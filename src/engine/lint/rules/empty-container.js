/**
 * `empty-container` (warning): a container block with no children renders
 * as dead weight — flags `designsetgo/section|row|grid|card` and
 * `core/group|columns|column` whenever `innerBlocks` is absent or empty.
 *
 * Plain ES module — no Node-only or WordPress imports.
 */

const CONTAINER_BLOCKS = new Set([
	'designsetgo/section',
	'designsetgo/row',
	'designsetgo/grid',
	'designsetgo/card',
	'core/group',
	'core/columns',
	'core/column',
]);

const rule = {
	id: 'empty-container',
	severity: 'warning',

	check(node, ctx) {
		if (!CONTAINER_BLOCKS.has(node.name)) {
			return;
		}

		const innerBlocks = node.innerBlocks;
		if (!Array.isArray(innerBlocks) || innerBlocks.length === 0) {
			ctx.report(
				`${node.name} is a container with no content.`,
				'Add innerBlocks, or remove this empty container.'
			);
		}
	},
};

export default rule;
