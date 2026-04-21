/**
 * DsgoChildToolbar — Unit Tests
 *
 * Verifies that the shared Theme 5 toolbar wires Add/Duplicate/Move/Remove
 * to the core/block-editor store correctly and exposes disabled states
 * at list boundaries (first/last/only).
 */

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({
	__: (text) => text,
}));

jest.mock('@wordpress/data', () => ({
	useSelect: jest.fn(),
	useDispatch: jest.fn(),
}));

jest.mock('@wordpress/block-editor', () => ({
	store: 'core/block-editor',
}));

jest.mock('@wordpress/blocks', () => ({
	createBlock: jest.fn((name, attrs, inner) => ({
		name,
		attributes: attrs,
		innerBlocks: inner,
		clientId: 'new-client-id',
	})),
	cloneBlock: jest.fn((block) => ({
		...block,
		clientId: `clone-of-${block.clientId}`,
	})),
}));

jest.mock('@wordpress/components', () => ({
	ToolbarGroup: ({ children }) => <div role="toolbar">{children}</div>,
	/* eslint-disable react/button-has-type, no-unused-vars */
	ToolbarButton: ({
		icon,
		label,
		onClick,
		disabled,
		showTooltip,
		isDestructive,
		...rest
	}) => (
		<button
			onClick={onClick}
			disabled={disabled}
			aria-label={label}
			data-destructive={isDestructive ? 'true' : undefined}
			{...rest}
		>
			{label}
		</button>
	),
	/* eslint-enable react/button-has-type, no-unused-vars */
}));

jest.mock('@wordpress/icons', () => ({
	plus: 'plus-icon',
	copy: 'copy-icon',
	trash: 'trash-icon',
	chevronLeft: 'chevron-left-icon',
	chevronRight: 'chevron-right-icon',
	chevronUp: 'chevron-up-icon',
	chevronDown: 'chevron-down-icon',
}));

import DsgoChildToolbar from '../../../src/components/shared/DsgoChildToolbar';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock, cloneBlock } from '@wordpress/blocks';

function makeChildren(count) {
	return Array.from({ length: count }, (_, i) => ({
		clientId: `child-${i}`,
		name: 'designsetgo/tab',
		innerBlocks: [],
		attributes: {},
	}));
}

function setupSelect(children, activeIndex) {
	useSelect.mockImplementation((fn) =>
		fn(() => ({
			getBlock: () => ({ innerBlocks: children }),
		}))
	);
	return { children, activeIndex };
}

