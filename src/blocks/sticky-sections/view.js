/**
 * Sticky Sections - Frontend JavaScript
 *
 * Enhances the CSS-only sticky stacking effect:
 * 1. Assigns dynamic z-index to child sections (supplements CSS nth-child fallback)
 * 2. Adds shadow class to non-first sections for visual depth
 * 3. Skips initialization for reduced-motion and mobile viewports
 */

/**
 * Track initialized containers to prevent double-init
 */
const initialized = new WeakSet();

/**
 * Default mobile breakpoint (matches CSS media query)
 */
const MOBILE_BREAKPOINT = 781;

/**
 * Initialize all sticky sections instances on the page
 */
function initStickySections() {
	const containers = document.querySelectorAll('.dsgo-sticky-sections');

	if (!containers.length) {
		return;
	}

	// Check reduced motion preference — fall back to normal stacking
	const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

	if (motionQuery.matches) {
		return;
	}

	// Check mobile viewport
	if (window.innerWidth < MOBILE_BREAKPOINT) {
		return;
	}

	containers.forEach((container) => {
		if (initialized.has(container)) {
			return;
		}

		const sections = container.querySelectorAll(':scope > .dsgo-stack');

		if (sections.length < 2) {
			return;
		}

		initialized.add(container);

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
