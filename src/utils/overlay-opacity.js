/**
 * overlayOpacityFraction
 *
 * Convert a percent overlay-opacity attribute (0–100) into a CSS opacity
 * fraction (0–1). Out-of-range values are clamped to [0, 100] and non-finite
 * values fall back to the 80% default.
 *
 * Shared by scroll-slides save.js (frontend markup) and edit.js (editor
 * preview) so the two paths can't drift. The PHP render path
 * (src/blocks/scroll-slides/render.php) mirrors this same clamp/fallback and
 * must be kept in sync manually.
 *
 * @param {number} percent Opacity as a percentage (0–100).
 * @return {number} Opacity as a fraction in [0, 1].
 */
export function overlayOpacityFraction(percent) {
	const clamped = Number.isFinite(percent)
		? Math.min(100, Math.max(0, percent))
		: 80;

	return clamped / 100;
}

/**
 * Default opacity for a container overlay whose colour is opaque.
 */
export const DEFAULT_OVERLAY_OPACITY = '0.65';

/**
 * Whitespace trimmed from a colour and its alpha component: space, tab, LF,
 * CR, form feed, vertical tab and NBSP. Spelled out (rather than JS trim() or
 * PHP trim()) so Block_Inserter::declared_color_alpha() trims the same set.
 */
const COLOR_WHITESPACE = /^[ \t\n\r\f\x0B\u00A0]+|[ \t\n\r\f\x0B\u00A0]+$/g;

/**
 * Alpha component: a plain decimal number, optionally a percentage. Hex,
 * binary, exponent and keyword forms are rejected on both the JS and PHP side.
 */
const ALPHA_PATTERN = /^([+-]?(?:\d+(?:\.\d*)?|\.\d+))(%?)$/;

/**
 * Trim COLOR_WHITESPACE from both ends.
 *
 * @param {string} value Value to trim.
 * @return {string} Trimmed value.
 */
function trimColorWhitespace(value) {
	return value.replace(COLOR_WHITESPACE, '');
}

/**
 * Read the alpha channel a colour value declares, when it declares one.
 *
 * Recognises `#RGBA` / `#RRGGBBAA` hex and the functional notations with an
 * explicit alpha component: legacy comma syntax (`rgba(0,0,0,.4)`,
 * `hsla(…)`) and the space syntax with a slash (`rgb(0 0 0 / 40%)`,
 * `oklch(… / .5)`). Preset slugs, CSS variables and keywords return null.
 *
 * @param {string} color Colour value as stored on the block.
 * @return {number|null} Alpha in [0, 1], or null when none is declared.
 */
function getDeclaredAlpha(color) {
	const value = trimColorWhitespace(color).toLowerCase();

	const hex = value.match(/^#([0-9a-f]{4}|[0-9a-f]{8})$/);
	if (hex) {
		const digits = hex[1];
		return digits.length === 4
			? parseInt(digits[3], 16) / 15
			: parseInt(digits.slice(6), 16) / 255;
	}

	const fn = value.match(
		/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\((.*)\)$/
	);
	if (!fn) {
		return null;
	}

	const args = fn[1];
	let alpha;
	if (args.includes('/')) {
		alpha = args.slice(args.lastIndexOf('/') + 1);
	} else {
		const parts = args.split(',');
		if (parts.length !== 4) {
			return null;
		}
		alpha = parts[3];
	}

	const match = trimColorWhitespace(alpha).match(ALPHA_PATTERN);
	if (!match) {
		return null;
	}

	const number = parseFloat(match[1]);
	return match[2] ? number / 100 : number;
}

/**
 * Resolve `--dsgo-overlay-opacity` for a container overlay colour.
 *
 * A colour that carries its own translucency (alpha below 1) sets the overlay
 * opacity by itself, so the layer is emitted fully opaque — otherwise the
 * colour's alpha and the layer opacity multiply (`#1212127D` at 0.65 would
 * paint at ~32%). Every other colour, including preset slugs and CSS
 * variables, uses the default opacity.
 *
 * Shared by the Section, Row, Grid and Scroll Accordion Item save.js and
 * edit.js. Block_Inserter::overlay_opacity_for_color() is the PHP twin and
 * must return the same string for every input.
 *
 * @param {string} color Overlay colour attribute.
 * @return {string} '1' or DEFAULT_OVERLAY_OPACITY.
 */
export function getOverlayOpacity(color) {
	if (typeof color !== 'string' || color === '') {
		return DEFAULT_OVERLAY_OPACITY;
	}

	const alpha = getDeclaredAlpha(color);

	return alpha !== null && alpha < 1 ? '1' : DEFAULT_OVERLAY_OPACITY;
}
