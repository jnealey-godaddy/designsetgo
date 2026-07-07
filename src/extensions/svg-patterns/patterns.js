/**
 * SVG Pattern Library - Utilities
 *
 * Provides validation, encoding, and generation functions for SVG patterns.
 * Pattern definitions are in pattern-data.js to keep files under 300 lines.
 *
 * @package
 */

// Re-export pattern data for consumers
export { PATTERNS, PATTERN_IDS, CATEGORIES } from './pattern-data';
import { PATTERNS } from './pattern-data';

/**
 * Validate a CSS color value to prevent SVG attribute injection.
 *
 * @param {string} color The color value to validate
 * @return {boolean} True if the color is safe
 */
export function isValidColor(color) {
	if (!color || typeof color !== 'string') {
		return false;
	}
	const validFormats = [
		/^#[0-9A-Fa-f]{3,8}$/, // Hex (#fff, #ffffff, #ffffffff)
		/^rgb\([^)]+\)$/, // rgb()
		/^rgba\([^)]+\)$/, // rgba()
		/^hsl\([^)]+\)$/, // hsl()
		/^hsla\([^)]+\)$/, // hsla()
		/^[a-z]+$/i, // Named colors
	];
	return validFormats.some((format) => format.test(color.trim()));
}

/**
 * Generate a URL-encoded SVG data URI for use in CSS background-image.
 *
 * @param {string} svg Raw SVG markup
 * @return {string} Encoded data URI
 */
export function encodeSvg(svg) {
	return `url("data:image/svg+xml,${encodeURIComponent(svg)}")`;
}

/**
 * Build SVG markup for a given pattern definition.
 * Color is sanitized to prevent SVG attribute injection.
 *
 * @param {Object} pattern Pattern definition
 * @param {string} color   Fill color (hex including #)
 * @param {number} opacity Fill opacity (0-1)
 * @return {string} Complete SVG markup
 */
export function buildPatternSvg(pattern, color, opacity) {
	const { width, height, paths } = pattern;

	// Sanitize color — reject anything that isn't a valid CSS color format
	const safeColor = isValidColor(color) ? color : '#9c92ac';

	// Clamp opacity to valid range
	const safeOpacity = Math.max(0, Math.min(1, Number(opacity) || 0.4));

	const pathElements = paths
		.map((p) => {
			const attrs = [];
			// SECURITY: Path d values are static data from pattern-data.js.
			// Never use user input here.
			attrs.push(`d="${p.d}"`);

			if (p.stroke) {
				// Stroke-based path (waves, rings, arcs)
				attrs.push('fill="none"');
				attrs.push(`stroke="${safeColor}"`);
				attrs.push(`stroke-opacity="${safeOpacity}"`);
				const sw = Math.max(
					0.5,
					Math.min(10, Number(p.strokeWidth) || 1)
				);
				attrs.push(`stroke-width="${sw}"`);
				if (p.strokeLinecap) {
					attrs.push(`stroke-linecap="${p.strokeLinecap}"`);
				}
			} else {
				// Fill-based path (default)
				attrs.push(`fill="${safeColor}"`);
				attrs.push(`fill-opacity="${safeOpacity}"`);
				if (p.fillRule) {
					attrs.push(`fill-rule="${p.fillRule}"`);
				}
			}

			// Per-path opacity multiplier (e.g. lighter grid lines)
			if (typeof p.opacity === 'number') {
				const safePathOpacity = Math.max(0, Math.min(1, p.opacity));
				attrs.push(`opacity="${safePathOpacity}"`);
			}

			return `<path ${attrs.join(' ')}/>`;
		})
		.join('');

	return `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">${pathElements}</svg>`;
}

/**
 * Get the CSS background-image value for a pattern.
 * Returns null for unrecognized pattern IDs.
 *
 * @param {string} patternId Pattern ID (must exist in PATTERNS)
 * @param {string} color     Fill color
 * @param {number} opacity   Fill opacity (0-1, clamped)
 * @param {number} scale     Scale multiplier (0.25-4, clamped)
 * @return {Object|null} Object with backgroundImage and backgroundSize, or null
 */
export function getPatternBackground(patternId, color, opacity, scale = 1) {
	if (!patternId || typeof patternId !== 'string') {
		return null;
	}

	// Own-property check, not a truthy bracket lookup: inherited
	// Object.prototype names ("constructor", "toString", …) must not resolve
	// to a function here and blow up the destructure below.
	if (!Object.prototype.hasOwnProperty.call(PATTERNS, patternId)) {
		return null;
	}
	const pattern = PATTERNS[patternId];

	const safeScale = Math.max(0.25, Math.min(4, Number(scale) || 1));
	const svg = buildPatternSvg(pattern, color, opacity);
	const backgroundImage = encodeSvg(svg);
	const backgroundSize = `${pattern.width * safeScale}px ${pattern.height * safeScale}px`;

	return { backgroundImage, backgroundSize };
}
