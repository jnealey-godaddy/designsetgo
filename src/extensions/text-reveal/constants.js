/**
 * Text Reveal Extension - Constants
 *
 * Default settings and supported blocks for the text reveal effect
 *
 * @package
 * @since 1.0.0
 */

/**
 * Default text reveal settings
 */
export const DEFAULT_TEXT_REVEAL_SETTINGS = {
	enabled: false,
	revealColor: '#2563eb', // Blue (blue-600) - color text transitions to
	splitMode: 'word', // 'word' or 'character'
	effect: 'color', // 'color' sweep or 'rise' (fade + rise per unit)
	transitionDuration: 150, // ms per word/char transition
};

/**
 * Blocks that support the text reveal effect
 */
export const SUPPORTED_BLOCKS = ['core/paragraph', 'core/heading'];
