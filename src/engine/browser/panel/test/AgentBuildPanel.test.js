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
	useSelect: jest.fn(),
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

import { useDispatch, useSelect } from '@wordpress/data';
import AgentBuildPanel from '../AgentBuildPanel';

const VALID_TREE_TEXT = JSON.stringify({
	version: 1,
	blocks: [{ name: 'test/static', attributes: { text: 'Hi' } }],
});

describe('AgentBuildPanel', () => {
	let insertBlocks;
	let createNotice;

	beforeEach(() => {
		insertBlocks = jest.fn();
		createNotice = jest.fn();
		useDispatch.mockImplementation((store) =>
			store === 'core/notices' ? { createNotice } : { insertBlocks }
		);
		useSelect.mockImplementation((mapSelect) =>
			mapSelect(() => ({ getReport: () => null }))
		);

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

	test('says "No issues found." only once a clean Check has run', () => {
		window.designsetgoEngine.assemble.mockReturnValue({
			status: 'valid',
			markup: '<!-- wp:test/static {"text":"Hi"} /-->',
			invalid: [],
			treeHash: 'abc',
		});

		render(<AgentBuildPanel />);

		expect(screen.queryByText(/no issues found/i)).not.toBeInTheDocument();

		typeTree(VALID_TREE_TEXT);
		expect(screen.queryByText(/no issues found/i)).not.toBeInTheDocument();

		fireEvent.click(screen.getByRole('button', { name: /check/i }));
		expect(screen.getByText(/no issues found/i)).toBeInTheDocument();

		// Editing again clears the result until the next Check.
		typeTree('{}');
		expect(screen.queryByText(/no issues found/i)).not.toBeInTheDocument();
	});

	test('does not say "No issues found." after invalid JSON', () => {
		render(<AgentBuildPanel />);

		typeTree('{ not valid json');
		fireEvent.click(screen.getByRole('button', { name: /check/i }));

		expect(screen.queryByText(/no issues found/i)).not.toBeInTheDocument();
	});

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

	test('editing the textarea after a valid Check disables Insert and clears the report', () => {
		window.designsetgoEngine.assemble.mockReturnValue({
			status: 'valid',
			markup: '<!-- wp:test/static {"text":"Hi"} /-->',
			invalid: [],
			treeHash: 'abc',
		});
		window.designsetgoEngine.lint.mockReturnValue([
			{
				rule: 'no-custom-html',
				severity: 'error',
				path: 'blocks[0]',
				message: 'custom html finding',
			},
		]);
		window.wp.blocks.parse.mockReturnValue([
			{ name: 'test/static', attributes: { text: 'Hi' } },
		]);

		render(<AgentBuildPanel />);

		typeTree(VALID_TREE_TEXT);
		fireEvent.click(screen.getByRole('button', { name: /check/i }));

		// Sanity: the valid Check left Insert enabled and the report visible.
		expect(
			screen.getByRole('button', { name: /insert/i })
		).not.toBeDisabled();
		expect(screen.getByText(/custom html finding/i)).toBeInTheDocument();

		// Edit the tree text WITHOUT re-checking — this must invalidate the
		// stale "valid" result from tree A, not leave Insert armed for it.
		typeTree(
			JSON.stringify({
				version: 1,
				blocks: [{ name: 'test/static', attributes: { text: 'Bye' } }],
			})
		);

		const insertButton = screen.getByRole('button', { name: /insert/i });
		expect(insertButton).toBeDisabled();
		expect(
			screen.queryByText(/custom html finding/i)
		).not.toBeInTheDocument();

		fireEvent.click(insertButton);

		expect(window.wp.blocks.parse).not.toHaveBeenCalled();
		expect(insertBlocks).not.toHaveBeenCalled();
	});

	// U2: long report paths must wrap instead of overflowing the sidebar.
	test('report paths carry the wrapping class', () => {
		window.designsetgoEngine.assemble.mockReturnValue({
			status: 'invalid',
			markup: '',
			invalid: [
				{
					path: 'blocks[1].innerBlocks[0].innerBlocks[0]',
					block: 'test/unknown',
					reason: 'Block "test/unknown" is not registered.',
				},
			],
		});

		const { container } = render(<AgentBuildPanel />);

		typeTree(
			JSON.stringify({ version: 1, blocks: [{ name: 'test/unknown' }] })
		);
		fireEvent.click(screen.getByRole('button', { name: /check/i }));

		const path = container.querySelector(
			'.dsgo-agent-build-panel__report-path'
		);
		expect(path).toBeInTheDocument();
		expect(path).toHaveTextContent(
			'blocks[1].innerBlocks[0].innerBlocks[0]'
		);
	});

	// U3: Insert must not be clickable twice for the same Check.
	describe('Insert disables itself once clicked (U3)', () => {
		beforeEach(() => {
			window.designsetgoEngine.assemble.mockReturnValue({
				status: 'valid',
				markup: '<!-- wp:test/static {"text":"Hi"} /-->',
				invalid: [],
				treeHash: 'abc',
			});
			window.wp.blocks.parse.mockReturnValue([
				{ name: 'test/static', attributes: { text: 'Hi' } },
			]);
		});

		test('Insert disables itself, inserts once, and shows a success notice', () => {
			render(<AgentBuildPanel />);
			typeTree(VALID_TREE_TEXT);
			fireEvent.click(screen.getByRole('button', { name: /check/i }));

			const insertButton = screen.getByRole('button', {
				name: /insert/i,
			});
			fireEvent.click(insertButton);

			expect(insertButton).toBeDisabled();
			expect(insertBlocks).toHaveBeenCalledTimes(1);
			expect(createNotice).toHaveBeenCalledWith(
				'success',
				expect.stringContaining('1'),
				expect.objectContaining({ type: 'snackbar' })
			);

			// Clicking again does nothing further.
			fireEvent.click(insertButton);
			expect(insertBlocks).toHaveBeenCalledTimes(1);
		});

		test('editing the text and Check re-enables Insert', () => {
			render(<AgentBuildPanel />);
			typeTree(VALID_TREE_TEXT);
			fireEvent.click(screen.getByRole('button', { name: /check/i }));
			fireEvent.click(screen.getByRole('button', { name: /insert/i }));

			expect(
				screen.getByRole('button', { name: /insert/i })
			).toBeDisabled();

			typeTree(
				JSON.stringify({
					version: 1,
					blocks: [
						{ name: 'test/static', attributes: { text: 'Bye' } },
					],
				})
			);
			fireEvent.click(screen.getByRole('button', { name: /check/i }));

			expect(
				screen.getByRole('button', { name: /insert/i })
			).not.toBeDisabled();
		});
	});

	// U4: the finish flow's stored report shows above the paste area.
	describe('the stored agent build report (U4)', () => {
		test('renders nothing when there is no stored report', () => {
			useSelect.mockImplementation((mapSelect) =>
				mapSelect(() => ({ getReport: () => null }))
			);

			render(<AgentBuildPanel />);

			expect(
				screen.queryByText(/pending agent build/i)
			).not.toBeInTheDocument();
			expect(
				screen.queryByText(/last agent build/i)
			).not.toBeInTheDocument();
		});

		test('shows "Pending agent build" and its findings for an awaiting_review report', () => {
			useSelect.mockImplementation((mapSelect) =>
				mapSelect(() => ({
					getReport: () => ({
						postId: 4,
						status: 'awaiting_review',
						invalid: [],
						findings: [
							{
								rule: 'mobile-layout',
								severity: 'warning',
								path: 'blocks[0]',
								message: 'stored finding message',
							},
						],
					}),
				}))
			);

			render(<AgentBuildPanel />);

			expect(
				screen.getByText(/pending agent build/i)
			).toBeInTheDocument();
			expect(
				screen.getByText(/stored finding message/i)
			).toBeInTheDocument();
		});

		test('shows "Last agent build" for a terminal status like failed', () => {
			useSelect.mockImplementation((mapSelect) =>
				mapSelect(() => ({
					getReport: () => ({
						postId: 4,
						status: 'failed',
						invalid: [
							{
								path: 'blocks[0]',
								block: 'core/unknown',
								reason: 'stored failure reason',
							},
						],
						findings: [],
					}),
				}))
			);

			render(<AgentBuildPanel />);

			expect(screen.getByText(/last agent build/i)).toBeInTheDocument();
			expect(
				screen.getByText(/stored failure reason/i)
			).toBeInTheDocument();
		});
	});
});
