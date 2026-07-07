/**
 * SVG Patterns Extension - Constants
 *
 * @package
 */

/**
 * Blocks that support the SVG pattern background
 */
export const SUPPORTED_BLOCKS = ['core/group', 'designsetgo/section'];

/**
 * Default attribute values
 */
export const DEFAULTS = {
	enabled: false,
	pattern: '',
	color: '#9c92ac',
	opacity: 0.4,
	scale: 1,
	fixed: false,
};

/**
 * Control ranges
 */
export const RANGES = {
	opacity: {
		min: 0.05,
		max: 1,
		step: 0.05,
	},
	scale: {
		min: 0.25,
		max: 4,
		step: 0.25,
	},
};

/**
 * Sentinel value for dsgoSvgPatternType meaning "inherit the theme's
 * SVG pattern preset from settings.custom.designsetgo.svgPattern".
 */
export const INHERIT = 'inherit';

/**
 * In-plugin fallback preset used when a block inherits but the theme
 * (Style Kit) sets nothing. Each field falls back independently.
 */
export const INHERIT_FALLBACK = {
	type: 'dot-grid',
	color: DEFAULTS.color,
	opacity: DEFAULTS.opacity,
	scale: DEFAULTS.scale,
};
