/**
 * `preset-values` (warning): flags raw `style.*` values where a theme.json
 * preset collection exists that a slug could have been used from instead.
 * Preset references (`var:preset|…`, `var(--wp--preset--…)`) never match
 * the raw-value patterns below, so they're never flagged.
 *
 *  - `style.color.text` / `style.color.background` — raw colors (anything
 *    `color.js`'s `parseColor()` recognizes), only when the palette is
 *    non-empty. Suggests the nearest palette slug by Euclidean RGB
 *    distance.
 *  - `style.spacing.padding` / `style.spacing.margin` / `style.spacing.blockGap`
 *    — raw `px`/`rem`/`em` values, only when spacing presets exist. These
 *    may be a single string or a `{top,right,bottom,left}` object; every
 *    string leaf is checked.
 *  - `style.typography.fontSize` — raw `px`/`rem`/`em` value, only when
 *    font-size presets exist.
 *
 * Plain ES module — no Node-only or WordPress imports.
 */
import { parseColor } from '../color';

const RAW_SIZE_RE = /^-?\d*\.?\d+(px|rem|em)$/;

/**
 * Whether `value` is a raw (non-preset) size string this rule flags.
 *
 * @param {unknown} value Candidate value.
 * @return {boolean} True for a raw `px`/`rem`/`em` value.
 */
function isRawSizeValue(value) {
	return typeof value === 'string' && RAW_SIZE_RE.test(value.trim());
}

/**
 * Nearest palette slug to a raw color, by Euclidean RGB distance.
 *
 * @param {{r: number, g: number, b: number}} color   Parsed raw color.
 * @param {Map<string, string>}               palette Slug → hex.
 * @return {string|null} Nearest slug, or `null` if the palette is empty.
 */
function nearestPaletteSlug(color, palette) {
	let nearestSlug = null;
	let nearestDistance = Infinity;

	for (const [slug, hex] of palette) {
		const paletteColor = parseColor(hex);
		if (!paletteColor) {
			continue;
		}
		const distance = Math.sqrt(
			(color.r - paletteColor.r) ** 2 +
				(color.g - paletteColor.g) ** 2 +
				(color.b - paletteColor.b) ** 2
		);
		if (distance < nearestDistance) {
			nearestDistance = distance;
			nearestSlug = slug;
		}
	}

	return nearestSlug;
}

/**
 * Check one `style.color.{text,background}` field for a raw value.
 *
 * @param {string}  field `'text'` or `'background'`.
 * @param {unknown} value Candidate attribute value.
 * @param {Object}  ctx   Rule context (`check` shape).
 */
function checkColorField(field, value, ctx) {
	if (ctx.design.palette.size === 0) {
		return;
	}
	const parsed = parseColor(value);
	if (!parsed) {
		return;
	}
	const nearestSlug = nearestPaletteSlug(parsed, ctx.design.palette);
	ctx.report(
		`style.color.${field} uses a raw color instead of a theme preset.`,
		nearestSlug
			? `Use the "${nearestSlug}" color preset instead of ${value}.`
			: 'Use a theme color preset instead of a raw color.'
	);
}

/**
 * Recursively check a `style.spacing.*` value (string or nested object)
 * for raw px/rem/em leaves.
 *
 * @param {string}  field Spacing field name, for the message.
 * @param {unknown} value Candidate value (string or object of strings).
 * @param {Object}  ctx   Rule context (`check` shape).
 */
function checkSpacingValue(field, value, ctx) {
	if (isRawSizeValue(value)) {
		ctx.report(
			`style.spacing.${field} uses a raw ${value.trim()} value instead of a theme spacing preset.`,
			'Use a theme spacing preset instead of a raw size.'
		);
		return;
	}
	if (value && typeof value === 'object') {
		for (const nested of Object.values(value)) {
			checkSpacingValue(field, nested, ctx);
		}
	}
}

const rule = {
	id: 'preset-values',
	severity: 'warning',

	check(node, ctx) {
		const style = node.attributes?.style;
		if (!style || typeof style !== 'object') {
			return;
		}

		if (style.color) {
			checkColorField('text', style.color.text, ctx);
			checkColorField('background', style.color.background, ctx);
		}

		if (style.spacing && ctx.design.spacingSlugs.size > 0) {
			checkSpacingValue('padding', style.spacing.padding, ctx);
			checkSpacingValue('margin', style.spacing.margin, ctx);
			checkSpacingValue('blockGap', style.spacing.blockGap, ctx);
		}

		if (
			style.typography &&
			ctx.design.fontSizeSlugs.size > 0 &&
			isRawSizeValue(style.typography.fontSize)
		) {
			ctx.report(
				`style.typography.fontSize uses a raw ${style.typography.fontSize.trim()} value instead of a theme font-size preset.`,
				'Use a theme font-size preset instead of a raw size.'
			);
		}
	},
};

export default rule;
