/**
 * AgentBuildPanel — the "Agent build" sidebar content: paste a JSON block
 * tree, Check it (assemble + lint against `window.designsetgoEngine`), then
 * Insert it (only once valid) via `core/block-editor`'s `insertBlocks`.
 *
 * Rendered directly (not through `registerPlugin`) per the project's Jest
 * setup: `@wordpress/editor` is stubbed to a bare store id in
 * `tests/unit/__mocks__/wordpressEditorMock.js`, so `PluginSidebar` isn't
 * available here — that's exercised only by `./index.js`, which this file
 * doesn't import.
 */
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/data', () => ({
	useDispatch: jest.fn(),
}));

// The real @wordpress/components pulls in @wordpress/rich-text's data store,
// which needs the real @wordpress/data this file replaces above. Stub the
// handful of components this panel actually uses, the same way
// draft-mode-controls.test.js and overlay-header-panel.test.js do.
jest.mock('@wordpress/components', () => ({
	TextareaControl: ({
		label,
		help,
		value,
		onChange,
		__nextHasNoMarginBottom, // eslint-disable-line no-unused-vars
		...props
	}) => (
		<label>
			{label}
			<textarea
				value={value}
				onChange={(event) => onChange(event.target.value)}
				{...props}
			/>
			{help && <span>{help}</span>}
		</label>
	),
	Button: ({ children, onClick, disabled, variant, ...props }) => (
		<button
			onClick={onClick}
			disabled={disabled}
			data-variant={variant}
			{...props}
		>
			{children}
		</button>
	),
	Notice: ({ children, status }) => (
		<div role="alert" data-status={status}>
			{children}
		</div>
	),
}));

import { useDispatch } from '@wordpress/data';
import AgentBuildPanel from '../AgentBuildPanel';

const VALID_TREE_TEXT = JSON.stringify({
	version: 1,
	blocks: [{ name: 'test/static', attributes: { text: 'Hi' } }],
});

describe('AgentBuildPanel', () => {
	let insertBlocks;

	beforeEach(() => {
		insertBlocks = jest.fn();
		useDispatch.mockReturnValue({ insertBlocks });

		window.designsetgoEngine = {
			version: 1,
			assemble: jest.fn(),
			validate: jest.fn(),
			lint: jest.fn().mockReturnValue([]),
		};
		window.wp = { blocks: { parse: jest.fn() } };
	});

	afterEach(() => {
		delete window.designsetgoEngine;
		delete window.wp;
	});

	function typeTree(text) {
		fireEvent.change(screen.getByLabelText(/block tree/i), {
			target: { value: text },
		});
	}

	test('shows an error notice for invalid JSON and keeps Insert disabled', () => {
		render(<AgentBuildPanel />);

		typeTree('{ not valid json');
		fireEvent.click(screen.getByRole('button', { name: /check/i }));

		expect(screen.getByText(/invalid json/i)).toBeInTheDocument();
		expect(screen.getByRole('button', { name: /insert/i })).toBeDisabled();
		expect(window.designsetgoEngine.assemble).not.toHaveBeenCalled();
	});

	test('disables Insert and lists invalid entries for an invalid tree', () => {
		window.designsetgoEngine.assemble.mockReturnValue({
			status: 'invalid',
			markup: '',
			invalid: [
				{
					path: 'blocks[0]',
					block: 'test/unknown',
					reason: 'Block "test/unknown" is not registered.',
				},
			],
			treeHash: 'abc',
		});

		render(<AgentBuildPanel />);

		typeTree(
			JSON.stringify({
				version: 1,
				blocks: [{ name: 'test/unknown' }],
			})
		);
		fireEvent.click(screen.getByRole('button', { name: /check/i }));

		expect(screen.getByRole('button', { name: /insert/i })).toBeDisabled();
		expect(screen.getByText(/is not registered/i)).toBeInTheDocument();
		expect(insertBlocks).not.toHaveBeenCalled();
	});

	test('checks a valid tree with {} design context and inserts the parsed blocks on Insert', () => {
		window.designsetgoEngine.assemble.mockReturnValue({
			status: 'valid',
			markup: '<!-- wp:test/static {"text":"Hi"} /-->',
			invalid: [],
			treeHash: 'abc',
		});
		const parsedBlocks = [
			{ name: 'test/static', attributes: { text: 'Hi' } },
		];
		window.wp.blocks.parse.mockReturnValue(parsedBlocks);

		render(<AgentBuildPanel />);

		typeTree(VALID_TREE_TEXT);
		fireEvent.click(screen.getByRole('button', { name: /check/i }));

		expect(window.designsetgoEngine.assemble).toHaveBeenCalledWith({
			version: 1,
			blocks: [{ name: 'test/static', attributes: { text: 'Hi' } }],
		});
		expect(window.designsetgoEngine.lint).toHaveBeenCalledWith(
			{
				version: 1,
				blocks: [{ name: 'test/static', attributes: { text: 'Hi' } }],
			},
			{}
		);

		const insertButton = screen.getByRole('button', { name: /insert/i });
		expect(insertButton).not.toBeDisabled();

		fireEvent.click(insertButton);

		expect(window.wp.blocks.parse).toHaveBeenCalledWith(
			'<!-- wp:test/static {"text":"Hi"} /-->'
		);
		expect(insertBlocks).toHaveBeenCalledWith(parsedBlocks);
	});
});
