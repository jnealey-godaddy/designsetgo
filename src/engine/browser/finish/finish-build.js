/**
 * Orchestrates finishing a pending agent build in the block editor: fetch
 * the pending tree, assemble + lint it against the site's real registered
 * blocks, apply the result, and report the outcome back over REST.
 *
 * The build is saved automatically only when the person in the editor
 * submitted it AND the post is not live. Every other case — a published
 * post, or a build submitted by another user — is applied for review
 * instead (see `./review.js`), so one user's content is never saved under
 * another user's capabilities. Unless the submitter held `unfiltered_html`,
 * the assembled markup is KSES-filtered server-side before either branch
 * applies it (see `./sanitize.js`).
 *
 * Pure aside from the injected `deps` — every WordPress store access
 * (`@wordpress/data`, `@wordpress/editor`, `@wordpress/notices`) lives in
 * `./index.js`, which is what makes this file unit-testable with plain
 * fakes (those packages are stubbed in Jest, see `jest.config.js`). See
 * `./apply.js` for the pure helpers this leans on.
 */
import { __ } from '@wordpress/i18n';
import {
	assembleTree,
	composeBlocks,
	failedMessage,
	savedWithIssuesMessage,
	viewDetailsAction,
} from './apply';
import { sanitizeAssembled } from './sanitize';
import {
	applyForReview,
	FINISH_NOTICE_ID,
	FINISH_REPORT_ERROR_NOTICE_ID,
} from './review';

export { FINISH_NOTICE_ID, FINISH_REPORT_ERROR_NOTICE_ID };

/** `invalid` entry reported when block registration never settles. */
const REGISTRATION_TIMEOUT_INVALID = [
	{ path: '', block: '', reason: 'block registration did not settle' },
];

/** `invalid` entry reported when saving the applied draft fails. */
const SAVE_FAILED_INVALID = [{ path: '', block: '', reason: 'save failed' }];

/**
 * @param {number}   postId               The post being finished.
 * @param {Object}   deps
 * @param {Function} deps.fetchPending    `() => Promise<Object>` — GET response: `{ pending, buildId?, tree?, mode?, conflict?, isSubmitter?, submitterUnfiltered?, designContext? }`.
 * @param {Function} deps.postReport      `(body: Object) => Promise<void>` — POSTs a report for this post; every body gets the pending `buildId`.
 * @param {Function} deps.sanitizeMarkup  `({ buildId, markup }) => Promise<{ markup }>` — POSTs to the sanitize route.
 * @param {Object}   deps.engine          `{ assemble, lint }` bound to the site's block registry.
 * @param {Function} deps.parse           `wp.blocks.parse`.
 * @param {Function} deps.validateBlock   `wp.blocks.validateBlock` — `(block) => [isValid, log]`.
 * @param {Function} deps.getEditorBlocks `() => Array` current editor blocks.
 * @param {Function} deps.replaceBlocks   `(blocks: Array) => void`.
 * @param {Function} deps.lockAutosave    `(lockName: string) => void` — `core/editor` `lockPostAutosaving`.
 * @param {Function} deps.unlockAutosave  `(lockName: string) => void` — `core/editor` `unlockPostAutosaving`.
 * @param {Function} deps.addFilter       `@wordpress/hooks` `addFilter`.
 * @param {Function} deps.removeFilter    `@wordpress/hooks` `removeFilter`.
 * @param {Function} deps.savePost        `() => Promise<boolean>` resolves whether the save succeeded.
 * @param {Function} deps.isPublished     `() => boolean` — post status is `publish`/`future`/`private`.
 * @param {Function} deps.notify          `(status, message, options) => void` — `core/notices` `createNotice` shape.
 * @param {Function} deps.removeNotice    `core/notices` `removeNotice` shape — `(id: string) => void`.
 * @param {Function} deps.openSidebar     `() => void` — opens the Agent build sidebar for "View details".
 * @param {Function} deps.setReport       `(report: Object|null) => void` — stores the report the panel reads.
 * @param {Function} deps.markDocument    `(state: string) => void` — sets `document.documentElement.dataset.dsgoFinish`.
 * @param {Function} deps.onNextSave      `(callback: Function) => Function` — registers a one-shot callback for the next successful, non-autosave save; returns `unsubscribe`.
 * @return {Promise<void>}
 */
