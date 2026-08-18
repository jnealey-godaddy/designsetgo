/**
 * Block Animations - Constants
 *
 * Animation presets and configuration for entrance/exit animations
 *
 * @package
 * @since 1.0.0
 */

/**
 * Animation presets organized by type
 */
export const ANIMATION_TYPES = {
	entrance: [
		{ label: 'Fade In', value: 'fadeIn' },
		{ label: 'Fade In Up', value: 'fadeInUp' },
		{ label: 'Fade In Down', value: 'fadeInDown' },
		{ label: 'Fade In Left', value: 'fadeInLeft' },
		{ label: 'Fade In Right', value: 'fadeInRight' },
		{ label: 'Slide In Up', value: 'slideInUp' },
		{ label: 'Slide In Down', value: 'slideInDown' },
		{ label: 'Slide In Left', value: 'slideInLeft' },
		{ label: 'Slide In Right', value: 'slideInRight' },
		{ label: 'Zoom In', value: 'zoomIn' },
		{ label: 'Bounce In', value: 'bounceIn' },
		{ label: 'Flip In X', value: 'flipInX' },
		{ label: 'Flip In Y', value: 'flipInY' },
	],
	exit: [
		{ label: 'Fade Out', value: 'fadeOut' },
		{ label: 'Fade Out Up', value: 'fadeOutUp' },
		{ label: 'Fade Out Down', value: 'fadeOutDown' },
		{ label: 'Fade Out Left', value: 'fadeOutLeft' },
		{ label: 'Fade Out Right', value: 'fadeOutRight' },
		{ label: 'Slide Out Up', value: 'slideOutUp' },
		{ label: 'Slide Out Down', value: 'slideOutDown' },
		{ label: 'Slide Out Left', value: 'slideOutLeft' },
		{ label: 'Slide Out Right', value: 'slideOutRight' },
		{ label: 'Zoom Out', value: 'zoomOut' },
		{ label: 'Bounce Out', value: 'bounceOut' },
	],
};

/**
 * Animation triggers
 */
export const ANIMATION_TRIGGERS = [
	{ label: 'On Scroll (Enter Viewport)', value: 'scroll' },
	{ label: 'On Page Load', value: 'load' },
	{ label: 'On Hover', value: 'hover' },
	{ label: 'On Click', value: 'click' },
];

/**
 * Animation durations (in milliseconds)
 */
export const ANIMATION_DURATIONS = [
	{ label: 'Fast (300ms)', value: 300 },
	{ label: 'Normal (600ms)', value: 600 },
	{ label: 'Slow (1000ms)', value: 1000 },
	{ label: 'Very Slow (2000ms)', value: 2000 },
];

/**
 * Animation easing functions
 */
export const ANIMATION_EASINGS = [
	{ label: 'Ease', value: 'ease' },
	{ label: 'Ease In', value: 'ease-in' },
	{ label: 'Ease Out', value: 'ease-out' },
	{ label: 'Ease In Out', value: 'ease-in-out' },
	{ label: 'Linear', value: 'linear' },
	{ label: 'Bounce', value: 'cubic-bezier(0.68, -0.55, 0.265, 1.55)' },
];

/**
 * Default animation settings
 */
export const DEFAULT_ANIMATION_SETTINGS = {
	enabled: false,
	entranceAnimation: '',
	exitAnimation: '',
	trigger: 'scroll',
	duration: 600,
	delay: 0,
	easing: 'ease-out',
	offset: 100, // Pixels from viewport to trigger
	once: true, // Only animate once
	staggerEnabled: false, // Animate direct children in sequence
	staggerStep: 80, // Milliseconds between each child
	scrollLinked: false, // Scrub the entrance against scroll position
	svgDraw: false, // Draw descendant SVG strokes on entering the viewport
};
