/**
 * Handles a block registry that never settled in time — split out of
 * `./finish-build.js` (which stayed close to the 300-line cap) since this
 * is a small, self-contained fallback path with its own deps subset.
 *
 * Pure aside from the injected `deps`, like `./finish-build.js`.
 */
import { __ } from '@wordpress/i18n';
import { FINISH_NOTICE_ID } from './review';

/** `invalid` entry reported when block registration never settles. */
export const REGISTRATION_TIMEOUT_INVALID = [
	{ path: '', block: '', reason: 'block registration did not settle' },
];

/**
 * Only called once the editor context is known to be finishable (see
 * `./context.js`), so a timeout in the Site Editor or widgets editor never
 * reaches here. Reports `failed` only when a build is actually pending —
 * naming that build — and stays silent otherwise. Either way, the stored
 * report is always brought in line with what actually happened (U1): a
 * `failed` report naming the timeout when a build was pending, or cleared
 * entirely when nothing was, so a report from an earlier build this same
 * page load can never linger.
 *
 * @param {Object}   deps              Same shape as `finishBuild()`'s deps; only these are used:
 * @param {Function} deps.fetchPending
 * @param {Function} deps.postReport
 * @param {Function} deps.notify
 * @param {Function} deps.setReport
 * @param {Function} deps.markDocument
 * @return {Promise<void>}
 */
export async function finishAfterRegistrationTimeout({
	fetchPending,
	postReport,
	notify,
	setReport,
	markDocument,
}) {
	try {
		const pending = await fetchPending();

		if (!pending || !pending.pending) {
			markDocument('done');
			setReport(null);
			return;
		}

		markDocument('failed');
		setReport({
			status: 'failed',
			invalid: REGISTRATION_TIMEOUT_INVALID,
			findings: [],
		});
		await postReport({
			status: 'failed',
			buildId: pending.buildId,
			invalid: REGISTRATION_TIMEOUT_INVALID,
		});
	} catch (error) {
		// The dataset attribute is the only signal left for automation.
		markDocument('failed');
		setReport({
			status: 'failed',
			invalid: REGISTRATION_TIMEOUT_INVALID,
			findings: [],
		});
	}

	notify(
		'error',
		__('The block editor did not finish loading in time.', 'designsetgo'),
		{ id: FINISH_NOTICE_ID }
	);
}