export async function finishBuild(postId, deps) {
	const {
		fetchPending,
		postReport,
		sanitizeMarkup,
		engine,
		parse,
		validateBlock,
		getEditorBlocks,
		replaceBlocks,
		lockAutosave,
		unlockAutosave,
		addFilter,
		removeFilter,
		savePost,
		isPublished,
		notify,
		removeNotice,
		openSidebar,
		setReport,
		markDocument,
		onNextSave,
	} = deps;

	try {
		const pending = await fetchPending();

		if (!pending || !pending.pending) {
			markDocument('done');
			return;
		}

		// Every report names the build it describes; the server rejects a
		// report whose buildId no longer matches what is pending (409).
		const report = (body) =>
			postReport({ ...body, buildId: pending.buildId });

		if (pending.conflict) {
			await report({ status: 'conflict' });
			notify(
				'warning',
				__(
					'This page changed since the agent build was queued. Nothing was applied.',
					'designsetgo'
				),
				{
					id: FINISH_NOTICE_ID,
					actions: [viewDetailsAction(openSidebar)],
				}
			);
			setReport({ status: 'conflict', invalid: [], findings: [] });
			markDocument('failed');
			return;
		}

		const { tree, mode, designContext } = pending;
		const currentBlocks = getEditorBlocks();
		const result = assembleTree({
			engine,
			parse,
			tree,
			designContext,
			mode,
			currentBlocks,
		});

		const fail = async (invalid) => {
			await report({
				status: 'failed',
				invalid,
				findings: result.findings,
			});
			notify('error', failedMessage(invalid), {
				id: FINISH_NOTICE_ID,
				actions: [viewDetailsAction(openSidebar)],
			});
			setReport({ status: 'failed', invalid, findings: result.findings });
			markDocument('failed');
		};

		if (!result.ok) {
			await fail(result.invalid);
			return;
		}

		const { findings } = result;
		let nextBlocks = result.blocks;

		// Strictly `true`: a missing flag must never skip the filter.
		if (pending.submitterUnfiltered !== true) {
			const sanitized = await sanitizeAssembled({
				sanitizeMarkup,
				parse,
				validateBlock,
				buildId: pending.buildId,
				markup: result.markup,
				parsedBlocks: result.parsedBlocks,
			});
			if (!sanitized.ok) {
				await fail(sanitized.invalid);
				return;
			}
			nextBlocks = composeBlocks(mode, currentBlocks, sanitized.blocks);
		}

		const status = findings.length ? 'finished_with_findings' : 'finished';
		// Strictly `true`: a missing flag must never be read as permission to
		// save someone else's build.
		const isSubmitter = pending.isSubmitter === true;

		if (!isSubmitter || isPublished()) {
			await applyForReview({
				report,
				replaceBlocks,
				lockAutosave,
				unlockAutosave,
				addFilter,
				removeFilter,
				notify,
				removeNotice,
				openSidebar,
				setReport,
				onNextSave,
				currentBlocks,
				nextBlocks,
				status,
				findings,
				isSubmitter,
			});
			markDocument('done');
			return;
		}

		replaceBlocks(nextBlocks);
		let saved = false;
		try {
			saved = await savePost();
		} catch (error) {
			saved = false;
		}

		if (saved) {
			await report({ status, findings });
			notify('success', savedWithIssuesMessage(findings), {
				id: FINISH_NOTICE_ID,
				...(findings.length
					? { actions: [viewDetailsAction(openSidebar)] }
					: {}),
			});
			setReport({ status, invalid: [], findings });
			markDocument('done');
			return;
		}

		// Never leave an unsaved, already-reported-failed build in a dirty
		// canvas for core autosave to write.
		replaceBlocks(currentBlocks);
		await report({ status: 'failed', invalid: SAVE_FAILED_INVALID });
		notify(
			'error',
			__(
				'The agent build could not be saved, so it was removed from the editor.',
				'designsetgo'
			),
			{ id: FINISH_NOTICE_ID, actions: [viewDetailsAction(openSidebar)] }
		);
		setReport({
			status: 'failed',
			invalid: SAVE_FAILED_INVALID,
			findings: [],
		});
		markDocument('failed');
	} catch (error) {
		markDocument('failed');
		notify(
			'error',
			__('Could not check for a pending agent build.', 'designsetgo'),
			{ id: FINISH_NOTICE_ID }
		);
	}
}

/**
 * Handles a block registry that never settled. Only called once the editor
 * context is known to be finishable (see `./context.js`), so a timeout in
 * the Site Editor or widgets editor never reaches here. Reports `failed`
 * only when a build is actually pending — naming that build — and stays
 * silent otherwise.
 *
 * @param {Object}   deps              Same shape as `finishBuild()`'s deps; only these are used:
 * @param {Function} deps.fetchPending
 * @param {Function} deps.postReport
 * @param {Function} deps.notify
 * @param {Function} deps.markDocument
 * @return {Promise<void>}
 */
export async function finishAfterRegistrationTimeout({
	fetchPending,
	postReport,
	notify,
	markDocument,
}) {
	try {
		const pending = await fetchPending();

		if (!pending || !pending.pending) {
			markDocument('done');
			return;
		}

		markDocument('failed');
		await postReport({
			status: 'failed',
			buildId: pending.buildId,
			invalid: REGISTRATION_TIMEOUT_INVALID,
		});
	} catch (error) {
		// The dataset attribute is the only signal left for automation.
		markDocument('failed');
	}

	notify(
		'error',
		__('The block editor did not finish loading in time.', 'designsetgo'),
		{ id: FINISH_NOTICE_ID }
	);
}
