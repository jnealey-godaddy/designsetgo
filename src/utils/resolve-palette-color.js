/**
 * Lookup helpers for the multi-origin palette returned by
 * useMultipleOriginColorsAndGradients().
 *
 * The hook returns origins in display order [theme, default, custom]. For
 * slug or hex collisions across origins, --wp--preset--color--{slug} resolves
 * to the *custom* palette at render time (custom CSS vars are loaded after
 * theme/default and override them). These helpers walk origins in reverse so
 * the editor lookup mirrors that cascade.
 *
 * @see node_modules/@wordpress/block-editor/build-module/components/colors-gradients/use-multiple-origin-colors-and-gradients.js
 */

/**
 * Find a color in the multi-origin palette by slug, preferring custom over
 * theme/default when the same slug appears in multiple origins.
 *
 * @param {Array}            originColors The grouped colors array from
 *                                        useMultipleOriginColorsAndGradients().
 * @param {string|undefined} slug         The color slug to look up.
 * @return {Object|undefined} The matching color object, or undefined.
 */
export function resolvePresetColorBySlug(originColors, slug) {
	if (!slug || !Array.isArray(originColors)) {
		return undefined;
	}
	for (let i = originColors.length - 1; i >= 0; i--) {
		const match = originColors[i]?.colors?.find((c) => c.slug === slug);
		if (match) return match;
	}
	return undefined;
}

/**
 * Find a color in the multi-origin palette by hex value (case-insensitive),
 * preferring custom over theme/default when the same hex appears in multiple
 * origins.
 *
 * @param {Array}            originColors The grouped colors array from
 *                                        useMultipleOriginColorsAndGradients().
 * @param {string|undefined} hex          The hex color to look up.
 * @return {Object|undefined} The matching color object, or undefined.
 */
export function resolvePresetColorByHex(originColors, hex) {
	if (!hex || !Array.isArray(originColors)) {
		return undefined;
	}
	const normalized = hex.toLowerCase();
	for (let i = originColors.length - 1; i >= 0; i--) {
		const match = originColors[i]?.colors?.find(
			(c) => c.color?.toLowerCase() === normalized
		);
		if (match) return match;
	}
	return undefined;
}
