/**
 * Default inner blocks template for a freshly-inserted Dynamic Query block.
 *
 * The outer Dynamic Query block is a pure container (v2.6); the items grid
 * lives inside the required designsetgo/query-results child. DEFAULT_TEMPLATE
 * scaffolds the canonical skeleton — filter, results, no-results, pagination
 * — so authors see every piece of the Dynamic Query toolkit on first insert
 * and can remove what they don't need. Variations in variations.js override
 * this for opinionated layouts (blog-index, team, etc.).
 */

// Per-item template — lives inside designsetgo/query-results.
export const RESULT_TEMPLATE = [
	[
		'designsetgo/section',
		{},
		[
			['core/post-featured-image', { isLink: true }],
			['core/post-title', { level: 3, isLink: true }],
			['core/post-excerpt'],
		],
	],
];

// Top-level template for the Dynamic Query container: the three siblings
// most authors end up wanting, plus the required query-results wrapper.
// Easy to remove a block; harder to discover one that wasn't scaffolded.
export const DEFAULT_TEMPLATE = [
	['designsetgo/query-results', { columns: 3 }, RESULT_TEMPLATE],
	['designsetgo/query-no-results'],
	['designsetgo/query-pagination', { paginationKind: 'numbered' }],
];
