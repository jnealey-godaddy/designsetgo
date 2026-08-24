/**
 * Text Reveal Extension - Frontend JavaScript
 *
 * Handles scroll-triggered text reveal animation on the frontend.
 * Splits text into spans and progressively reveals them based on scroll position.
 *
 * @package
 * @since 1.0.0
 */

/* global IntersectionObserver, requestAnimationFrame */

export { UNIT_CLASS, wrapTextNodes } from './splitter';
import { UNIT_CLASS, wrapTextNodes } from './splitter';

// Store observers for cleanup
const activeObservers = new WeakMap();

/**
 * Check if user prefers reduced motion
 *
 * @return {boolean} True if user prefers reduced motion
 */
function prefersReducedMotion() {
	return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Scroll distance still available below the current position.
 *
 * @return {number} Pixels of remaining document scroll, never negative.
 */
function remainingScroll() {
	const doc = document.documentElement;
	const maxScroll = Math.max(
		0,
		Math.max(
			doc.scrollHeight,
			document.body ? document.body.scrollHeight : 0
		) - window.innerHeight
	);

	return Math.max(0, maxScroll - (window.scrollY || doc.scrollTop || 0));
}

/**
 * Update reveal progress based on scroll position
 *
 * @param {HTMLElement} element    The text reveal element
 * @param {NodeList}    spans      All span units
 * @param {number}      totalUnits Total number of units
 */
export function updateRevealProgress(element, spans, totalUnits) {
	const rect = element.getBoundingClientRect();
	const viewportHeight = window.innerHeight;

	// Calculate element's position relative to viewport
	// Progress: 0 when element enters viewport, 1 when element center reaches viewport center
	const elementTop = rect.top;
	const elementHeight = rect.height;

	// Start revealing slightly after element enters viewport (20% into viewport)
	const scrollStart = viewportHeight * 0.8; // Element starts reveal at 80% down viewport

	// Calculate progress based on element center position
	const elementCenter = elementTop + elementHeight / 2;

	// Finish revealing when the element's centre reaches the viewport centre -
	// unless the page runs out of scroll first. Text near the bottom of a
	// document, or on a page too short to scroll at all, can never travel that
	// far, and a reveal that never completes leaves the "Fade & Rise" effect's
	// units permanently at opacity 0: invisible copy, not a missing flourish.
	// So when the natural finish line is out of reach, it moves down to the
	// highest position the element can actually get to. Centre and remaining
	// scroll fall at the same rate, so that position is fixed rather than a
	// target that recedes with every frame.
	const highestReachableCenter = elementCenter - remainingScroll();
	const scrollEnd = Math.max(viewportHeight / 2, highestReachableCenter);

	const scrollRange = scrollStart - scrollEnd;

	// Progress from 0 (just entered) to 1 (fully revealed). A non-positive
	// range means the element is already as far up as it will ever get.
	let progress =
		scrollRange > 0 ? (scrollStart - elementCenter) / scrollRange : 1;

	// Clamp progress between 0 and 1
	progress = Math.max(0, Math.min(1, progress));

	// Determine how many units should be revealed
	const revealCount = Math.floor(progress * totalUnits);

	// Update span classes
	spans.forEach((span, index) => {
		if (index < revealCount) {
			span.classList.add('is-revealed');
		} else {
			span.classList.remove('is-revealed');
		}
	});
}

/**
 * Initialize text reveal for an element
 *
 * @param {HTMLElement} element The element with text reveal enabled
 */
function initTextReveal(element) {
	// Prevent double initialization
	if (element.dataset.dsgoTextRevealInitialized === 'true') {
		return;
	}

	// Get settings from data attributes
	const revealColor = element.dataset.dsgoTextRevealColor || '#2563eb';
	const splitMode = element.dataset.dsgoTextRevealSplitMode || 'word';
	const transition = element.dataset.dsgoTextRevealTransition || 150;
	const effect = element.dataset.dsgoTextRevealEffect || 'color';

	// The effect decides what each unit does once it is revealed; the reveal
	// order itself is the same scroll-progress walk either way.
	element.classList.add(`dsgo-text-reveal--${effect}`);

	// Set CSS custom properties (base color inherits from the block's text color)
	element.style.setProperty('--dsgo-text-reveal-color', revealColor);
	element.style.setProperty(
		'--dsgo-text-reveal-transition',
		`${transition}ms`
	);

	// Split text into spans
	wrapTextNodes(element, splitMode);

	// Get all spans
	const spans = element.querySelectorAll(`.${UNIT_CLASS}`);
	const totalUnits = spans.length;

	if (totalUnits === 0) {
		return;
	}

	// Mark as initialized
	element.dataset.dsgoTextRevealInitialized = 'true';

	// If user prefers reduced motion, reveal all immediately
	if (prefersReducedMotion()) {
		spans.forEach((span) => span.classList.add('is-revealed'));
		return;
	}

	// Scroll handler with requestAnimationFrame throttling
	let ticking = false;
	const scrollHandler = () => {
		if (!ticking) {
			requestAnimationFrame(() => {
				updateRevealProgress(element, spans, totalUnits);
				ticking = false;
			});
			ticking = true;
		}
	};

	// Set up Intersection Observer to only track scroll when visible
	const observer = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting) {
					// Element is visible - start tracking scroll
					window.addEventListener('scroll', scrollHandler, {
						passive: true,
					});
					// Initial update
					scrollHandler();
				} else {
					// Element is not visible - stop tracking scroll
					window.removeEventListener('scroll', scrollHandler);
				}
			});
		},
		{
			threshold: [0, 0.1],
			rootMargin: '50px 0px 50px 0px', // Start slightly before entering viewport
		}
	);

	// Store observer for cleanup
	activeObservers.set(element, observer);
	observer.observe(element);
}

/**
 * Destroy text reveal for an element (cleanup)
 *
 * @param {HTMLElement} element The element to clean up
 */
function destroyTextReveal(element) {
	const observer = activeObservers.get(element);
	if (observer) {
		observer.disconnect();
		activeObservers.delete(element);
	}
}

/**
 * Initialize all text reveal elements on page load
 */
function initAllTextReveal() {
	// Prevent multiple initializations
	if (window.dsgoTextRevealInitialized) {
		return;
	}
	window.dsgoTextRevealInitialized = true;

	const elements = document.querySelectorAll(
		'[data-dsgo-text-reveal-enabled="true"]'
	);

	if (!elements.length) {
		return;
	}

	elements.forEach(initTextReveal);
}

/**
 * Re-initialize text reveal (for dynamic content)
 * Can be called from external scripts: window.dsgoTextReveal.reinit()
 * Only initializes elements that haven't been initialized yet
 */
function reinitTextReveal() {
	const elements = document.querySelectorAll(
		'[data-dsgo-text-reveal-enabled="true"]:not([data-dsgo-text-reveal-initialized="true"])'
	);
	elements.forEach(initTextReveal);
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initAllTextReveal);
document.addEventListener('dsgo-content-loaded', reinitTextReveal);

// Expose functions for external use
window.dsgoTextReveal = {
	reinit: reinitTextReveal,
	init: initTextReveal,
	destroy: destroyTextReveal,
};
