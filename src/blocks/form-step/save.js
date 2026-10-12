import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Dynamic block: render.php wraps the fields; only the inner blocks are stored.
 *
 * @return {Element} Inner blocks content.
 */
export default function save() {
	return <InnerBlocks.Content />;
}
