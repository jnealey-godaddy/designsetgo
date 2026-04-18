/**
 * DsgoChildToolbar Tests
 *
 * @package
 */

import { render, screen, fireEvent } from '@testing-library/react';

const mockInsertBlock = jest.fn();
const mockRemoveBlock = jest.fn();
const mockMoveBlocksUp = jest.fn();
const mockMoveBlocksDown = jest.fn();
const mockReplaceBlock = jest.fn();
const mockUseDispatch = jest.fn(() => ({
	insertBlock: mockInsertBlock,
	removeBlock: mockRemoveBlock,
	moveBlocksUp: mockMoveBlocksUp,
	moveBlocksDown: mockMoveBlocksDown,
	replaceBlock: mockReplaceBlock,
}));
const mockCreateBlock = jest.fn((name, attrs) => ({
	clientId: 'new',
	name,
	attributes: attrs,
}));
const mockCloneBlock = jest.fn((block) => ({ ...block, clientId: 'cloned' }));

jest.mock('@wordpress/data', () => ({
	useDispatch: (...args) => mockUseDispatch(...args),
	useSelect: (cb) =>
		cb(() => ({
			getBlockRootClientId: () => 'parent-id',
			getBlock: () => ({
				clientId: 'child-id',
				name: 'designsetgo/tab',
				attributes: {},
				innerBlocks: [],
			}),
			getBlockIndex: () => 1,
		})),
}));
jest.mock('@wordpress/blocks', () => ({
	createBlock: (...args) => mockCreateBlock(...args),
	cloneBlock: (...args) => mockCloneBlock(...args),
}));
jest.mock('@wordpress/block-editor', () => ({
	BlockControls: ({ children }) => (
		<div data-testid="block-controls">{children}</div>
	),
}));
jest.mock('@wordpress/components', () => ({
	ToolbarGroup: ({ children }) => <div>{children}</div>,
	ToolbarButton: ({ label, onClick }) => (
		<button onClick={onClick}>{label}</button>
	),
}));

import { DsgoChildToolbar } from '../../../../src/components/shared/DsgoChildToolbar';

describe('DsgoChildToolbar', () => {
	beforeEach(() => {
		mockInsertBlock.mockClear();
		mockRemoveBlock.mockClear();
		mockMoveBlocksUp.mockClear();
		mockMoveBlocksDown.mockClear();
		mockReplaceBlock.mockClear();
		mockCreateBlock.mockClear();
		mockCloneBlock.mockClear();
	});

	test('renders inside BlockControls slot', () => {
		render(
			<DsgoChildToolbar
				clientId="child-id"
				childBlockName="designsetgo/tab"
			/>
		);
		expect(screen.getByTestId('block-controls')).toBeInTheDocument();
	});

	test('Add button inserts a new sibling at index+1', () => {
		render(
			<DsgoChildToolbar
				clientId="child-id"
				childBlockName="designsetgo/tab"
			/>
		);
		fireEvent.click(screen.getByRole('button', { name: /Add/i }));
		expect(mockCreateBlock).toHaveBeenCalledWith('designsetgo/tab', {});
		expect(mockInsertBlock).toHaveBeenCalledWith(
			expect.objectContaining({ name: 'designsetgo/tab' }),
			2,
			'parent-id',
			false
		);
	});

	test('Duplicate button clones the current block at index+1', () => {
		render(
			<DsgoChildToolbar
				clientId="child-id"
				childBlockName="designsetgo/tab"
			/>
		);
		fireEvent.click(screen.getByRole('button', { name: /Duplicate/i }));
		expect(mockCloneBlock).toHaveBeenCalled();
		expect(mockInsertBlock).toHaveBeenCalledWith(
			expect.objectContaining({ clientId: 'cloned' }),
			2,
			'parent-id',
			false
		);
	});

	test('Move Up calls moveBlocksUp with the clientId', () => {
		render(
			<DsgoChildToolbar
				clientId="child-id"
				childBlockName="designsetgo/tab"
			/>
		);
		fireEvent.click(screen.getByRole('button', { name: /Move up/i }));
		expect(mockMoveBlocksUp).toHaveBeenCalledWith(
			['child-id'],
			'parent-id'
		);
	});

	test('Move Down calls moveBlocksDown with the clientId', () => {
		render(
			<DsgoChildToolbar
				clientId="child-id"
				childBlockName="designsetgo/tab"
			/>
		);
		fireEvent.click(screen.getByRole('button', { name: /Move down/i }));
		expect(mockMoveBlocksDown).toHaveBeenCalledWith(
			['child-id'],
			'parent-id'
		);
	});

	test('Remove button calls removeBlock with the clientId', () => {
		render(
			<DsgoChildToolbar
				clientId="child-id"
				childBlockName="designsetgo/tab"
			/>
		);
		fireEvent.click(screen.getByRole('button', { name: /Remove/i }));
		expect(mockRemoveBlock).toHaveBeenCalledWith('child-id', false);
	});
});
