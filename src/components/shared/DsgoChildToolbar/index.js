/**
 * DsgoChildToolbar
 *
 * Editor-only toolbar group rendered inside `<BlockControls>` for parent
 * blocks that manage an ordered list of children (tabs, slides,
 * accordion-items, marquee rows, etc.). Provides the canonical Add /
 * Duplicate / Move / Remove controls backed by core/block-editor, so
 * individual parent blocks don't reinvent the dispatch plumbing or the
 * icon/label vocabulary.
 *
 * Usage:
 *
 *   <BlockControls>
 *     <DsgoChildToolbar
 *       parentClientId={clientId}
 *       childBlockName="designsetgo/tab"
 *       activeIndex={activeTab}
 *       onActiveIndexChange={(i) => setAttributes({ activeTab: i })}
 *       addLabel={__('Add tab', 'designsetgo')}
 *     />
 *   </BlockControls>
 *
 * Keeping the canvas clean is the point — use this in place of inline
 * `+`/copy/trash buttons on the canvas. Canvas-led blocks (tab and slide
 * pickers where position is visually meaningful) may still keep a single
 * hover-only inline `+`, but destructive and order controls belong here.
 */

import { __ } from '@wordpress/i18n';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock, cloneBlock } from '@wordpress/blocks';
import { plus, copy, trash, chevronLeft, chevronRight } from '@wordpress/icons';

/**
 * @typedef {Object} DsgoChildToolbarProps
 * @property {string}   parentClientId             Client ID of the parent block whose children we act on.
 * @property {string}   childBlockName             Block name to insert when the user clicks Add.
 * @property {number}   [activeIndex]              Index of the currently active child (optional).
 *                                                 When provided, Duplicate/Move/Remove target this child.
 * @property {Function} [onActiveIndexChange]      Called with a new index after Add/Duplicate/Move/Remove.
 * @property {Object}   [childAttributes]          Attributes passed to `createBlock` for new children.
 * @property {Array}    [childInnerBlocks]         Inner blocks passed to `createBlock` for new children.
 * @property {string}   [addLabel]                 Localized label for the Add button (default: "Add item").
 * @property {string}   [duplicateLabel]           Localized label for Duplicate.
 * @property {string}   [removeLabel]              Localized label for Remove.
 * @property {string}   [movePrevLabel]            Localized label for Move Previous.
 * @property {string}   [moveNextLabel]            Localized label for Move Next.
 * @property {boolean}  [showMove=true]            Whether to render Move Prev/Next buttons.
 * @property {string}   [orientation='horizontal'] 'horizontal' shows left/right arrows;
 *                                                 'vertical' shows up/down (still chevronLeft/Right icons
 *                                                 because WP icons only ships left/right; the semantic
 *                                                 label is what matters).
 */

/**
 * @param {DsgoChildToolbarProps} props
 */
