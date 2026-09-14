/**
 * `top-level-sections` (warning, page-level): flags the anti-pattern where
 * an agent wraps an entire page in a single top-level container instead of
 * making each section its own top-level block (the project's documented
 * convention — see CLAUDE.md's "Sections are top-level blocks").
 *
 * Flags when the tree has exactly one top-level block, that block is a
 * container (`core/group` or `designsetgo/section`), and it holds 2 or
 * more container children (same two names). The finding is attached to the
 * single top-level wrapper.
 *
 * Plain ES module — no Node-only or WordPress imports.
 */

const CONTAINER_BLOCKS = new Set(['core/group', 'designsetgo/section']);

const rule = {
	id: 'top-level-sections',
	severity: 'warning',

	checkTree(tree, ctx) {
		const blocks = Array.isArray(tree?.blocks) ? tree.blocks : [];

		if (blocks.length !== 1) {
			return;
		}

		const [wrapper] = blocks;
		if (!wrapper || !CONTAINER_BLOCKS.has(wrapper.name)) {
			return;
		}

		const children = Array.isArray(wrapper.innerBlocks)
			? wrapper.innerBlocks
			: [];
		const containerChildCount = children.filter(
			(child) => child && CONTAINER_BLOCKS.has(child.name)
		).length;

		if (containerChildCount >= 2) {
			ctx.report(
				'blocks[0]',
				`The page is wrapped in a single top-level ${wrapper.name} holding ${containerChildCount} sections.`,
				'Make each section its own top-level block instead of nesting them inside one wrapper.'
			);
		}
	},
};

export default rule;
