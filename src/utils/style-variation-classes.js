/**
 * Style-variation → activation-class detection shared by DesignSetGo's
 * layout container blocks (Section, Row, Grid).
 *
 * Style kits express overlay/hover effects as block-style variations
 * (`is-style-overlay-*`, `is-style-hover-{text,icon,button}-*`) whose
 * stylesheet supplies the color via CSS rather than an inline custom
 * property. `Section_Styles` (`includes/features/class-section-styles.php`)
 * mirrors any such variation registered for `core/group`/`core/columns`/
 * `core/column` onto Section, Row, and Grid alike — making a variation
 * selectable in the editor Styles panel for all three — so each block must
 * still emit a stable activation class so its own (and, for hover-icon/
 * hover-button, a child block's) CSS can key off it. An inline
 * `[style*=…]` attribute selector can't see a variation stylesheet's vars.
 *
 * Used by both edit.js and save.js for each consuming block so the editor
 * preview and saved markup stay byte-identical.
 */

/**
 * @param {string} [className] The block's `className` attribute value.
 * @return {boolean} True when an overlay style variation is present.
 */
export function hasOverlayStyleClass(className) {
	if (!className || typeof className !== 'string') {
		return false;
	}

	return className
		.split(/\s+/)
		.some((token) => token.startsWith('is-style-overlay-'));
}

/**
 * Hover style-variation family prefixes and the activation-class suffix each
 * maps to. Each family activates exactly one effect (so a variation that
 * sets only some of the hover vars never clobbers the others).
 */
const HOVER_VARIATION_FAMILIES = [
	{ prefix: 'is-style-hover-text-', suffix: 'has-hover-text' },
	{ prefix: 'is-style-hover-icon-', suffix: 'has-hover-icon' },
	{ prefix: 'is-style-hover-button-', suffix: 'has-hover-button' },
];

/**
 * Resolve the hover activation classes a block should emit for the style
 * variations present on its `className`.
 *
 * @param {string} [className]    The block's `className` attribute value.
 * @param {string} blockClassName The block's own class prefix (e.g.
 *                                `dsgo-stack`, `dsgo-flex`, `dsgo-grid`) —
 *                                activation classes are emitted as
 *                                `${blockClassName}--${suffix}`.
 * @return {string[]} Activation classes to add (possibly empty).
 */
export function hoverVariationClasses(className, blockClassName) {
	if (!className || typeof className !== 'string') {
		return [];
	}

	const tokens = className.split(/\s+/);

	return HOVER_VARIATION_FAMILIES.filter(({ prefix }) =>
		tokens.some((token) => token.startsWith(prefix))
	).map(({ suffix }) => `${blockClassName}--${suffix}`);
}
