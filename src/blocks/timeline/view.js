/**
 * Timeline Block - Frontend JavaScript
 * Scroll-triggered animations with IntersectionObserver
 * Follows WordPress best practices and respects prefers-reduced-motion
 */

/* global IntersectionObserver */

document.addEventListener('DOMContentLoaded', function () {
	initTimelines();
});
document.addEventListener('dsgo-content-loaded', initTimelines);

// Check for reduced motion preference
const prefersReducedMotion = window.matchMedia(
	'(prefers-reduced-motion: reduce)'
).matches;

/**
 * Initialize all timeline blocks on the page
 */
function initTimelines() {
	const timelines = document.querySelectorAll('.dsgo-timeline');

	timelines.forEach((timeline) => {
		// Prevent duplicate initialization
		if (timeline.hasAttribute('data-dsgo-initialized')) {
			return;
		}
		timeline.setAttribute('data-dsgo-initialized', 'true');

		// Check if animations are enabled
		const animateEnabled = timeline.getAttribute('data-animate') === 'true';

		if (!animateEnabled || prefersReducedMotion) {
			// Make all items visible immediately
			showAllItems(timeline);
			return;
		}

		// Get animation settings from data attributes with NaN validation
		const parsedDuration = parseInt(
			timeline.getAttribute('data-animation-duration') || '600',
			10
		);
		const animationDuration = Number.isNaN(parsedDuration)
			? 600
			: parsedDuration;

		const parsedDelay = parseInt(
			timeline.getAttribute('data-stagger-delay') || '100',
			10
		);
		const staggerDelay = Number.isNaN(parsedDelay) ? 100 : parsedDelay;

		// Initialize scroll animation
		initScrollAnimation(timeline, animationDuration, staggerDelay);
	});
}

/**
 * Show all timeline items immediately (no animation)
 * @param {HTMLElement} timeline - The timeline container element
 */
function showAllItems(timeline) {
	const items = timeline.querySelectorAll('.dsgo-timeline-item');
	items.forEach((item) => {
		item.classList.add('dsgo-timeline-item--visible');
		item.style.opacity = '1';
		item.style.transform = 'none';
	});
}

/**
 * Initialize scroll-triggered animations using IntersectionObserver
 * @param {HTMLElement} timeline          - The timeline container element
 * @param {number}      animationDuration - Animation duration in milliseconds
 * @param {number}      staggerDelay      - Delay between each item animation
 */
function initScrollAnimation(timeline, animationDuration, staggerDelay) {
	const items = timeline.querySelectorAll('.dsgo-timeline-item');

	// Create intersection observer
	const observerOptions = {
		root: null, // Use viewport
		rootMargin: '0px 0px -10% 0px', // Trigger slightly before item is fully visible
		threshold: 0.1, // Trigger when 10% of item is visible
	};

	const observer = new IntersectionObserver((entries) => {
		entries.forEach((entry) => {
			if (entry.isIntersecting) {
				const item = entry.target;

				// Calculate stagger delay based on order
				const index = Array.from(items).indexOf(item);
				const delay = index * staggerDelay;

				// Apply staggered animation
				setTimeout(() => {
					animateItemIn(item, animationDuration);
					// Once animated, stop observing this item
					observer.unobserve(item);
				}, delay);
			}
		});
	}, observerOptions);

	// Observe all timeline items
	items.forEach((item) => {
		// Check if item is already in viewport on load
		const rect = item.getBoundingClientRect();
		const isInViewport = rect.top < window.innerHeight && rect.bottom >= 0;

		if (isInViewport) {
			// Animate immediately with stagger
			const index = Array.from(items).indexOf(item);
			const delay = index * staggerDelay;

			setTimeout(() => {
				animateItemIn(item, animationDuration);
			}, delay);
		} else {
			// Observe for scroll
			observer.observe(item);
		}
	});
}

/**
 * Animate a single timeline item into view
 * @param {HTMLElement} item     - The timeline item to animate
 * @param {number}      duration - Animation duration in milliseconds
 */
function animateItemIn(item, duration) {
	// Add visible class to trigger CSS animation
	item.classList.add('dsgo-timeline-item--visible');

	// Set animation duration via CSS custom property
	item.style.setProperty(
		'--dsgo-timeline-animation-duration',
		`${duration}ms`
	);
}

/**
 * Re-initialize timelines (useful for dynamic content)
 * Can be called from external scripts if needed
 */
window.dsgoReinitTimelines = function () {
	// Remove initialization flags
	document
		.querySelectorAll('.dsgo-timeline[data-dsgo-initialized]')
		.forEach((timeline) => {
			timeline.removeAttribute('data-dsgo-initialized');
		});

	// Re-initialize
	initTimelines();
};
