/**
 * CSS keywords that should never be treated as bare preset slugs.
 */
const CSS_KEYWORDS = new Set([
	'transparent',
	'inherit',
	'initial',
	'unset',
	'revert',
	'revert-layer',
	'currentcolor',
	'none',
	'auto',
	'normal',
]);

/**
 * Values that start with these prefixes are already valid CSS and should pass through.
 */
const CSS_VALUE_PREFIX =
	/^(#|rgb|hsl|hwb|lab|lch|oklch|oklab|color\(|var\(|url\(|linear-gradient\(|radial-gradient\(|conic-gradient\(|repeating-linear-gradient\(|repeating-radial-gradient\(|repeating-conic-gradient\(|\d)/;

/**
 * Convert WordPress preset format to CSS variable.
 *
 * Converts "var:preset|spacing|md" to "var(--wp--preset--spacing--md)".
 * Also handles WordPress 6.1+ object format {top, left} for separate row/column gaps.
 *
 * When presetType is provided, bare preset slugs (e.g. "accent-3") are detected
 * and converted to the appropriate CSS variable. This defends against markup
 * generated without the "var:preset|" prefix.
 *
 * @param {string|Object} value        The preset value or gap object
 * @param {string}        [presetType] Optional preset type hint (e.g. 'color', 'spacing')
 * @return {string|undefined} CSS variable format, coerced string, or undefined if no valid value
 */
export function convertPresetToCSSVar(value, presetType) {
	if (!value) {
		return undefined;
	}

	// Handle object format (WordPress 6.1+ for separate row/column gaps)
	// For flex layouts, use top value (row gap) as the primary gap
	if (typeof value === 'object') {
		value = value.top || value.left;
		if (!value) {
			return undefined;
		}
	}

	// Ensure value is a string before using string methods
	if (typeof value !== 'string') {
		return String(value);
	}

	// If it's already a CSS variable, return as-is
	if (value.startsWith('var(--')) {
		return value;
	}

	// Convert WordPress preset format: var:preset|spacing|md -> var(--wp--preset--spacing--md)
	if (value.startsWith('var:preset|')) {
		const parts = value.replace('var:preset|', '').split('|');
		return `var(--wp--preset--${parts.join('--')})`;
	}

	// When a preset type is provided, detect bare slugs and convert them.
	// Bare slugs are values like "accent-3" or "primary" that an LLM or
	// external tool might use instead of the full "var:preset|color|accent-3" format.
	if (
		presetType &&
		!CSS_VALUE_PREFIX.test(value) &&
		!CSS_KEYWORDS.has(value.toLowerCase())
	) {
		return `var(--wp--preset--${presetType}--${value})`;
	}

	return value;
}

/**
 * Convert a color value to a CSS variable, with bare slug detection.
 *
 * Convenience wrapper around convertPresetToCSSVar that automatically
 * handles bare color preset slugs (e.g. "accent-3" -> "var(--wp--preset--color--accent-3)").
 *
 * @param {string} value The color value (preset format, CSS variable, hex, or bare slug)
 * @return {string|undefined} CSS variable or raw color value
 */
export function convertColorToCSSVar(value) {
	return convertPresetToCSSVar(value, 'color');
}
