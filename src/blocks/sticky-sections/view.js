/**
 * Sticky Sections - Frontend JavaScript
 *
 * Enhances the CSS-only sticky stacking effect:
 * 1. Assigns dynamic z-index to child sections (supplements CSS nth-child fallback)
 * 2. Adds shadow class to non-first sections for visual depth
 *
 * It runs regardless of viewport or motion preference: the stylesheet
 * neutralises sticky positioning, z-index (!important) and the shadow on
 * mobile, under reduced motion and in print, so resizing across the
 * breakpoint or toggling reduced motion needs no re-initialisation.
 * The work is idempotent, so reinit events can safely run it again.
 */

/**
 * Initialize all sticky sections instances on the page
 */
function initStickySections() {
	const containers = document.querySelectorAll('.dsgo-sticky-sections');

	if (!containers.length) {
		return;
	}

	containers.forEach((container) => {
		const sections = container.querySelectorAll(':scope > .dsgo-stack');

		if (sections.length < 2) {
			return;
		}

		// Assign z-index dynamically (more robust than CSS nth-child for edge cases)
		sections.forEach((section, index) => {
			section.style.zIndex = index + 1;

			// Add shadow class to non-first sections for depth effect
			if (index > 0) {
				section.classList.add('dsgo-sticky-sections__stacking');
			}
		});
	});
}

/**
 * Initialize on DOM ready
 */
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initStickySections);
} else {
	initStickySections();
}

/**
 * Reinitialize on dynamic content changes (e.g., AJAX)
 */
document.addEventListener('sticky-sections:reinit', initStickySections);
document.addEventListener('dsgo-content-loaded', initStickySections);
