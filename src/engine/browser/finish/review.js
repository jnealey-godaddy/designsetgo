/**
 * The "apply for review" outcome of finishing a pending agent build: the
 * assembled blocks go into the canvas unsaved, the server hears
 * `awaiting_review`, and a person gets a notice with a Discard action. A
 * later successful save reports the build's final status.
 *
 * Used whenever an automatic save would be wrong: the post is live
 * (published/future/private), or the build was submitted by someone other
 * than the person who opened the editor — saving it here would save another
 * user's content under this user's capabilities.
 *
 * Pure aside from the injected callbacks, like `./finish-build.js`.
 */
import { __ } from '@wordpress/i18n';

/** Stable notice id: a reload never stacks duplicate finish notices. */
export const FINISH_NOTICE_ID = 'designsetgo-agent-build-finish';

/**
 * Separate stable id for the "reporting itself failed" notice — never
 * replaces the review/Discard notice above, since both must be visible at
 * once.
 */
export const FINISH_REPORT_ERROR_NOTICE_ID =
	'designsetgo-agent-build-finish-report-error';

/**
 * `core/editor` autosave lock held while a build waits for review. Core
 * autosave on a draft the opener authored writes the post itself
 * (`wp_update_post()`), which would save the build without anyone clicking
 * Save — and, for another user's build, under the opener's capabilities.
 */
export const AUTOSAVE_LOCK_NAME = 'designsetgo-agent-build';

/**
 * The lock alone does not stop Preview: with classic meta boxes on the page
 * core passes `forceIsAutosaveable`, which skips the lock, and for a draft
 * the preview save updates the post itself. `savePost()` runs this filter
 * through `applyFiltersAsync()` before `saveEntityRecord()`, and a throw
 * aborts the save and shows core's "Updating failed." notice with the
 * message appended.
 */
export const PRE_SAVE_HOOK = 'editor.preSavePost';
export const REVIEW_FILTER_NAMESPACE = 'designsetgo/agent-build-review';

/**
 * `editor.preSavePost` callback held during review.
 *
 * @param {Object} edits     Post edits about to be saved.
 * @param {Object} [options] `savePost()` options.
 * @return {Object} The edits, unchanged, for a manual save.
 */
function refuseAutomaticSaves(edits, options) {
	if (options?.isPreview || options?.isAutosave) {
		throw new Error(
			__(
				'Save or discard the agent build before previewing.',
				'designsetgo'
			)
		);
	}
	return edits;
}

/**
 * @param {Object}   options
 * @param {Function} options.report         `(body) => Promise` — POSTs a report for this build.
 * @param {Function} options.replaceBlocks  `(blocks: Array) => void`.
 * @param {Function} options.lockAutosave   `(lockName: string) => void` — `core/editor` `lockPostAutosaving`.
 * @param {Function} options.unlockAutosave `(lockName: string) => void` — `core/editor` `unlockPostAutosaving`.
 * @param {Function} options.addFilter      `@wordpress/hooks` `addFilter`.
 * @param {Function} options.removeFilter   `@wordpress/hooks` `removeFilter`.
 * @param {Function} options.notify         `core/notices` `createNotice` shape.
 * @param {Function} options.onNextSave     `(callback) => unsubscribe` — one-shot callback for the next successful, non-autosave save.
 * @param {Array}    options.currentBlocks  Blocks in the editor before the build was applied (restored by Discard).
 * @param {Array}    options.nextBlocks     Blocks to apply.
 * @param {string}   options.status         Final status to report once saved: `finished` or `finished_with_findings`.
 * @param {Array}    options.findings       Lint findings, already mapped to the REST shape.
 * @param {boolean}  options.isSubmitter    Whether the person in the editor submitted this build.
 * @return {Promise<void>}
 */
export async function applyForReview({
	report,
	replaceBlocks,
	lockAutosave,
	unlockAutosave,
	addFilter,
	removeFilter,
	notify,
	onNextSave,
	currentBlocks,
	nextBlocks,
	status,
	findings,
	isSubmitter,
}) {
	// Hold automatic saves first: the canvas turns dirty the moment the
	// blocks go in.
	lockAutosave(AUTOSAVE_LOCK_NAME);
	addFilter(PRE_SAVE_HOOK, REVIEW_FILTER_NAMESPACE, refuseAutomaticSaves);
	const release = () => {
		removeFilter(PRE_SAVE_HOOK, REVIEW_FILTER_NAMESPACE);
		unlockAutosave(AUTOSAVE_LOCK_NAME);
	};

	// Until the Discard notice and the save watcher are both in place, a
	// throw must not leave the build in a canvas autosave may write: put
	// the original blocks back, then release. `applied` is set first, since
	// resetBlocks can update the store and then throw from a subscriber.
	let applied = false;
	let armed = false;
	try {
		applied = true;
		replaceBlocks(nextBlocks);

		// A failed report here must never suppress the review notice below —
		// without it, a person has no Discard affordance for blocks that are
		// already sitting in their canvas. Report failure is surfaced as its
		// own separate notice instead.
		let reportedAwaitingReview = true;
		try {
			await report({ status: 'awaiting_review', findings });
		} catch (error) {
			reportedAwaitingReview = false;
		}

		const message = isSubmitter
			? __('Review agent changes before updating.', 'designsetgo')
			: __(
					'An agent submitted these changes on behalf of another user. Review them before saving.',
					'designsetgo'
				);

		// Set once the next-save watcher is registered below; Discard calls it
		// so a save after discarding never reports the discarded build.
		let unsubscribeNextSave = () => {};
		let discarded = false;

		notify('warning', message, {
			id: FINISH_NOTICE_ID,
			// This notice carries the only Discard; dismissing it would strand
			// the applied blocks with no way back.
			isDismissible: false,
			actions: [
				{
					label: __('Discard', 'designsetgo'),
					onClick: () => {
						discarded = true;
						unsubscribeNextSave();
						replaceBlocks(currentBlocks);
						release();
						// finishBuild() has already returned by the time this
						// fires, so its try/catch can't cover it — never let a
						// network hiccup here surface as an unhandled rejection.
						Promise.resolve(report({ status: 'discarded' })).catch(
							() => {}
						);
					},
				},
			],
		});

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

		const unsubscribe = onNextSave(async () => {
			if (discarded) {
				return;
			}
			// A person saved the build on purpose; automatic saves may resume.
			release();
			// Fires long after finishBuild() has returned, so its try/catch
			// can't cover this either — see the Discard handler above.
			try {
				await report({ status, findings });
			} catch (error) {
				// Nothing left to report to; the tree is already cleared or
				// still marked awaiting_review server-side either way.
			}
		});

		if (typeof unsubscribe === 'function') {
			unsubscribeNextSave = unsubscribe;
		}

		armed = true;
	} finally {
		if (!armed) {
			if (applied) {
				replaceBlocks(currentBlocks);
			}
			release();
		}
	}
}
