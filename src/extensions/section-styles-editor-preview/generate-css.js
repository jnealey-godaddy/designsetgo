/**
 * Section Styles — Editor Preview: CSS generation
 *
 * Pure helpers that turn a global-styles block-variation config into an editor
 * CSS overlay for the DesignSetGo container blocks. Kept free of any
 * `@wordpress/*` imports so it can be unit-tested in isolation.
 *
 * Context: WordPress mirrors theme/plugin section styles onto DSGo blocks
 * server-side (see `DesignSetGo\Section_Styles`), but the editor generates
 * block-style-variation CSS client-side and never runs that mirror for the
 * user (Global Styles) layer. This overlay reproduces the user-layer variation
 * styles on the DSGo blocks so the editor preview matches the saved output.
 *
 * @package
 */

/**
 * Core container blocks whose section-style variations we mirror. Matches the
 * `$source_blocks` list in class-section-styles.php.
 */
export const SOURCE_BLOCKS = ['core/group', 'core/columns', 'core/column'];

/**
 * DesignSetGo container block suffixes (the part after `designsetgo/`) that
 * receive mirrored section styles. Mirrors `Section_Styles::$container_blocks`
 * — deliberately excludes `image-accordion-item` (opts out of background
 * color). Keep in sync with class-section-styles.php.
 */
export const TARGET_SUFFIXES = [
	'section',
	'row',
	'grid',
	'card',
	'fifty-fifty',
	'modal',
	'slide',
	'scroll-slide',
	'tab',
	'accordion-item',
	'scroll-accordion-item',
	'timeline-item',
	'counter',
	'flip-card-face',
];

/**
 * Convert a camelCase segment to kebab-case for CSS custom property names.
 *
 * @param {string} segment Path segment (e.g. `fontSize`).
 * @return {string} Kebab-cased segment (e.g. `font-size`).
 */
