import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

/**
 * Dynamic block — render.php owns the frontend HTML, but `save()` must return
 * a structure containing `<InnerBlocks.Content />` so WordPress persists the
 * per-item template blocks (post-title, featured-image, etc.) inside the
 * block comment. Returning `null` causes WP to serialize the block as
 * `<!-- wp:designsetgo/query /-->` with no children, which means the frontend
 * renders empty `<li>`s.
 */
export default function save() {
	const blockProps = useBlockProps.save();
	const innerBlocksProps = useInnerBlocksProps.save(blockProps);
	return <div {...innerBlocksProps} />;
}
