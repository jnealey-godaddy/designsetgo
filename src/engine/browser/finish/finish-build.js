/**
 * Orchestrates finishing a pending agent build in the block editor: fetch
 * the pending tree, assemble + lint it against the site's real registered
 * blocks, apply the result, and report the outcome back over REST.
 *
 * The build is saved automatically only when the person in the editor
 * submitted it AND the post is not live. Every other case — a published
 * post, or a build submitted by another user — is applied for review
 * instead (see `./review.js`), so one user's content is never saved under
 * another user's capabilities.
 *
 * Pure aside from the injected `deps` — every WordPress store access
 * (`@wordpress/data`, `@wordpress/editor`, `@wordpress/notices`) lives in
 * `./index.js`, which is what makes this file unit-testable with plain
 * fakes (those packages are stubbed in Jest, see `jest.config.js`). See
 * `./apply.js` for the pure helpers this leans on.
 */
import { __ } from '@wordpress/i18n';
import { assembleTree } from './apply';
import {
	applyForReview,
	FINISH_NOTICE_ID,
	FINISH_REPORT_ERROR_NOTICE_ID,
} from './review';

export { FINISH_NOTICE_ID, FINISH_REPORT_ERROR_NOTICE_ID };

/** `invalid` entry `postReport()` gets when saving the applied draft fails. */
const SAVE_FAILED_INVALID = [{ path: '', block: '', reason: 'save failed' }];

/**
 * @param {number}   postId               The post being finished.
 * @param {Object}   deps
 * @param {Function} deps.fetchPending    `() => Promise<Object>` — GET response: `{ pending, tree?, mode?, conflict?, isSubmitter?, designContext? }`.
 * @param {Function} deps.postReport      `(body: Object) => Promise<void>` — POSTs a report for this post.
 * @param {Object}   deps.engine          `{ assemble, lint }` bound to the site's block registry.
 * @param {Function} deps.parse           `wp.blocks.parse`.
 * @param {Function} deps.getEditorBlocks `() => Array` current editor blocks.
 * @param {Function} deps.replaceBlocks   `(blocks: Array) => void`.
 * @param {Function} deps.savePost        `() => Promise<boolean>` resolves whether the save succeeded.
 * @param {Function} deps.isPublished     `() => boolean` — post status is `publish`/`future`/`private`.
 * @param {Function} deps.notify          `(status, message, options) => void` — `core/notices` `createNotice` shape.
 * @param {Function} deps.markDocument    `(state: string) => void` — sets `document.documentElement.dataset.dsgoFinish`.
 * @param {Function} deps.onNextSave      `(callback: Function) => Function` — registers a one-shot callback for the next successful, non-autosave save; returns `unsubscribe`.
 * @return {Promise<void>}
 */
export async function finishBuild(postId, deps) {
	const {
		fetchPending,
		postReport,
		engine,
		parse,
		getEditorBlocks,
		replaceBlocks,
		savePost,
		isPublished,
		notify,
		markDocument,
		onNextSave,
	} = deps;

	try {
		const pending = await fetchPending();

		if (!pending || !pending.pending) {
			markDocument('done');
			return;
		}

		if (pending.conflict) {
			await postReport({ status: 'conflict' });
			notify(
				'warning',
				__(
					'This page changed since the agent build was queued. Nothing was applied.',
					'designsetgo'
				),
				{ id: FINISH_NOTICE_ID }
			);
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

		if (!result.ok) {
			await postReport({
				status: 'failed',
				invalid: result.invalid,
				findings: result.findings,
			});
			notify(
				'error',
				__('The agent build could not be applied.', 'designsetgo'),
				{ id: FINISH_NOTICE_ID }
			);
			markDocument('failed');
			return;
		}

		const { blocks: nextBlocks, findings } = result;
		const status = findings.length ? 'finished_with_findings' : 'finished';
		// Strictly `true`: a missing flag must never be read as permission to
		// save someone else's build.
		const isSubmitter = pending.isSubmitter === true;

		if (!isSubmitter || isPublished()) {
			await applyForReview({
				report: postReport,
				replaceBlocks,
				notify,
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
		const saved = await savePost();

		if (saved) {
			await postReport({ status, findings });
			notify(
				'success',
				__('Agent build applied and saved.', 'designsetgo'),
				{ id: FINISH_NOTICE_ID }
			);
			markDocument('done');
			return;
		}

		await postReport({ status: 'failed', invalid: SAVE_FAILED_INVALID });
		notify(
			'error',
			__(
				'Agent build was applied but could not be saved.',
				'designsetgo'
			),
			{ id: FINISH_NOTICE_ID }
		);
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
