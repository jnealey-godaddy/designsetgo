/**
 * cssVars
 *
 * Pure helper that builds an inline-style object of CSS custom properties
 * from a block's attributes. Replaces the hand-rolled
 *
 *   style={{
 *     '--dsgo-bg': convertColorToCSSVar(attrs.bg),
 *     '--dsgo-text': convertColorToCSSVar(attrs.text),
 *   }}
 *
 * pattern in ~15 save.js files.
 *
 * Usage:
 *
 *   const styles = cssVars(attributes, {
 *     '--dsgo-bg': 'backgroundColor',
 *     '--dsgo-text': 'textColor',
 *     '--dsgo-pad': { attribute: 'padding', convert: (v) => `${v}px` },
 *   });
 *
 * Empty, null, or undefined values are omitted so they don't override CSS defaults.
 *
 * @param {Object} attributes The block attributes.
 * @param {Object} map        Map of CSS-variable name → attribute name (string)
 *                            or { attribute, convert } for non-color values.
 * @return {Object} Inline-style object suitable for React's `style` prop.
 *
 * NOTE: attributes whose raw value is `undefined`, `null`, or `''` are skipped
 * BEFORE the converter is called. Custom converters therefore cannot implement
 * fallback defaults — callers that need computed fallbacks (e.g. `hoverBg ||
 * openBg`) should resolve them before passing `attributes`, or use the spread
 * pattern directly.
 */
import { convertColorToCSSVar } from './convert-preset-to-css-var';

export function cssVars(attributes, map) {
	const styles = {};
	for (const [cssVar, spec] of Object.entries(map)) {
		const isShorthand = typeof spec === 'string';
		const attribute = isShorthand ? spec : spec.attribute;
		const convert = isShorthand ? convertColorToCSSVar : spec.convert;
		const raw = attributes[attribute];
		if (raw === undefined || raw === null || raw === '') {
			continue;
		}
		const value = convert(raw);
		if (value === undefined || value === '') {
			continue;
		}
		styles[cssVar] = value;
	}
	return styles;
}
