/**
 * Scroll Slides - Frontend JavaScript
 * Handles scroll-pinned slideshow with crossfading transitions
 *
 * How it works:
 * 1. Wraps the container in a "pin spacer" div (height = slides x viewportHeight)
 * 2. Container becomes sticky (pinned at top of viewport)
 * 3. As user scrolls through the spacer, JS calculates a 0-1 progress value
 * 4. Progress determines which slide is active and drives opacity/transform transitions
 * 5. A nav is built dynamically from each slide's data-dsgo-nav-heading attribute
 * 6. Background layers crossfade per slide based on each slide's background-image
 */

import { setupDOM } from './frontend/setup-dom';
import { setupScrollEngine } from './frontend/scroll-engine';
import { setupTapEngine } from './frontend/tap-engine';

/**
 * Track initialized containers to prevent double-init
 */
const initialized = new WeakSet();

/**
 * Mobile breakpoint — matches the CSS media query
 */
const MOBILE_BREAKPOINT = '(max-width: 781px)';

/**
 * Initialize all scroll slides instances on the page
 */
function initScrollSlides() {
	const containers = document.querySelectorAll('.dsgo-scroll-slides');

	if (!containers.length) {
		return;
	}

	// Check reduced motion preference — show all slides stacked
	const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

	if (motionQuery.matches) {
		return;
	}

	const mobileQuery = window.matchMedia(MOBILE_BREAKPOINT);
	const isMobile = mobileQuery.matches;

	containers.forEach((container) => {
		if (initialized.has(container)) {
			return;
		}
		initialized.add(container);

		const slides = container.querySelectorAll('.dsgo-scroll-slide');

		if (slides.length < 2) {
			// Need at least 2 slides for the effect
			if (slides.length === 1) {
				slides[0].classList.add('is-active');
			}
			return;
		}

		const minHeight = container.dataset.dsgoMinHeight || '100vh';
		const maxHeight = container.dataset.dsgoMaxHeight || '';

		const controller = new AbortController();

		// === Build DOM: spacer (desktop only), nav, backgrounds ===
		setupDOM(container, slides, minHeight, maxHeight, isMobile);

		if (isMobile) {
			// === Tap engine — click nav to switch slides ===
			setupTapEngine(container, slides, controller);
		} else {
			// === Scroll engine — scroll-driven animation ===
			setupScrollEngine(container, slides, controller);
		}

		// Abort engine if user enables reduced motion mid-session
		motionQuery.addEventListener(
			'change',
			(e) => {
				if (e.matches) {
					controller.abort();
				}
			},
			{ signal: controller.signal }
		);
	});
}

/**
 * Initialize on DOM ready
 */
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initScrollSlides);
} else {
	initScrollSlides();
}

/**
 * Reinitialize on dynamic content changes (e.g., AJAX)
 */
document.addEventListener('scroll-slides:reinit', initScrollSlides);
document.addEventListener('dsgo-content-loaded', initScrollSlides);
