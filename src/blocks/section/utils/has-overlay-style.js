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
