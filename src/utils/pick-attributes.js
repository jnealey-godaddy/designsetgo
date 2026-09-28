/**
 * Attribute helpers for block transforms.
 *
 * A transform between a core block and a DSGo block builds the target's
 * attributes by hand, so anything not copied is silently lost. These keep
 * the author's colours and borders when both blocks support them.
 */

// Preset colour slugs and the classes they come with.
export const COLOR_ATTRIBUTE_KEYS = [
	'backgroundColor',
	'textColor',
	'gradient',
	'borderColor',
	'className',
];

/**
 * The given attributes that are set (neither undefined nor '').
 *
 * @param {Object}   attributes Source attributes.
 * @param {string[]} keys       Keys to copy.
 * @return {Object} Copied attributes.
 */
export function pickAttributes(attributes, keys) {
	return Object.fromEntries(
		keys
			.filter(
				(key) =>
					attributes?.[key] !== undefined && attributes[key] !== ''
			)
			.map((key) => [key, attributes[key]])
	);
}

/**
 * The parts of a `style` attribute that the target block supports.
 *
 * @param {Object|undefined} style          Source `style` attribute.
 * @param {Object}           [only]         Which groups to keep.
 * @param {boolean}          [only.border]  Keep border styles.
 * @param {boolean}          [only.padding] Keep padding.
 * @param {boolean}          [only.margin]  Keep margin.
 * @return {Object|undefined} Style for the target, or undefined when empty.
 */
export function pickStyle(
	style,
	{ border = false, padding = false, margin = false } = {}
) {
	if (!style) {
		return undefined;
	}
	const spacing = {
		...(padding &&
			style.spacing?.padding && { padding: style.spacing.padding }),
		...(margin &&
			style.spacing?.margin && { margin: style.spacing.margin }),
	};
	const picked = {
		...(style.color && { color: style.color }),
		...(style.elements && { elements: style.elements }),
		...(border && style.border && { border: style.border }),
		...(Object.keys(spacing).length && { spacing }),
	};
	return Object.keys(picked).length ? picked : undefined;
}

/**
 * Colours, classes and the supported parts of `style`, ready to spread into
 * a transform's attributes.
 *
 * @param {Object} attributes    Source attributes.
 * @param {Object} [styleGroups] Passed to pickStyle().
 * @return {Object} Attributes to spread.
 */
export function pickColors(attributes, styleGroups) {
	const style = pickStyle(attributes?.style, styleGroups);
	return {
		...pickAttributes(attributes, COLOR_ATTRIBUTE_KEYS),
		...(style && { style }),
	};
}
