import { InnerBlocks } from '@wordpress/block-editor';

// render.php wraps innerBlocks in the configured tag (header/div/section).
// Returning an InnerBlocks.Content wrapper here would double-wrap the output.
export default function save() {
	return <InnerBlocks.Content />;
}
