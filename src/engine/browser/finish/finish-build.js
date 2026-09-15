/**
 * Orchestrates finishing a pending agent build in the block editor: fetch
 * the pending tree, assemble + lint it against the site's real registered
 * blocks, apply the result (saving drafts, leaving published posts for
 * review), and report the outcome back over REST.
 *
 * Pure aside from the injected `deps` — every WordPress store access
 * (`@wordpress/data`, `@wordpress/editor`, `@wordpress/notices`) lives in
 * `./index.js`, which is what makes this file unit-testable with plain
 * fakes (those packages are stubbed in Jest, see `jest.config.js`). See
 * `./apply.js` for the pure helpers this leans on.
 */
import { __ } from '@wordpress/i18n';
import { assembleTree } from './apply';

/** Stable notice id: a reload never stacks duplicate finish notices. */
export const FINISH_NOTICE_ID = 'designsetgo-agent-build-finish';

/**
 * Separate stable id for the "reporting itself failed" notice — never
 * replaces the review/Discard notice above, since both must be visible at
 * once (see the published branch below).
 */
export const FINISH_REPORT_ERROR_NOTICE_ID =
	'designsetgo-agent-build-finish-report-error';

/** `invalid` entry `postReport()` gets when saving the applied draft fails. */
const SAVE_FAILED_INVALID = [{ path: '', block: '', reason: 'save failed' }];

/**
 * @param {number}   postId               The post being finished.
 * @param {Object}   deps
 * @param {Function} deps.fetchPending    `() => Promise<Object>` — GET response: `{ pending, tree?, mode?, conflict?, designContext? }`.
 * @param {Function} deps.postReport      `(body: Object) => Promise<void>` — POSTs a report for this post.
 * @param {Object}   deps.engine          `{ assemble, lint }` bound to the site's block registry.
 * @param {Function} deps.parse           `wp.blocks.parse`.
 * @param {Function} deps.getEditorBlocks `() => Array` current editor blocks.
 * @param {Function} deps.replaceBlocks   `(blocks: Array) => void`.
 * @param {Function} deps.savePost        `() => Promise<boolean>` resolves whether the save succeeded.
 * @param {Function} deps.isPublished     `() => boolean` — post status is `publish`/`future`/`private`.
 * @param {Function} deps.notify          `(status, message, options) => void` — `core/notices` `createNotice` shape.
 * @param {Function} deps.markDocument    `(state: string) => void` — sets `document.documentElement.dataset.dsgoFinish`.
 * @param {Function} deps.onNextSave      `(callback: Function) => void` — registers a one-shot callback for the next successful, non-autosave save (published posts only).
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

		if (!isPublished()) {
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
			} else {
				await postReport({
					status: 'failed',
					invalid: SAVE_FAILED_INVALID,
				});
				notify(
					'error',
					__(
						'Agent build was applied but could not be saved.',
						'designsetgo'
					),
					{ id: FINISH_NOTICE_ID }
				);
				markDocument('failed');
			}
			return;
		}

		// Published: apply for review only, never save automatically.
		const original = currentBlocks;
		replaceBlocks(nextBlocks);

		// A failed report here must never suppress the review notice below —
		// without it, a person has no Discard affordance for blocks that are
		// already sitting in their canvas. Report failure is surfaced as its
		// own separate notice instead.
		let reportedAwaitingReview = true;
		try {
			await postReport({ status: 'awaiting_review', findings });
		} catch (error) {
			reportedAwaitingReview = false;
		}

		notify(
			'warning',
			__('Review agent changes before updating.', 'designsetgo'),
			{
				id: FINISH_NOTICE_ID,
				isDismissible: true,
				actions: [
					{
						label: __('Discard', 'designsetgo'),
						onClick: () => {
							replaceBlocks(original);
							// finishBuild() has already returned by the time this
							// fires, so its try/catch above can't cover it — never
							// let a network hiccup here surface as an unhandled
							// rejection.
							Promise.resolve(
								postReport({ status: 'discarded' })
							).catch(() => {});
						},
					},
				],
			}
		);

		if (!reportedAwaitingReview) {
			notify(
				'error',
				__(
					'Could not report the applied build back to the server.',
					'designsetgo'
				),
				{ id: FINISH_REPORT_ERROR_NOTICE_ID }
			);
		}

		onNextSave(async () => {
			// Fires long after finishBuild() has returned, so its try/catch
			// above can't cover this either — see the Discard handler above.
			try {
				await postReport({ status, findings });
			} catch (error) {
				// Nothing left to report to; the tree is already cleared or
				// still marked awaiting_review server-side either way.
			}
		});

		markDocument('done');
	} catch (error) {
		markDocument('failed');
		notify(
			'error',
			__('Could not check for a pending agent build.', 'designsetgo'),
			{ id: FINISH_NOTICE_ID }
		);
	}
}
