/**
 * Default inner blocks template for a freshly-inserted Dynamic Query block.
 * Variations in src/blocks/query/variations.js override this for opinionated
 * card layouts (blog-index, team, etc.). A bare insert gets the generic
 * post-featured-image + linked title + excerpt trio.
 */
export const DEFAULT_TEMPLATE = [
	['core/post-featured-image', { isLink: true }],
	['core/post-title', { level: 3, isLink: true }],
	['core/post-excerpt'],
];
