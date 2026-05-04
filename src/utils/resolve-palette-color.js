/**
 * Lookup helpers for the multi-origin palette returned by
 * useMultipleOriginColorsAndGradients().
 *
 * For slug or hex collisions across origins, --wp--preset--color--{slug}
 * resolves to whichever palette has higher precedence in WP_Theme_JSON
 * merge order: custom > theme > default. These helpers iterate origins in
 * that precedence so the editor lookup mirrors what the CSS variable
 * resolves to at render time.
 *
 * Looking up by origin `slug` instead of array index keeps this independent
 * of the order the hook happens to push origins into the array.
 *
 * @see node_modules/@wordpress/block-editor/build-module/components/colors-gradients/use-multiple-origin-colors-and-gradients.js
 */

const ORIGIN_PRECEDENCE = ['custom', 'theme', 'default'];

const orderedOrigins = (originColors) => {
	if (!Array.isArray(originColors)) return [];
	const byPrecedence = ORIGIN_PRECEDENCE.map((slug) =>
		originColors.find((o) => o?.slug === slug)
	).filter(Boolean);
	const others = originColors.filter(
		(o) => o && !ORIGIN_PRECEDENCE.includes(o.slug)
	);
	return [...byPrecedence, ...others];
};

/**
 * Find a color in the multi-origin palette by slug, preferring custom > theme
 * > default when the same slug appears in multiple origins.
 *
 * @param {Array}            originColors The grouped colors array from
 *                                        useMultipleOriginColorsAndGradients().
 * @param {string|undefined} slug         The color slug to look up.
 * @return {Object|undefined} The matching color object, or undefined.
 */
export function resolvePresetColorBySlug(originColors, slug) {
	if (!slug) return undefined;
	for (const origin of orderedOrigins(originColors)) {
		const match = origin.colors?.find((c) => c.slug === slug);
		if (match) return match;
	}
	return undefined;
}

/**
 * Find a color in the multi-origin palette by hex value (case-insensitive),
 * preferring custom > theme > default when the same hex appears in multiple
 * origins.
 *
 * @param {Array}            originColors The grouped colors array from
 *                                        useMultipleOriginColorsAndGradients().
 * @param {string|undefined} hex          The hex color to look up.
 * @return {Object|undefined} The matching color object, or undefined.
 */
export function resolvePresetColorByHex(originColors, hex) {
	if (!hex) return undefined;
	const normalized = hex.toLowerCase();
	for (const origin of orderedOrigins(originColors)) {
		const match = origin.colors?.find(
			(c) => c.color?.toLowerCase() === normalized
		);
		if (match) return match;
	}
	return undefined;
}
