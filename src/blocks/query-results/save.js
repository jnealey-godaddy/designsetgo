import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

/**
 * Dynamic block — render.php (or the parent Query block's render.php)
 * produces the frontend HTML. `save()` must emit `<InnerBlocks.Content />`
 * so WordPress persists the per-item template blocks inside the block
 * comment; returning null would discard children and leave the server
 * renderer with no template to iterate.
 */
export default function save() {
	const blockProps = useBlockProps.save();
	const innerBlocksProps = useInnerBlocksProps.save(blockProps);
	return <div {...innerBlocksProps} />;
}
