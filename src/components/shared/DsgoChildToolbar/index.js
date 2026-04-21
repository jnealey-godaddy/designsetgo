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
import {
	plus,
	copy,
	trash,
	chevronLeft,
	chevronRight,
	chevronUp,
	chevronDown,
} from '@wordpress/icons';

/**
 * @typedef {Object} DsgoChildToolbarProps
 * @property {string}   parentClientId             Client ID of the parent block whose children we act on.
 * @property {string}   childBlockName             Block name to insert when the user clicks Add.
 * @property {number}   [activeIndex]              Index of the currently active child (optional).
 *                                                 When provided, Duplicate/Move/Remove target this child.
 * @property {Function} [onActiveIndexChange]      Called with `(newIndex, newChildClientId)` after
 *                                                 Add/Duplicate/Move. Remove passes `null` for the
 *                                                 clientId. Callers should prefer the clientId when
 *                                                 reselecting — a stale `innerBlocks` closure
 *                                                 captured above this component won't include a
 *                                                 freshly-inserted child until the next render.
 * @property {Object}   [childAttributes]          Attributes passed to `createBlock` for new children.
 * @property {Array}    [childInnerBlocks]         Inner blocks passed to `createBlock` for new children.
 * @property {Object}   [cloneAttributeOverrides]  Attributes merged into the duplicated block. Use
 *                                                 this to clear per-block unique IDs (e.g.
 *                                                 `{ uniqueId: '' }`) so the child's mount effect
 *                                                 regenerates its ARIA wiring instead of inheriting
 *                                                 the source's id.
 * @property {string}   [addLabel]                 Localized label for the Add button (default: "Add item").
 * @property {string}   [duplicateLabel]           Localized label for Duplicate.
 * @property {string}   [removeLabel]              Localized label for Remove.
 * @property {string}   [movePrevLabel]            Localized label for Move Previous.
 * @property {string}   [moveNextLabel]            Localized label for Move Next.
 * @property {boolean}  [disableAdd=false]         Disables the Add button.
 * @property {boolean}  [disableDuplicate=false]   Disables the Duplicate button.
 * @property {boolean}  [disableRemove=false]      Disables the Remove button.
 * @property {boolean}  [disableMove=false]        Disables both Move buttons.
 * @property {boolean}  [showMove=true]            Whether to render Move Prev/Next buttons.
 * @property {string}   [orientation='horizontal'] 'horizontal' renders chevronLeft/Right;
 *                                                 'vertical' renders chevronUp/Down so the
 *                                                 icon matches the direction of travel.
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
	cloneAttributeOverrides,
	addLabel,
	duplicateLabel,
	removeLabel,
	movePrevLabel,
	moveNextLabel,
	disableAdd = false,
	disableDuplicate = false,
	disableRemove = false,
	disableMove = false,
	showMove = true,
	orientation = 'horizontal',
}) {
	const { childCount, activeChild, targetIndex } = useSelect(
		(select) => {
			const { getBlock } = select(blockEditorStore);
			const parent = getBlock(parentClientId);
			const inner = parent?.innerBlocks || [];
			// With no children there is no valid "active" index to clamp to;
			// return -1 so Add appends at 0 and Duplicate/Remove/Move are
			// hidden (no activeChild).
			const resolvedIndex =
				inner.length > 0 && typeof activeIndex === 'number'
					? Math.max(0, Math.min(inner.length - 1, activeIndex))
					: -1;
			return {
				childCount: inner.length,
				activeChild: inner[resolvedIndex] || null,
				targetIndex: resolvedIndex,
			};
		},
		[parentClientId, activeIndex]
	);

	const { insertBlock, removeBlock, moveBlocksDown, moveBlocksUp } =
		useDispatch(blockEditorStore);

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
			onActiveIndexChange(insertionIndex, block.clientId);
		}
	};

	const handleDuplicate = () => {
		if (!activeChild) {
			return;
		}
		// cloneBlock produces a deep clone with fresh clientIds. The
		// cloneAttributeOverrides escape hatch lets callers clear per-block
		// unique ids (e.g. Tabs' `uniqueId` powers the tab/panel ARIA wiring
		// — without resetting it, the clone would duplicate the source's id).
		const clone = cloneBlock(activeChild, cloneAttributeOverrides);
		insertBlock(clone, targetIndex + 1, parentClientId, false);
		if (typeof onActiveIndexChange === 'function') {
			onActiveIndexChange(targetIndex + 1, clone.clientId);
		}
	};

	const handleRemove = () => {
		if (!activeChild || childCount <= 1) {
			return;
		}
		removeBlock(activeChild.clientId, false);
		if (typeof onActiveIndexChange === 'function') {
			onActiveIndexChange(Math.max(0, targetIndex - 1), null);
		}
	};

	const handleMovePrev = () => {
		if (!activeChild || targetIndex <= 0) {
			return;
		}
		moveBlocksUp([activeChild.clientId], parentClientId);
		if (typeof onActiveIndexChange === 'function') {
			onActiveIndexChange(targetIndex - 1, activeChild.clientId);
		}
	};

	const handleMoveNext = () => {
		if (!activeChild || targetIndex >= childCount - 1) {
			return;
		}
		moveBlocksDown([activeChild.clientId], parentClientId);
		if (typeof onActiveIndexChange === 'function') {
			onActiveIndexChange(targetIndex + 1, activeChild.clientId);
		}
	};

	const hasTarget = activeChild !== null;
	const isFirst = targetIndex <= 0;
	const isLast = targetIndex >= childCount - 1;
	const isOnly = childCount <= 1;
	const isVertical = orientation === 'vertical';
	const movePrevIcon = isVertical ? chevronUp : chevronLeft;
	const moveNextIcon = isVertical ? chevronDown : chevronRight;

	const resolvedAddLabel = addLabel || __('Add item', 'designsetgo');
	const resolvedDuplicateLabel =
		duplicateLabel || __('Duplicate item', 'designsetgo');
	const resolvedRemoveLabel = removeLabel || __('Remove item', 'designsetgo');
	const resolvedMovePrevLabel =
		movePrevLabel ||
		(isVertical
			? __('Move up', 'designsetgo')
			: __('Move left', 'designsetgo'));
	const resolvedMoveNextLabel =
		moveNextLabel ||
		(isVertical
			? __('Move down', 'designsetgo')
			: __('Move right', 'designsetgo'));

	return (
		<ToolbarGroup label={__('Child block actions', 'designsetgo')}>
			<ToolbarButton
				icon={plus}
				label={resolvedAddLabel}
				onClick={handleAdd}
				disabled={disableAdd}
				showTooltip
			/>
			{hasTarget && (
				<ToolbarButton
					icon={copy}
					label={resolvedDuplicateLabel}
					onClick={handleDuplicate}
					disabled={disableDuplicate}
					showTooltip
				/>
			)}
			{hasTarget && showMove && (
				<>
					<ToolbarButton
						icon={movePrevIcon}
						label={resolvedMovePrevLabel}
						onClick={handleMovePrev}
						disabled={disableMove || isFirst}
						showTooltip
					/>
					<ToolbarButton
						icon={moveNextIcon}
						label={resolvedMoveNextLabel}
						onClick={handleMoveNext}
						disabled={disableMove || isLast}
						showTooltip
					/>
				</>
			)}
			{hasTarget && (
				<ToolbarButton
					icon={trash}
					label={resolvedRemoveLabel}
					onClick={handleRemove}
					disabled={disableRemove || isOnly}
					isDestructive
					showTooltip
				/>
			)}
		</ToolbarGroup>
	);
}
