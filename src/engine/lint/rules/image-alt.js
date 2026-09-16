/**
 * `image-alt` (error): flags images missing accessible alt text.
 *
 *  - `core/image` — flags a missing or empty `alt` attribute.
 *  - `designsetgo/dynamic-image` (`src/blocks/dynamic-image/block.json`) —
 *    its `alt` is resolved at *render* time from the bound Dynamic Tag
 *    source (`ImageResolver::resolve()` in `render.php`), so a lint pass
 *    over the JSON tree cannot know whether the bound source will supply
 *    good alt text; an absent `altOverride` is trusted to defer to it.
 *    The only statically-checkable mistake is an *explicit* empty
 *    `altOverride` (a present `"altOverride": ""` key) — that discards
 *    whatever alt text the bound source would have provided. `altOverride`
 *    defaults to `""` (no override), so the check is on key presence, not
 *    on the value differing from the default.
 *
 * Either block is exempt when `className` contains the `is-decorative`
 * token — no image block.json in this repo defines a dedicated decorative
 * attribute (icon/block.json's `isDecorative` is for the Icon block, not
 * an image), so `className` is the documented escape hatch here.
 *
 * Plain ES module — no Node-only or WordPress imports.
 */

const DECORATIVE_CLASS_RE = /(?:^|\s)is-decorative(?:\s|$)/;

/**
 * Whether a node's `className` attribute marks it decorative.
 *
 * @param {Object} node Block node.
 * @return {boolean} True when `className` contains `is-decorative`.
 */
function isDecorative(node) {
	const className = node?.attributes?.className;
	return typeof className === 'string' && DECORATIVE_CLASS_RE.test(className);
}

const rule = {
	id: 'image-alt',
	severity: 'error',

	check(node, ctx) {
		if (isDecorative(node)) {
			return;
		}

		if (node.name === 'core/image') {
			const alt = node.attributes?.alt;
			if (typeof alt !== 'string' || alt.trim() === '') {
				ctx.report(
					'core/image has no alt text.',
					'Add descriptive alt text, or mark the image decorative with className "is-decorative".'
				);
			}
			return;
		}

		if (node.name === 'designsetgo/dynamic-image') {
			const attributes = node.attributes || {};
			const hasExplicitAltOverride = Object.prototype.hasOwnProperty.call(
				attributes,
				'altOverride'
			);
			if (hasExplicitAltOverride && attributes.altOverride === '') {
				ctx.report(
					'designsetgo/dynamic-image explicitly sets an empty altOverride, discarding the alt text its bound source would supply.',
					'Remove the empty altOverride to let alt text come from the bound source, or supply descriptive text.'
				);
			}
		}
	},
};

export default rule;
