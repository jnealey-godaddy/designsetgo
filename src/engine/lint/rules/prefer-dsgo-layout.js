/**
 * `prefer-dsgo-layout` (warning): steers agents toward DesignSetGo's own
 * layout blocks instead of core equivalents that duplicate them:
 *
 *  - `core/columns` — always; `designsetgo/grid` is the responsive
 *    multi-column replacement.
 *  - `core/group` with `attributes.layout.type === 'flex'` — replace with
 *    `designsetgo/row`.
 *  - `core/group` with `attributes.layout.type === 'grid'` — replace with
 *    `designsetgo/grid`.
 *  - Any other top-level `core/group` — replace with `designsetgo/section`,
 *    the project's convention for a top-level page section.
 *
 * Each `core/group` node is checked against at most one of the three
 * `core/group` conditions above (flex/grid layout takes priority over the
 * top-level check), so a single node never produces more than one finding.
 *
 * Plain ES module — no Node-only or WordPress imports.
 */

const rule = {
	id: 'prefer-dsgo-layout',
	severity: 'warning',

	check(node, ctx) {
		if (node.name === 'core/columns') {
			ctx.report(
				'core/columns is a core layout block; DesignSetGo provides a responsive equivalent.',
				'Use designsetgo/grid instead of core/columns.'
			);
			return;
		}

		if (node.name !== 'core/group') {
			return;
		}

		const layoutType = node.attributes?.layout?.type;

		if (layoutType === 'flex') {
			ctx.report(
				'core/group with a flex layout duplicates a DesignSetGo block.',
				'Use designsetgo/row instead of core/group with a flex layout.'
			);
			return;
		}

		if (layoutType === 'grid') {
			ctx.report(
				'core/group with a grid layout duplicates a DesignSetGo block.',
				'Use designsetgo/grid instead of core/group with a grid layout.'
			);
			return;
		}

		if (ctx.parent === null) {
			ctx.report(
				'A top-level core/group should be a page section.',
				'Use designsetgo/section instead of a top-level core/group.'
			);
		}
	},
};

export default rule;
