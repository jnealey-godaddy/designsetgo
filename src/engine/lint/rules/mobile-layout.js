/**
 * `mobile-layout` (warning): flags DesignSetGo layout blocks configured in
 * a way that won't adapt to small screens. `core/columns` is out of scope
 * (handled by `prefer-dsgo-layout`).
 *
 * Attribute names/defaults, verbatim from block.json (trees omit
 * default-valued attributes, so an absent attribute is treated as its
 * block.json default):
 *
 *  - `src/blocks/grid/block.json`: `desktopColumns` (default 3),
 *    `tabletColumns` (default 2), `mobileColumns` (default 1). Flags when
 *    effective `desktopColumns >= 3 AND mobileColumns >= 3` — the defaults
 *    alone never trigger this (mobileColumns defaults to 1), so only an
 *    explicit high mobile override is a mistake.
 *  - `src/blocks/row/block.json`: `mobileStack` (default `false`), and the
 *    standard WP layout attribute `attributes.layout.flexWrap` (default
 *    `"nowrap"`, per `supports.layout.default.flexWrap` — row's
 *    `edit.js`/`save.js` both fall back to `'nowrap'` when
 *    `layout?.flexWrap` is unset, confirming this is the effective
 *    default). Flags a row with 3+ `innerBlocks`, effective `mobileStack`
 *    false, and effective `flexWrap` `"nowrap"`.
 *
 * Plain ES module — no Node-only or WordPress imports.
 */

const GRID_DESKTOP_COLUMNS_DEFAULT = 3;
const GRID_MOBILE_COLUMNS_DEFAULT = 1;
const ROW_MOBILE_STACK_DEFAULT = false;
const ROW_FLEX_WRAP_DEFAULT = 'nowrap';
const MIN_FLAGGED_COLUMNS = 3;
const MIN_FLAGGED_ROW_CHILDREN = 3;

/**
 * Check a `designsetgo/grid` node for a mobile-column mistake.
 *
 * @param {Object} node Block node.
 * @param {Object} ctx  Rule context (`check` shape).
 */
function checkGrid(node, ctx) {
	const attributes = node.attributes || {};
	const desktopColumns =
		typeof attributes.desktopColumns === 'number'
			? attributes.desktopColumns
			: GRID_DESKTOP_COLUMNS_DEFAULT;
	const mobileColumns =
		typeof attributes.mobileColumns === 'number'
			? attributes.mobileColumns
			: GRID_MOBILE_COLUMNS_DEFAULT;

	if (
		desktopColumns >= MIN_FLAGGED_COLUMNS &&
		mobileColumns >= MIN_FLAGGED_COLUMNS
	) {
		ctx.report(
			`designsetgo/grid has ${mobileColumns} mobile columns, which won't fit small screens.`,
			'Lower mobileColumns (e.g. to 1) so the grid stacks on mobile.'
		);
	}
}

/**
 * Check a `designsetgo/row` node for a mobile-stacking mistake.
 *
 * @param {Object} node Block node.
 * @param {Object} ctx  Rule context (`check` shape).
 */
function checkRow(node, ctx) {
	const attributes = node.attributes || {};
	const childCount = Array.isArray(node.innerBlocks)
		? node.innerBlocks.length
		: 0;
	const mobileStack =
		typeof attributes.mobileStack === 'boolean'
			? attributes.mobileStack
			: ROW_MOBILE_STACK_DEFAULT;
	const flexWrap = attributes.layout?.flexWrap || ROW_FLEX_WRAP_DEFAULT;

	if (
		childCount >= MIN_FLAGGED_ROW_CHILDREN &&
		mobileStack === false &&
		flexWrap === 'nowrap'
	) {
		ctx.report(
			`designsetgo/row has ${childCount} items that won't wrap or stack on mobile.`,
			'Set mobileStack to true, or allow the row to wrap, so it adapts on small screens.'
		);
	}
}

const rule = {
	id: 'mobile-layout',
	severity: 'warning',

	check(node, ctx) {
		if (node.name === 'designsetgo/grid') {
			checkGrid(node, ctx);
			return;
		}

		if (node.name === 'designsetgo/row') {
			checkRow(node, ctx);
		}
	},
};

export default rule;
