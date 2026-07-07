/**
 * overlayOpacityFraction
 *
 * Convert a percent overlay-opacity attribute (0–100) into a CSS opacity
 * fraction (0–1). Out-of-range values are clamped to [0, 100] and non-finite
 * values fall back to the 80% default.
 *
 * Shared by scroll-slides save.js (frontend markup) and edit.js (editor
 * preview) so the two paths can't drift. The PHP render path
 * (src/blocks/scroll-slides/render.php) mirrors this same clamp/fallback and
 * must be kept in sync manually.
 *
 * @param {number} percent Opacity as a percentage (0–100).
 * @return {number} Opacity as a fraction in [0, 1].
 */
export function overlayOpacityFraction(percent) {
	const clamped = Number.isFinite(percent)
		? Math.min(100, Math.max(0, percent))
		: 80;

	return clamped / 100;
}
