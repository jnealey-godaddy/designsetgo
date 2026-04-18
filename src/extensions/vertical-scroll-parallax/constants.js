/**
 * Vertical Scroll Parallax - Constants
 *
 * Configuration and default settings for vertical scroll parallax effects
 *
 * @package
 * @since 1.0.0
 */

/**
 * Default parallax settings
 */
export const DEFAULT_PARALLAX_SETTINGS = {
	enabled: false,
	direction: 'up',
	speed: 5,
	viewportStart: 0,
	viewportEnd: 100,
	relativeTo: 'viewport',
	enableDesktop: true,
	enableTablet: true,
	enableMobile: false,
	rotateEnabled: false,
	rotateDirection: 'cw',
	rotateSpeed: 3,
};

/**
 * Direction values for parallax movement
 */
export const DIRECTION_VALUES = {
	UP: 'up',
	DOWN: 'down',
	LEFT: 'left',
	RIGHT: 'right',
};

/**
 * Rotation direction values
 */
export const ROTATION_DIRECTION_VALUES = {
	CW: 'cw',
	CCW: 'ccw',
};

/**
 * Reference point values for parallax calculation
 */
export const RELATIVE_TO_VALUES = {
	VIEWPORT: 'viewport',
	PAGE: 'page',
};

/**
 * Blocks that support vertical scroll parallax
 * Container and visual blocks only - excludes text-heavy blocks
 */
export const ALLOWED_BLOCKS = [
	// Core WordPress blocks
	'core/group',
	'core/cover',
	'core/image',
	'core/media-text',
	'core/columns',
	'core/column',
	// DesignSetGo container blocks
	'designsetgo/section',
	'designsetgo/row',
	'designsetgo/grid',
	// DesignSetGo visual blocks
	'designsetgo/flip-card',
	'designsetgo/flip-card-front',
	'designsetgo/flip-card-back',
	'designsetgo/icon',
	'designsetgo/icon-button',
	'designsetgo/image-accordion',
	'designsetgo/image-accordion-item',
	'designsetgo/scroll-accordion',
	'designsetgo/scroll-accordion-item',
];

/**
 * Device breakpoints (matches WordPress/theme defaults)
 */
export const BREAKPOINTS = {
	desktop: 1024,
	tablet: 768,
};
