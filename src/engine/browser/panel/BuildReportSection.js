/**
 * Shows the finish flow's last agent build report for the current post,
 * above the paste area — status, findings grouped by severity, and any
 * invalid entries (reusing `ReportList`). Reads from the
 * `designsetgo/agent-build` data store the finish flow writes to (see
 * `../report-store.js`), so a reviewer can see WHY a build needs review, or
 * why it failed, without pasting anything themselves (U4).
 */
import { __ } from '@wordpress/i18n';
import ReportList from './ReportList';

/**
 * @param {string} status One of the finish flow's report statuses.
 * @return {string} A short, human label for it.
 */
function describeStatus(status) {
	switch (status) {
		case 'awaiting_review':
			return __('Awaiting review', 'designsetgo');
		case 'finished_with_findings':
			return __('Finished with findings', 'designsetgo');
		case 'finished':
			return __('Finished', 'designsetgo');
		case 'failed':
			return __('Failed', 'designsetgo');
		case 'conflict':
			return __('Conflict', 'designsetgo');
		case 'discarded':
			return __('Discarded', 'designsetgo');
		default:
			return status;
	}
}

/**
 * @param {Object}      props
 * @param {Object|null} [props.report] `{ status, invalid?, findings? }`, or `null`/`undefined`.
 * @return {JSX.Element|null} The section, or `null` when there is nothing to show.
 */
export default function BuildReportSection({ report }) {
	if (!report || !report.status) {
		return null;
	}

	const title =
		report.status === 'awaiting_review'
			? __('Pending agent build', 'designsetgo')
			: __('Last agent build', 'designsetgo');

	return (
		<div className="dsgo-agent-build-panel__stored-report">
			<h2>{title}</h2>
			<p className="dsgo-agent-build-panel__stored-report-status">
				{describeStatus(report.status)}
			</p>
			<ReportList invalid={report.invalid} findings={report.findings} />
		</div>
	);
}
