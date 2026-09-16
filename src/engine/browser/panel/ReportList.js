/**
 * Renders an "Agent build" Check report: shape/unknown-block problems from
 * `assemble()`'s `invalid` array, and design-mistake findings from `lint()`,
 * grouped by severity. Findings already arrive sorted in document order
 * (see `../../lint/index.js`), so this component only groups, never sorts.
 */
import { __ } from '@wordpress/i18n';

/**
 * @param {Object}   props
 * @param {object[]} [props.invalid]  `assemble()` invalid entries: `{ path, block, reason }`.
 * @param {object[]} [props.findings] `lint()` findings: `{ rule, severity, path, message, suggestion? }`.
 * @return {JSX.Element} The report.
 */
export default function ReportList({ invalid = [], findings = [] }) {
	const errors = findings.filter((finding) => finding.severity === 'error');
	const warnings = findings.filter(
		(finding) => finding.severity === 'warning'
	);

	if (!invalid.length && !findings.length) {
		return (
			<p className="dsgo-agent-build-panel__report-empty">
				{__('No issues found.', 'designsetgo')}
			</p>
		);
	}

	return (
		<div className="dsgo-agent-build-panel__report">
			<ReportGroup
				title={__('Invalid', 'designsetgo')}
				items={invalid}
				renderItem={(entry, index) => (
					<li key={index}>
						<code className="dsgo-agent-build-panel__report-path">
							{entry.path}
						</code>
						{entry.block ? ` [${entry.block}]` : ''}: {entry.reason}
					</li>
				)}
			/>
			<ReportGroup
				title={__('Errors', 'designsetgo')}
				items={errors}
				renderItem={renderFinding}
			/>
			<ReportGroup
				title={__('Warnings', 'designsetgo')}
				items={warnings}
				renderItem={renderFinding}
			/>
		</div>
	);
}

/**
 * One lint finding as a list item, including its suggestion when present.
 *
 * @param {{rule: string, path: string, message: string, suggestion?: string}} finding Finding to render.
 * @param {number}                                                             index   List index, used as the React key.
 * @return {JSX.Element} The list item.
 */
function renderFinding(finding, index) {
	return (
		<li key={index}>
			<code className="dsgo-agent-build-panel__report-path">
				{finding.path}
			</code>{' '}
			[{finding.rule}]: {finding.message}
			{finding.suggestion ? ` — ${finding.suggestion}` : ''}
		</li>
	);
}

/**
 * One titled group of report items; renders nothing when `items` is empty.
 *
 * @param {Object}                                       props
 * @param {string}                                       props.title      Group heading.
 * @param {object[]}                                     props.items      Entries to render.
 * @param {(item: object, index: number) => JSX.Element} props.renderItem Per-item renderer.
 * @return {JSX.Element|null} The group, or `null` when empty.
 */
function ReportGroup({ title, items, renderItem }) {
	if (!items.length) {
		return null;
	}

	return (
		<div className="dsgo-agent-build-panel__report-group">
			<h3>{title}</h3>
			<ul>{items.map(renderItem)}</ul>
		</div>
	);
}
