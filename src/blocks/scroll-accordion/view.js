/**
 * Scroll Accordion - Frontend JavaScript
 * Handles sticky stacking card effect with scaling animation
 * Based on scroll position within page
 */

/* global requestAnimationFrame, cancelAnimationFrame */

const activeAccordions = new Map();

function cleanupDisconnectedAccordions() {
	activeAccordions.forEach((cleanup, accordion) => {
		if (!accordion.isConnected) {
			cleanup();
		}
	});
}

/**
 * Initialize all scroll accordions on the page
 */
function initScrollAccordions() {
	cleanupDisconnectedAccordions();
	const accordions = document.querySelectorAll('.dsgo-scroll-accordion');

	if (!accordions.length) {
		return;
	}

	// Check if user prefers reduced motion
	const prefersReducedMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	accordions.forEach((accordion) => {
		// Prevent duplicate initialization (avoids listener accumulation on soft nav)
		if (accordion.hasAttribute('data-dsgo-initialized')) {
			return;
		}
		accordion.setAttribute('data-dsgo-initialized', 'true');

		// Get all items
		const items = accordion.querySelectorAll('.dsgo-scroll-accordion-item');

		if (!items.length) {
			return;
		}

		// If reduced motion is preferred, don't animate
		if (prefersReducedMotion) {
			return;
		}

		// Performance: Cache viewport dimensions (updated on resize only)
		let viewportHeight = window.innerHeight;
		let viewportCenter = viewportHeight / 2;

		/**
		 * Update cached viewport dimensions
		 * Performance optimization: Only called on resize
		 */
		function updateViewportDimensions() {
			viewportHeight = window.innerHeight;
			viewportCenter = viewportHeight / 2;
		}

		// Track scroll position and apply scaling
		let ticking = false;
		let frameId = null;

		function updateCards() {
			// Performance: Use cached viewport dimensions
			items.forEach((item) => {
				// getBoundingClientRect is necessary here as positions change on scroll
				const itemRect = item.getBoundingClientRect();

				// Calculate how far the item has scrolled relative to viewport center
				const distanceFromCenter = itemRect.top - viewportCenter;

				// Items below center should be scaled down
				// Scale decreases as items approach the center from below
				if (distanceFromCenter > 0) {
					// Item is below center - scale based on distance
					const scaleValue = Math.max(
						0.85,
						1 - (distanceFromCenter / viewportHeight) * 0.3
					);
					item.style.transform = `scale(${scaleValue})`;
				} else {
					// Item is at or above center - full scale
					item.style.transform = 'scale(1)';
				}
			});

			ticking = false;
			frameId = null;
		}

		// Throttle scroll events with requestAnimationFrame
		function requestTick() {
			if (!accordion.isConnected) {
				cleanup();
				return;
			}
			if (!ticking) {
				frameId = requestAnimationFrame(updateCards);
				ticking = true;
			}
		}

		// Handle resize with dimension recalculation
		let resizeTimer;
		function handleResize() {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(() => {
				// Performance: Recalculate viewport dimensions on resize
				updateViewportDimensions();
				requestTick();
			}, 150);
		}

		// Listen for scroll and resize events
		window.addEventListener('scroll', requestTick, { passive: true });
		window.addEventListener('resize', handleResize, { passive: true });
		const cleanup = () => {
			window.removeEventListener('scroll', requestTick);
			window.removeEventListener('resize', handleResize);
			clearTimeout(resizeTimer);
			if (
				null !== frameId &&
				typeof cancelAnimationFrame === 'function'
			) {
				cancelAnimationFrame(frameId);
			}
			activeAccordions.delete(accordion);
		};
		activeAccordions.set(accordion, cleanup);

		// Initial check
		updateCards();
	});
}

/**
 * Initialize on DOM ready
 */
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initScrollAccordions);
} else {
	// DOM is already ready
	initScrollAccordions();
}

/**
 * Reinitialize on dynamic content changes (e.g., AJAX, soft navigation)
 */
document.addEventListener('scroll-accordion:reinit', initScrollAccordions);
document.addEventListener('dsgo-content-loaded', initScrollAccordions);