describe('DsgoChildToolbar', () => {
	let insertBlock;
	let removeBlock;
	let moveBlocksUp;
	let moveBlocksDown;

	beforeEach(() => {
		jest.clearAllMocks();
		insertBlock = jest.fn();
		removeBlock = jest.fn();
		moveBlocksUp = jest.fn();
		moveBlocksDown = jest.fn();
		useDispatch.mockReturnValue({
			insertBlock,
			removeBlock,
			moveBlocksUp,
			moveBlocksDown,
		});
	});

	test('renders add-only when no active child', () => {
		setupSelect(makeChildren(2));
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
			/>
		);
		expect(screen.getByLabelText('Add item')).toBeInTheDocument();
		expect(
			screen.queryByLabelText('Duplicate item')
		).not.toBeInTheDocument();
		expect(screen.queryByLabelText('Remove item')).not.toBeInTheDocument();
	});

	test('Add inserts a new block at activeIndex + 1 and bumps index', async () => {
		setupSelect(makeChildren(3));
		const onActiveIndexChange = jest.fn();
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={1}
				onActiveIndexChange={onActiveIndexChange}
				childAttributes={{ title: 'New' }}
			/>
		);

		await userEvent.click(screen.getByLabelText('Add item'));

		expect(createBlock).toHaveBeenCalledWith(
			'designsetgo/tab',
			{ title: 'New' },
			undefined
		);
		expect(insertBlock).toHaveBeenCalledWith(
			expect.objectContaining({ name: 'designsetgo/tab' }),
			2,
			'parent',
			false
		);
		expect(onActiveIndexChange).toHaveBeenCalledWith(2, 'new-client-id');
	});

	test('Duplicate clones active child and inserts after it', async () => {
		setupSelect(makeChildren(3));
		const onActiveIndexChange = jest.fn();
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={1}
				onActiveIndexChange={onActiveIndexChange}
			/>
		);

		await userEvent.click(screen.getByLabelText('Duplicate item'));

		expect(cloneBlock).toHaveBeenCalledWith(
			expect.objectContaining({ clientId: 'child-1' }),
			undefined
		);
		expect(insertBlock).toHaveBeenCalledWith(
			expect.objectContaining({ clientId: 'clone-of-child-1' }),
			2,
			'parent',
			false
		);
		expect(onActiveIndexChange).toHaveBeenCalledWith(2, 'clone-of-child-1');
	});

	test('Duplicate forwards cloneAttributeOverrides to cloneBlock', async () => {
		// Parents with per-block unique IDs (Tabs' uniqueId, accordion panel
		// ids, etc.) rely on the override to wipe stale ids so the clone
		// regenerates its own ARIA wiring on mount.
		setupSelect(makeChildren(3));
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={1}
				cloneAttributeOverrides={{ uniqueId: '' }}
			/>
		);

		await userEvent.click(screen.getByLabelText('Duplicate item'));

		expect(cloneBlock).toHaveBeenCalledWith(
			expect.objectContaining({ clientId: 'child-1' }),
			{ uniqueId: '' }
		);
	});

	test('Remove dispatches removeBlock and decrements active index', async () => {
		setupSelect(makeChildren(3));
		const onActiveIndexChange = jest.fn();
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={2}
				onActiveIndexChange={onActiveIndexChange}
			/>
		);

		await userEvent.click(screen.getByLabelText('Remove item'));

		expect(removeBlock).toHaveBeenCalledWith('child-2', false);
		expect(onActiveIndexChange).toHaveBeenCalledWith(1, null);
	});

	test('disable flags prevent the toolbar actions from dispatching', async () => {
		setupSelect(makeChildren(3));
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={1}
				disableAdd
				disableDuplicate
				disableRemove
				disableMove
			/>
		);

		expect(screen.getByLabelText('Add item')).toBeDisabled();
		expect(screen.getByLabelText('Duplicate item')).toBeDisabled();
		expect(screen.getByLabelText('Remove item')).toBeDisabled();
		expect(screen.getByLabelText('Move left')).toBeDisabled();
		expect(screen.getByLabelText('Move right')).toBeDisabled();

		await userEvent.click(screen.getByLabelText('Add item'));
		await userEvent.click(screen.getByLabelText('Duplicate item'));
		await userEvent.click(screen.getByLabelText('Remove item'));

		expect(insertBlock).not.toHaveBeenCalled();
		expect(removeBlock).not.toHaveBeenCalled();
	});

	test('Remove is disabled when only one child remains', () => {
		setupSelect(makeChildren(1));
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={0}
			/>
		);
		expect(screen.getByLabelText('Remove item')).toBeDisabled();
	});

	test('MovePrev is disabled at first index', () => {
		setupSelect(makeChildren(3));
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={0}
			/>
		);
		expect(screen.getByLabelText('Move left')).toBeDisabled();
	});

	test('MoveNext is disabled at last index', () => {
		setupSelect(makeChildren(3));
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={2}
			/>
		);
		expect(screen.getByLabelText('Move right')).toBeDisabled();
	});

	test('MovePrev dispatches moveBlocksUp and decrements index', async () => {
		setupSelect(makeChildren(3));
		const onActiveIndexChange = jest.fn();
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={2}
				onActiveIndexChange={onActiveIndexChange}
			/>
		);

		await userEvent.click(screen.getByLabelText('Move left'));

		expect(moveBlocksUp).toHaveBeenCalledWith(['child-2'], 'parent');
		expect(onActiveIndexChange).toHaveBeenCalledWith(1, 'child-2');
	});

	test('MoveNext dispatches moveBlocksDown and increments index', async () => {
		setupSelect(makeChildren(3));
		const onActiveIndexChange = jest.fn();
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={0}
				onActiveIndexChange={onActiveIndexChange}
			/>
		);

		await userEvent.click(screen.getByLabelText('Move right'));

		expect(moveBlocksDown).toHaveBeenCalledWith(['child-0'], 'parent');
		expect(onActiveIndexChange).toHaveBeenCalledWith(1, 'child-0');
	});

	test('custom labels override defaults', () => {
		setupSelect(makeChildren(2));
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={0}
				addLabel="Add tab"
				duplicateLabel="Duplicate tab"
				removeLabel="Remove tab"
				movePrevLabel="Move tab left"
				moveNextLabel="Move tab right"
			/>
		);

		expect(screen.getByLabelText('Add tab')).toBeInTheDocument();
		expect(screen.getByLabelText('Duplicate tab')).toBeInTheDocument();
		expect(screen.getByLabelText('Remove tab')).toBeInTheDocument();
		expect(screen.getByLabelText('Move tab left')).toBeInTheDocument();
		expect(screen.getByLabelText('Move tab right')).toBeInTheDocument();
	});

	test('Add inserts at index 0 when there are no children (activeIndex ignored)', async () => {
		setupSelect(makeChildren(0));
		const onActiveIndexChange = jest.fn();
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={0}
				onActiveIndexChange={onActiveIndexChange}
			/>
		);

		await userEvent.click(screen.getByLabelText('Add item'));

		expect(insertBlock).toHaveBeenCalledWith(
			expect.any(Object),
			0,
			'parent',
			false
		);
		expect(onActiveIndexChange).toHaveBeenCalledWith(0, 'new-client-id');
		// With no children there is no active target, so no duplicate/remove.
		expect(
			screen.queryByLabelText('Duplicate item')
		).not.toBeInTheDocument();
		expect(screen.queryByLabelText('Remove item')).not.toBeInTheDocument();
	});

	test('vertical orientation renders chevronUp/Down and up/down labels', () => {
		setupSelect(makeChildren(3));
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={1}
				orientation="vertical"
			/>
		);
		expect(screen.getByLabelText('Move up')).toBeInTheDocument();
		expect(screen.getByLabelText('Move down')).toBeInTheDocument();
		expect(screen.queryByLabelText('Move left')).not.toBeInTheDocument();
		expect(screen.queryByLabelText('Move right')).not.toBeInTheDocument();
	});

	test('showMove=false hides move buttons', () => {
		setupSelect(makeChildren(3));
		render(
			<DsgoChildToolbar
				parentClientId="parent"
				childBlockName="designsetgo/tab"
				activeIndex={1}
				showMove={false}
			/>
		);

		expect(screen.queryByLabelText('Move left')).not.toBeInTheDocument();
		expect(screen.queryByLabelText('Move right')).not.toBeInTheDocument();
	});
});
