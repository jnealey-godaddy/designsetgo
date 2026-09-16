/**
 * `no-custom-html` (error): agents sometimes reach for raw markup instead
 * of a real block. Both patterns bypass the block editor's design system,
 * sanitization, and accessibility guarantees, so both are hard errors:
 *
 *  - `core/html` — renders whatever string it's given, verbatim.
 *  - Any block whose attributes contain a string with inline `<svg` markup
 *    (icons/illustrations pasted straight into an attribute instead of
 *    using a real block).
 *
 * Plain ES module — no Node-only or WordPress imports.
 */

const SVG_RE = /<svg/i;

/**
 * Whether `value` contains `<svg` markup anywhere within it — a string
 * directly, or nested inside an array/object (e.g. `style`, `sourceArgs`).
 *
 * @param {unknown} value Candidate attribute value.
 * @return {boolean} True when raw SVG markup is found.
 */
function containsSvgMarkup(value) {
	if (typeof value === 'string') {
		return SVG_RE.test(value);
	}
	if (Array.isArray(value)) {
		return value.some(containsSvgMarkup);
	}
	if (value && typeof value === 'object') {
		return Object.values(value).some(containsSvgMarkup);
	}
	return false;
}

const rule = {
	id: 'no-custom-html',
	severity: 'error',

	check(node, ctx) {
		if (node.name === 'core/html') {
			ctx.report(
				'core/html renders arbitrary custom markup, bypassing the block editor’s design system and accessibility checks.',
				'Use a real block (e.g. designsetgo/icon, core/image, or a layout block) instead of core/html.'
			);
			return;
		}

		if (containsSvgMarkup(node.attributes)) {
			ctx.report(
				`${node.name} has raw <svg> markup embedded in an attribute instead of using a real block.`,
				'Use designsetgo/icon (or core/image) instead of embedding raw SVG markup.'
			);
		}
	},
};

export default rule;