export default function DsgoChildToolbar({
	parentClientId,
	childBlockName,
	activeIndex,
	onActiveIndexChange,
	childAttributes = {},
	childInnerBlocks,
	addLabel,
	duplicateLabel,
	removeLabel,
	movePrevLabel,
	moveNextLabel,
	showMove = true,
	orientation = 'horizontal',
}) {
	const { childCount, activeChild } = useSelect(
		(select) => {
			const { getBlock } = select(blockEditorStore);
			const parent = getBlock(parentClientId);
			const inner = parent?.innerBlocks || [];
			const resolvedIndex =
				typeof activeIndex === 'number'
					? Math.max(0, Math.min(inner.length - 1, activeIndex))
					: -1;
			return {
				childCount: inner.length,
				activeChild: inner[resolvedIndex] || null,
			};
		},
		[parentClientId, activeIndex]
	);

	const { insertBlock, removeBlock, moveBlocksDown, moveBlocksUp } =
		useDispatch(blockEditorStore);

	const targetIndex =
		typeof activeIndex === 'number'
			? Math.max(0, Math.min(childCount - 1, activeIndex))
			: -1;

	const handleAdd = () => {
		const block = createBlock(
			childBlockName,
			childAttributes,
			childInnerBlocks
		);
		const insertionIndex = targetIndex >= 0 ? targetIndex + 1 : childCount;
		// updateSelection: false — keep parent selected so the toolbar stays
		// anchored to the parent block instead of jumping to the new child.
		insertBlock(block, insertionIndex, parentClientId, false);
		if (typeof onActiveIndexChange === 'function') {
			onActiveIndexChange(insertionIndex);
		}
	};

	const handleDuplicate = () => {
		if (!activeChild) {
			return;
		}
		// cloneBlock produces a deep clone with fresh clientIds. Callers that
		// store a derived per-block unique id (see `useUniqueBlockId`) should
		// reset it via child onMount so the clone regenerates its own.
		const clone = cloneBlock(activeChild);
		insertBlock(clone, targetIndex + 1, parentClientId, false);
		if (typeof onActiveIndexChange === 'function') {
			onActiveIndexChange(targetIndex + 1);
		}
	};

	const handleRemove = () => {
		if (!activeChild || childCount <= 1) {
			return;
		}
		removeBlock(activeChild.clientId, false);
		if (typeof onActiveIndexChange === 'function') {
			onActiveIndexChange(Math.max(0, targetIndex - 1));
		}
	};

	const handleMovePrev = () => {
		if (!activeChild || targetIndex <= 0) {
			return;
		}
		moveBlocksUp([activeChild.clientId], parentClientId);
		if (typeof onActiveIndexChange === 'function') {
			onActiveIndexChange(targetIndex - 1);
		}
	};

	const handleMoveNext = () => {
		if (!activeChild || targetIndex >= childCount - 1) {
			return;
		}
		moveBlocksDown([activeChild.clientId], parentClientId);
		if (typeof onActiveIndexChange === 'function') {
			onActiveIndexChange(targetIndex + 1);
		}
	};

	const hasTarget = activeChild !== null;
	const isFirst = targetIndex <= 0;
	const isLast = targetIndex >= childCount - 1;
	const isOnly = childCount <= 1;

	const resolvedAddLabel = addLabel || __('Add item', 'designsetgo');
	const resolvedDuplicateLabel =
		duplicateLabel || __('Duplicate item', 'designsetgo');
	const resolvedRemoveLabel = removeLabel || __('Remove item', 'designsetgo');
	const resolvedMovePrevLabel =
		movePrevLabel ||
		(orientation === 'vertical'
			? __('Move up', 'designsetgo')
			: __('Move left', 'designsetgo'));
	const resolvedMoveNextLabel =
		moveNextLabel ||
		(orientation === 'vertical'
			? __('Move down', 'designsetgo')
			: __('Move right', 'designsetgo'));

	return (
		<ToolbarGroup>
			<ToolbarButton
				icon={plus}
				label={resolvedAddLabel}
				onClick={handleAdd}
				showTooltip
			/>
			{hasTarget && (
				<ToolbarButton
					icon={copy}
					label={resolvedDuplicateLabel}
					onClick={handleDuplicate}
					showTooltip
				/>
			)}
			{hasTarget && showMove && (
				<>
					<ToolbarButton
						icon={chevronLeft}
						label={resolvedMovePrevLabel}
						onClick={handleMovePrev}
						disabled={isFirst}
						showTooltip
					/>
					<ToolbarButton
						icon={chevronRight}
						label={resolvedMoveNextLabel}
						onClick={handleMoveNext}
						disabled={isLast}
						showTooltip
					/>
				</>
			)}
			{hasTarget && (
				<ToolbarButton
					icon={trash}
					label={resolvedRemoveLabel}
					onClick={handleRemove}
					disabled={isOnly}
					isDestructive
					showTooltip
				/>
			)}
		</ToolbarGroup>
	);
}
