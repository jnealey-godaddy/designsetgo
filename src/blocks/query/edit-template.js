/**
 * Default inner blocks template for a freshly-inserted Dynamic Query block.
 *
 * The outer Dynamic Query block is a pure container (v2.6); the items grid
 * lives inside the required designsetgo/query-results child. By default the
 * query produces a bare list with a DSGo Section wrapping the generic
 * post-featured-image + linked title + excerpt trio.
 *
 * Variations in variations.js override RESULT_TEMPLATE (used inside the
 * query-results wrapper) for opinionated card layouts.
 */

// Per-item template — lives inside designsetgo/query-results.
export const RESULT_TEMPLATE = [
	[
		'designsetgo/section',
		{},
		[
			[ 'core/post-featured-image', { isLink: true } ],
			[ 'core/post-title', { level: 3, isLink: true } ],
			[ 'core/post-excerpt' ],
		],
	],
];

// Top-level template for the Dynamic Query container: one results block
// containing RESULT_TEMPLATE. Placeholders in QueryPlaceholder inject this
// or a variation-specific structure on first insert.
export const DEFAULT_TEMPLATE = [
	[ 'designsetgo/query-results', {}, RESULT_TEMPLATE ],
];
