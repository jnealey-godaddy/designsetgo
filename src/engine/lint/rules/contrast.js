/**
 * `contrast` (error): resolves each node's text and background color and
 * flags a WCAG 2.x ratio below 4.5.
 *
 * Resolution, per side:
 *  - `textColor` / `backgroundColor` (bare preset slug) via
 *    `ctx.design.presetColor()`.
 *  - `style.color.text` / `style.color.background` (preset ref or raw
 *    color) via `ctx.design.presetColor()` first, then `parseColor()`.
 *
 * A node is only checked when it sets at least one side itself (so a
 * low-contrast section's plain descendants aren't each re-reported).
 * Whichever side it doesn't set is inherited from the nearest ancestor
 * that itself sets that side. Skipped when either side ends up
 * unresolvable, or when a gradient/background image is present — on the
 * node itself, or on the ancestor that supplied an inherited background
 * (`gradient`, `style.color.gradient`, `style.background.backgroundImage`).
 *
 * Plain ES module — no Node-only or WordPress imports.
 */
import { parseColor, contrastRatio } from '../color';

const CONTRAST_THRESHOLD = 4.5;

/**
 * Resolve a color reference to a usable color string, or `null` if it
 * fails to resolve as either a preset or a raw color.
 *
 * @param {unknown} value  Candidate color reference.
 * @param {Object}  design `ctx.design` helpers.
 * @return {string|null} Resolved color string, or `null`.
 */
function resolveColorValue(value, design) {
	if (typeof value !== 'string' || value === '') {
		return null;
	}
	const preset = design.presetColor(value);
	if (preset) {
		return preset;
	}
	return parseColor(value) ? value : null;
}

/**
 * A node's own (non-inherited) resolved color for one side.
 *
 * @param {Object} node   Block node.
 * @param {string} side   `'text'` or `'background'`.
 * @param {Object} design `ctx.design` helpers.
 * @return {string|null|undefined} The resolved color, `null` if the node
 *   sets the side but it doesn't resolve, or `undefined` if the node
 *   doesn't set the side at all.
 */
function ownColor(node, side, design) {
	const attributes = node?.attributes || {};

	if (side === 'text') {
		if (
			typeof attributes.textColor === 'string' &&
			attributes.textColor !== ''
		) {
			return resolveColorValue(attributes.textColor, design);
		}
		if (attributes.style?.color?.text !== undefined) {
			return resolveColorValue(attributes.style.color.text, design);
		}
		return undefined;
	}

	if (
		typeof attributes.backgroundColor === 'string' &&
		attributes.backgroundColor !== ''
	) {
		return resolveColorValue(attributes.backgroundColor, design);
	}
	if (attributes.style?.color?.background !== undefined) {
		return resolveColorValue(attributes.style.color.background, design);
	}
	return undefined;
}

/**
 * Whether a node has a gradient or background image that would make a
 * flat text/background contrast check meaningless.
 *
 * @param {Object} node Block node.
 * @return {boolean} True when a gradient/background image is present.
 */
function hasVisualOverride(node) {
	const attributes = node?.attributes || {};

	if (typeof attributes.gradient === 'string' && attributes.gradient !== '') {
		return true;
	}
	if (
		typeof attributes.style?.color?.gradient === 'string' &&
		attributes.style.color.gradient !== ''
	) {
		return true;
	}
	const backgroundImage = attributes.style?.background?.backgroundImage;
	if (
		backgroundImage === undefined ||
		backgroundImage === null ||
		backgroundImage === ''
	) {
		return false;
	}
	if (typeof backgroundImage === 'object') {
		return Object.keys(backgroundImage).length > 0;
	}
	return true;
}

/**
 * Resolve one side's color for `node`, inheriting from the nearest
 * ancestor that sets it when `node` doesn't set it itself.
 *
 * @param {Object}   node      Block node.
 * @param {Object[]} ancestors Ancestor nodes, nearest last.
 * @param {string}   side      `'text'` or `'background'`.
 * @param {Object}   design    `ctx.design` helpers.
 * @return {{ color: string|null|undefined, source: Object }} Resolved
 *   color and the node (self or ancestor) that supplied it.
 */
function resolveSide(node, ancestors, side, design) {
	const own = ownColor(node, side, design);
	if (own !== undefined) {
		return { color: own, source: node };
	}
	for (let i = ancestors.length - 1; i >= 0; i--) {
		const ancestor = ancestors[i];
		const value = ownColor(ancestor, side, design);
		if (value !== undefined) {
			return { color: value, source: ancestor };
		}
	}
	return { color: undefined, source: node };
}

const rule = {
	id: 'contrast',
	severity: 'error',

	check(node, ctx) {
		const design = ctx.design;
		const ownText = ownColor(node, 'text', design);
		const ownBackground = ownColor(node, 'background', design);

		if (ownText === undefined && ownBackground === undefined) {
			return;
		}

		const text = resolveSide(node, ctx.ancestors, 'text', design);
		const background = resolveSide(
			node,
			ctx.ancestors,
			'background',
			design
		);

		if (!text.color || !background.color) {
			return;
		}

		if (hasVisualOverride(node) || hasVisualOverride(background.source)) {
			return;
		}

		const ratio = contrastRatio(text.color, background.color);
		if (ratio === null || ratio >= CONTRAST_THRESHOLD) {
			return;
		}

		ctx.report(
			`Low contrast: text ${text.color} on background ${background.color} (ratio ${ratio.toFixed(2)}, needs ${CONTRAST_THRESHOLD}).`,
			'Choose a text/background pair with at least 4.5:1 contrast.'
		);
	},
};

export default rule;
