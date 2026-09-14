/**
 * "Agent build" panel content: paste a JSON block tree, Check it (assemble
 * + lint against `window.designsetgoEngine`, set up by `../index.js`), then
 * Insert it once valid.
 *
 * Lint runs with an empty `{}` design context — the palette/spacing/font
 * checks degrade to "no known presets", which still catches raw hex colors
 * etc. Wiring a real design context (e.g. from `getEditorSettings().colors`)
 * is possible but not required for this panel; see the Task 15 brief.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { TextareaControl, Button, Notice } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import ReportList from './ReportList';

const EMPTY_REPORT = { invalid: [], findings: [] };

/**
 * @return {JSX.Element} The panel content.
 */
export default function AgentBuildPanel() {
	const [treeText, setTreeText] = useState('');
	const [parseError, setParseError] = useState('');
	const [report, setReport] = useState(EMPTY_REPORT);
	const [markup, setMarkup] = useState('');
	const [isValid, setIsValid] = useState(false);
	const { insertBlocks } = useDispatch('core/block-editor');

	/**
	 * Clears everything Check/Insert produced: the last parse error, the
	 * assembled markup, the valid flag, and the report. Called both before
	 * a fresh Check and on every textarea edit, so a stale "valid" result
	 * from a previous tree can never linger against newly-typed text.
	 */
	function resetCheckState() {
		setParseError('');
		setMarkup('');
		setIsValid(false);
		setReport(EMPTY_REPORT);
	}

	/**
	 * @param {string} value New textarea contents.
	 */
	function handleTreeTextChange(value) {
		setTreeText(value);
		resetCheckState();
	}

	/**
	 * Parses the textarea's JSON, then runs `assemble()`/`lint()` against
	 * `window.designsetgoEngine` and stores the report.
	 */
	function handleCheck() {
		resetCheckState();

		let tree;
		try {
			tree = JSON.parse(treeText);
		} catch (error) {
			setParseError(
				sprintf(
					/* translators: %s: JSON parse error message. */
					__('Invalid JSON: %s', 'designsetgo'),
					error.message
				)
			);
			return;
		}

		const engine = window.designsetgoEngine;
		const assembled = engine.assemble(tree);
		const findings = engine.lint(tree, {});

		setReport({ invalid: assembled.invalid, findings });
		setMarkup(assembled.markup);
		setIsValid(assembled.status === 'valid');
	}

	/**
	 * Parses the last-assembled markup with the site's own block registry
	 * and inserts the result into the post.
	 */
	function handleInsert() {
		if (!isValid) {
			return;
		}
		const parsedBlocks = window.wp.blocks.parse(markup);
		insertBlocks(parsedBlocks);
	}

	return (
		<div className="dsgo-agent-build-panel">
			<TextareaControl
				__nextHasNoMarginBottom
				label={__('Block tree (JSON)', 'designsetgo')}
				help={
					/* translators: this is a literal JSON example the user pastes over — keep the keys, quoting, and structure exactly as shown; do not translate any part of it. */
					__(
						'{ "version": 1, "blocks": [ { "name": "core/paragraph", "attributes": {} } ] }',
						'designsetgo'
					)
				}
				value={treeText}
				onChange={handleTreeTextChange}
				rows={14}
			/>

			{parseError && (
				<Notice status="error" isDismissible={false}>
					{parseError}
				</Notice>
			)}

			<div className="dsgo-agent-build-panel__actions">
				<Button variant="secondary" onClick={handleCheck}>
					{__('Check', 'designsetgo')}
				</Button>
				<Button
					variant="primary"
					onClick={handleInsert}
					disabled={!isValid}
				>
					{__('Insert', 'designsetgo')}
				</Button>
			</div>

			<ReportList invalid={report.invalid} findings={report.findings} />
		</div>
	);
}
