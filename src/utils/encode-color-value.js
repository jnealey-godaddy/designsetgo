/**
 * Encode/decode color values between hex and WordPress preset format.
 *
 * WordPress's ColorGradientSettingsDropdown always passes raw hex values
 * to onColorChange, even when users pick a preset color from the palette.
 * These utilities perform the reverse lookup to store the WordPress internal
 * preset format (var:preset|color|{slug}), keeping blocks in sync with
 * theme color changes.
 *
 * The stored format must then be converted to CSS variables for output
 * using convertPresetToCSSVar() from convert-preset-to-css-var.js.
 *
 * @see node_modules/@wordpress/block-editor/build-module/components/global-styles/color-panel.js
 */

import {
	resolvePresetColorBySlug,
	resolvePresetColorByHex,
} from './resolve-palette-color';

/**
 * Encode a hex color value to WordPress preset format if it matches a theme preset.
 *
 * Called in onColorChange callbacks. If the hex value matches a color in the
 * theme/default/custom palette, returns "var:preset|color|{slug}".
 * Otherwise returns the raw value (custom color).
 *
 * @param {string|undefined} colorValue    The raw color value from the picker.
 * @param {Object}           colorSettings The result of useMultipleOriginColorsAndGradients().
 * @return {string|undefined} WordPress preset format string or the original value.
 */
export function encodeColorValue(colorValue, colorSettings) {
	if (!colorValue || !colorSettings?.colors) {
		return colorValue;
	}

	const colorObject = resolvePresetColorByHex(
		colorSettings.colors,
		colorValue
	);

	return colorObject ? `var:preset|color|${colorObject.slug}` : colorValue;
}

/**
 * Decode a WordPress preset color value back to hex for UI display.
 *
 * Called for the colorValue prop of ColorGradientSettingsDropdown so the
 * color picker swatch highlights the correct preset color.
 *
 * Handles both formats:
 * - "var:preset|color|{slug}" (WordPress internal format, stored in attributes)
 * - "var(--wp--preset--color--{slug})" (CSS variable format, legacy)
 *
 * @param {string|undefined} colorValue    The stored color value.
 * @param {Object}           colorSettings The result of useMultipleOriginColorsAndGradients().
 * @return {string|undefined} Hex color string or the original value.
 */
export function decodeColorValue(colorValue, colorSettings) {
	if (!colorValue || !colorSettings?.colors) {
		return colorValue;
	}

	let slug;

	// Match WordPress internal format: var:preset|color|{slug}
	const presetMatch = colorValue.match(/^var:preset\|color\|(.+)$/);
	if (presetMatch) {
		slug = presetMatch[1];
	}

	// Match CSS variable format: var(--wp--preset--color--{slug}) (legacy support)
	if (!slug) {
		const cssVarMatch = colorValue.match(
			/^var\(--wp--preset--color--(.+)\)$/
		);
		if (cssVarMatch) {
			slug = cssVarMatch[1];
		}
	}

	if (!slug) {
		return colorValue;
	}

	const colorObject = resolvePresetColorBySlug(colorSettings.colors, slug);

	// Return hex if found, undefined if slug exists but isn't in current palette.
	// Returning undefined (not the raw preset string) prevents broken swatches
	// in the color picker UI when a theme removes a previously-used color.
	return colorObject ? colorObject.color : undefined;
}
