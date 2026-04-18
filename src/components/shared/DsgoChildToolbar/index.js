/**
 * DsgoChildToolbar
 *
 * Drop-in BlockControls toolbar for child blocks of compound parents
 * (tab, slide, accordion-item, etc.). Provides Add / Duplicate / Move /
 * Remove actions wired to core/block-editor.
 *
 * Theme 5 uses this to consolidate the three different "add child"
 * affordances currently scattered across the codebase (inline canvas
 * buttons in tabs/scroll-marquee, default appender in accordion, none
 * in reveal).
 *
 * Usage:
 *
 *   // inside child block edit():
 *   <DsgoChildToolbar
 *     clientId={clientId}
 *     childBlockName="designsetgo/tab"
 *     newAttributes={{ label: __('New Tab', 'designsetgo') }}
 *   />
 */
import { __ } from '@wordpress/i18n';
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock, cloneBlock } from '@wordpress/blocks';
import { plus, copy, chevronUp, chevronDown, trash } from '@wordpress/icons';

export function DsgoChildToolbar({
	clientId,
	childBlockName,
	newAttributes = {},
}) {
	const { insertBlock, removeBlock, moveBlocksUp, moveBlocksDown } =
		useDispatch('core/block-editor');

	const { rootClientId, block, index } = useSelect(
		(select) => {
			const store = select('core/block-editor');
			const root = store.getBlockRootClientId(clientId);
			return {
				rootClientId: root,
				block: store.getBlock(clientId),
				index: store.getBlockIndex(clientId),
			};
		},
		[clientId]
	);

	const onAdd = () => {
		if (rootClientId === null) {
			return;
		}
		const newBlock = createBlock(childBlockName, newAttributes);
		insertBlock(newBlock, index + 1, rootClientId, false);
	};
	const onDuplicate = () => {
		if (!block) {
			return;
		}
		insertBlock(cloneBlock(block), index + 1, rootClientId, false);
	};
	const onMoveUp = () => {
		if (rootClientId === null) {
			return;
		}
		moveBlocksUp([clientId], rootClientId);
	};
	const onMoveDown = () => {
		if (rootClientId === null) {
			return;
		}
		moveBlocksDown([clientId], rootClientId);
	};
	const onRemove = () => removeBlock(clientId, false);

	return (
		<BlockControls>
			<ToolbarGroup>
				<ToolbarButton
					icon={plus}
					label={__('Add', 'designsetgo')}
					onClick={onAdd}
				/>
				<ToolbarButton
					icon={copy}
					label={__('Duplicate', 'designsetgo')}
					onClick={onDuplicate}
				/>
				<ToolbarButton
					icon={chevronUp}
					label={__('Move up', 'designsetgo')}
					onClick={onMoveUp}
				/>
				<ToolbarButton
					icon={chevronDown}
					label={__('Move down', 'designsetgo')}
					onClick={onMoveDown}
				/>
				<ToolbarButton
					icon={trash}
					label={__('Remove', 'designsetgo')}
					onClick={onRemove}
					isDestructive
				/>
			</ToolbarGroup>
		</BlockControls>
	);
}