function kebabCase(segment) {
	return segment.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

/**
 * Whether a style value is present (allows `0` / empty string, excludes
 * null/undefined).
 *
 * @param {*} value Value to test.
 * @return {boolean} True when set.
 */
function isSet(value) {
	return value !== undefined && value !== null;
}

/**
 * Resolve a theme.json style value to a CSS value, expanding preset/custom
 * references (`var:preset|color|accent-2` → `var(--wp--preset--color--accent-2)`).
 *
 * Also strips characters that could terminate a declaration or rule (`{`, `}`,
 * `;`) and the backslash (`\`) that CSS would otherwise decode into those same
 * characters via hex escapes (e.g. `\7b` → `{`), which would slip past a
 * literal-character strip. Values come straight from the *unsanitized* edited
 * Global Styles record and are written via `textContent`, so this is the only
 * guard against a crafted value escaping its rule and injecting sibling CSS
 * into the canvas. Legitimate CSS values (colors, lengths, `var()`, gradients,
 * `calc()`) never contain these characters, so stripping is a no-op for real
 * data.
 *
 * @param {*} value Raw style value.
 * @return {*} CSS-ready value.
 */
export function toCssValue(value) {
	if (typeof value !== 'string') {
		return value;
	}

	const match = value.match(/^var:(preset|custom)\|(.+)$/);
	const resolved = match
		? `var(--wp--${match[1]}--${match[2]
				.split('|')
				.map(kebabCase)
				.join('--')})`
		: value;

	return resolved.replace(/[{};\\]/g, '');
}

/**
 * Build border declarations, supporting both the flat shape
 * (`{ color, width, style, radius }`) and the split-sides shape
 * (`{ top: { color, width, style }, … }`), plus per-corner radius.
 *
 * @param {Object} border Border style object.
 * @return {string[]} CSS declarations.
 */
function borderDeclarations(border) {
	const out = [];
	if (!border || typeof border !== 'object') {
		return out;
	}

	const sides = ['top', 'right', 'bottom', 'left'];
	const hasSides = sides.some((side) => border[side]);

	if (hasSides) {
		sides.forEach((side) => {
			const edge = border[side];
			if (!edge || typeof edge !== 'object') {
				return;
			}
			if (isSet(edge.color)) {
				out.push(`border-${side}-color:${toCssValue(edge.color)}`);
			}
			if (isSet(edge.width)) {
				out.push(`border-${side}-width:${toCssValue(edge.width)}`);
			}
			if (isSet(edge.style)) {
				out.push(`border-${side}-style:${toCssValue(edge.style)}`);
			}
		});
	} else {
		if (isSet(border.color)) {
			out.push(`border-color:${toCssValue(border.color)}`);
		}
		if (isSet(border.width)) {
			out.push(`border-width:${toCssValue(border.width)}`);
		}
		if (isSet(border.style)) {
			out.push(`border-style:${toCssValue(border.style)}`);
		}
	}

	// Radius can coexist with either shape.
	if (isSet(border.radius)) {
		if (typeof border.radius === 'object') {
			const corners = {
				topLeft: 'top-left',
				topRight: 'top-right',
				bottomLeft: 'bottom-left',
				bottomRight: 'bottom-right',
			};
			Object.keys(corners).forEach((key) => {
				if (isSet(border.radius[key])) {
					out.push(
						`border-${corners[key]}-radius:${toCssValue(
							border.radius[key]
						)}`
					);
				}
			});
		} else {
			out.push(`border-radius:${toCssValue(border.radius)}`);
		}
	}

	return out;
}

/**
 * Build spacing (padding/margin) declarations from a spacing style object.
 *
 * @param {Object} spacing Spacing style object.
 * @return {string[]} CSS declarations.
 */
function spacingDeclarations(spacing) {
	const out = [];
	if (!spacing || typeof spacing !== 'object') {
		return out;
	}

	['padding', 'margin'].forEach((prop) => {
		const box = spacing[prop];
		if (!box || typeof box !== 'object') {
			return;
		}
		['top', 'right', 'bottom', 'left'].forEach((side) => {
			if (isSet(box[side])) {
				out.push(`${prop}-${side}:${toCssValue(box[side])}`);
			}
		});
	});

	return out;
}

/**
 * Turn a single variation's style object into a CSS declaration string.
 *
 * @param {Object} variation Variation style object (theme.json shape).
 * @return {string} `prop:value;prop:value` (no braces); empty when nothing set.
 */
export function variationDeclarations(variation) {
	if (!variation || typeof variation !== 'object') {
		return '';
	}

	const out = [];
	const color = variation.color || {};

	if (isSet(color.background)) {
		out.push(`background-color:${toCssValue(color.background)}`);
	}
	if (isSet(color.gradient)) {
		out.push(`background:${toCssValue(color.gradient)}`);
	}
	if (isSet(color.text)) {
		out.push(`color:${toCssValue(color.text)}`);
	}

	out.push(...borderDeclarations(variation.border));
	out.push(...spacingDeclarations(variation.spacing));

	if (isSet(variation.shadow)) {
		out.push(`box-shadow:${toCssValue(variation.shadow)}`);
	}

	const typography = variation.typography || {};
	if (isSet(typography.fontSize)) {
		out.push(`font-size:${toCssValue(typography.fontSize)}`);
	}
	if (isSet(typography.lineHeight)) {
		out.push(`line-height:${toCssValue(typography.lineHeight)}`);
	}

	return out.join(';');
}

/**
 * Build the full editor overlay stylesheet from a global-styles `styles.blocks`
 * config. Collects every variation registered on the core container blocks
 * (first source wins on slug collision) and emits one rule per DSGo target ×
 * variation, scoped to the stable `is-style-{slug}` class.
 *
 * @param {Object} blocksConfig `styles.blocks` object from global styles.
 * @return {string} CSS stylesheet (may be empty).
 */
export function buildVariationCss(blocksConfig) {
	if (!blocksConfig || typeof blocksConfig !== 'object') {
		return '';
	}

	// Flatten variations across the source containers; first source wins.
	const merged = {};
	SOURCE_BLOCKS.forEach((source) => {
		const variations = blocksConfig[source]?.variations;
		if (!variations) {
			return;
		}
		Object.keys(variations).forEach((slug) => {
			if (!(slug in merged)) {
				merged[slug] = variations[slug];
			}
		});
	});

	const slugs = Object.keys(merged);
	if (!slugs.length) {
		return '';
	}

	let css = '';
	TARGET_SUFFIXES.forEach((suffix) => {
		const ownVariations =
			blocksConfig[`designsetgo/${suffix}`]?.variations || {};
		slugs.forEach((slug) => {
			// Parity with Section_Styles::mirror_variation_styles(): never
			// clobber a variation the DSGo block defines for itself. The editor
			// already previews that one natively (it lives under the block's own
			// name), and the server mirror skips broadcasting onto it — so
			// emitting the core-container version here would reintroduce the
			// preview/frontend divergence this overlay exists to prevent.
			if (slug in ownVariations) {
				return;
			}
			const declarations = variationDeclarations(merged[slug]);
			if (declarations) {
				css += `.wp-block-designsetgo-${suffix}.is-style-${slug}{${declarations}}\n`;
			}
		});
	});

	return css;
}
