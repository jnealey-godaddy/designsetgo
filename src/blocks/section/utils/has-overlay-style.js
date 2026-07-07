/**
 * Section overlay style-variation detection.
 *
 * A section renders its `::before` overlay whenever the
 * `dsgo-stack--has-overlay` class is present. Historically that class was
 * emitted only when the `overlayColor` attribute was set (which also injects
 * an inline `--dsgo-overlay-color`). Style kits now express the overlay as a
 * block-style variation (`is-style-overlay-dark`, and any future
 * `is-style-overlay-*`) that supplies the overlay color via CSS. When such a
 * variation is applied, the block must still emit the overlay class so the
 * pseudo-element renders — the color comes from the variation's stylesheet
 * rather than an inline custom property.
 *
 * Detecting the whole `is-style-overlay-` family (not just `-dark`) means new
 * overlay variations light up without another plugin change.
 *
 * Used by both edit.js and save.js so the editor preview and saved markup stay
 * byte-identical.
 *
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
 * Hover style-variation → activation-class map.
 *
 * The section's hover-text / child-icon / child-button hover colors override a
 * base value with `!important`, so their CSS is gated to "only when actually
 * set" — historically via an inline-`style` attribute selector, which a style
 * variation's stylesheet can't satisfy. To let a variation drive them, the
 * block emits a stable activation class the CSS can key off, mirroring the
 * overlay pattern.
 *
 * Each family activates exactly one effect (so a variation that sets only some
 * of the hover vars never clobbers the others). The section hover *background*
 * is intentionally absent — it is an additive overlay that is already safe when
 * unset, so it needs no activation class and works with any variation.
 */
const HOVER_VARIATION_FAMILIES = [
	{ prefix: 'is-style-hover-text-', className: 'dsgo-stack--has-hover-text' },
	{ prefix: 'is-style-hover-icon-', className: 'dsgo-stack--has-hover-icon' },
	{
		prefix: 'is-style-hover-button-',
		className: 'dsgo-stack--has-hover-button',
	},
];

/**
 * Resolve the hover activation classes a section should emit for the style
 * variations present on its `className`.
 *
 * Used by both edit.js and save.js so the editor preview and saved markup stay
 * byte-identical.
 *
 * @param {string} [className] The block's `className` attribute value.
 * @return {string[]} Activation classes to add (possibly empty).
 */
export function hoverVariationClasses(className) {
	if (!className || typeof className !== 'string') {
		return [];
	}

	const tokens = className.split(/\s+/);

	return HOVER_VARIATION_FAMILIES.filter(({ prefix }) =>
		tokens.some((token) => token.startsWith(prefix))
	).map(({ className: activationClass }) => activationClass);
}
