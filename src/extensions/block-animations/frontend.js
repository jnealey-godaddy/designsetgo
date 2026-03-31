/**
 * Block Animations - Frontend JavaScript
 *
 * Handles animation triggers and execution on the frontend
 *
 * @package
 * @since 1.0.0
 */

/**
 * Initialize animations for all animated elements on the page.
 */
function initBlockAnimations() {
	// Skip all animation setup when user prefers reduced motion
	const prefersReducedMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	if (prefersReducedMotion) {
		return;
	}

	const animatedElements = document.querySelectorAll(
		'[data-dsgo-animation-enabled="true"]'
	);

	if (!animatedElements.length) {
		return;
	}

	// Process each animated element
	animatedElements.forEach((element) => {
		// Prevent duplicate initialization
		if (element.dataset.dsgoAnimationInitialized) {
			return;
		}
		element.dataset.dsgoAnimationInitialized = 'true';

		const trigger = element.dataset.dsgoAnimationTrigger || 'scroll';
		const duration = element.dataset.dsgoAnimationDuration || '600';
		const delay = element.dataset.dsgoAnimationDelay || '0';
		const easing = element.dataset.dsgoAnimationEasing || 'ease-out';

		// Set animation styles
		element.style.animationDuration = `${duration}ms`;
		element.style.animationDelay = `${delay}ms`;
		element.style.animationTimingFunction = easing;

		// Apply trigger-specific behavior
		switch (trigger) {
			case 'load':
				animateOnLoad(element);
				break;
			case 'scroll':
				animateOnScroll(element);
				break;
			case 'hover':
				animateOnHover(element);
				break;
			case 'click':
				animateOnClick(element);
				break;
			default:
				animateOnScroll(element);
		}
	});
}

document.addEventListener('DOMContentLoaded', initBlockAnimations);
document.addEventListener('dsgo-content-loaded', initBlockAnimations);

/**
 * Animate element on page load
 *
 * @param {HTMLElement} element Element to animate
 */
function animateOnLoad(element) {
	// Trigger entrance animation immediately on page load
	// eslint-disable-next-line no-undef
	requestAnimationFrame(() => {
		element.classList.add('dsgo-entrance-active');
	});
}

/**
 * Shared IntersectionObserver instances keyed by offset value.
 * Reusing observers across elements with the same offset reduces overhead.
 *
 * @type {Map<number, IntersectionObserver>}
 */
const scrollObservers = new Map();

/**
 * Per-element animation state for scroll-triggered animations.
 *
 * @type {WeakMap<HTMLElement, Object>}
 */
const elementState = new WeakMap();

/**
 * Get or create a shared IntersectionObserver for a given offset.
 *
 * @param {number} offset Viewport offset in pixels
 * @return {IntersectionObserver} Shared observer instance
 */
function getScrollObserver(offset) {
	if (scrollObservers.has(offset)) {
		return scrollObservers.get(offset);
	}

	// eslint-disable-next-line no-undef
	const observer = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				const el = entry.target;
				const state = elementState.get(el);

				if (!state) {
					return;
				}

				const currentRatio = entry.intersectionRatio;
				const isEntering =
					entry.isIntersecting && !state.wasIntersecting;
				const isStartingToLeave =
					state.wasIntersecting &&
					currentRatio < state.previousRatio &&
					currentRatio < 0.8;

				if (isEntering) {
					// Element is entering viewport - play entrance animation
					if (!state.hasAnimated || state.shouldRepeat) {
						// Clear any pending exit animation
						if (state.animationTimeout) {
							clearTimeout(state.animationTimeout);
							state.animationTimeout = null;
						}

						// Force reset state for fast scroll handling
						state.isAnimating = false;
						el.classList.remove('dsgo-exit-active');
						el.classList.add('dsgo-entrance-active');
						state.hasAnimated = true;

						// Set animating flag and schedule reset
						state.isAnimating = true;
						state.animationTimeout = setTimeout(() => {
							state.isAnimating = false;
							state.animationTimeout = null;
						}, state.duration);
					}

					// Unobserve if only animating once and no exit animation
					if (state.once && !state.hasExitAnimation) {
						observer.unobserve(el);
						elementState.delete(el);
					}
				} else if (
					isStartingToLeave &&
					state.hasExitAnimation &&
					state.shouldRepeat &&
					state.hasAnimated
				) {
					// Element is starting to leave viewport (80% visible threshold)
					// Play exit animation early so user sees it
					if (!state.isAnimating) {
						if (state.animationTimeout) {
							clearTimeout(state.animationTimeout);
							state.animationTimeout = null;
						}

						state.isAnimating = true;
						el.classList.remove('dsgo-entrance-active');
						el.classList.add('dsgo-exit-active');

						// Reset after exit animation completes
						state.animationTimeout = setTimeout(() => {
							el.classList.remove('dsgo-exit-active');
							state.isAnimating = false;
							state.animationTimeout = null;
						}, state.duration);
					}
				} else if (
					!entry.isIntersecting &&
					state.wasIntersecting &&
					!state.hasExitAnimation
				) {
					// Element left viewport and no exit animation - just clean up
					el.classList.remove('dsgo-entrance-active');
				}

				state.wasIntersecting = entry.isIntersecting;
				state.previousRatio = currentRatio;
			});
		},
		{
			rootMargin: `-${offset}px`,
			threshold: [0, 0.5, 0.8], // Entry, midpoint, and exit-start detection
		}
	);

	scrollObservers.set(offset, observer);
	return observer;
}

/**
 * Animate element when it enters viewport
 *
 * @param {HTMLElement} element Element to animate
 */
function animateOnScroll(element) {
	const rawOffset = parseInt(element.dataset.dsgoAnimationOffset);
	const offset = Number.isFinite(rawOffset) ? rawOffset : 100;
	// Default to true (animate once) when attribute is absent
	const once = element.dataset.dsgoAnimationOnce !== 'false';
	const hasExitAnimation =
		element.dataset.dsgoExitAnimation &&
		element.dataset.dsgoExitAnimation !== '';
	const duration = parseInt(element.dataset.dsgoAnimationDuration) || 600;

	// Store per-element state in a WeakMap for automatic GC cleanup
	elementState.set(element, {
		once,
		hasExitAnimation,
		duration,
		shouldRepeat: hasExitAnimation || !once,
		hasAnimated: false,
		isAnimating: false,
		wasIntersecting: false,
		previousRatio: 0,
		animationTimeout: null,
	});

	const observer = getScrollObserver(offset);
	observer.observe(element);
}

/**
 * Animate element on hover
 *
 * @param {HTMLElement} element Element to animate
 */
function animateOnHover(element) {
	const duration = parseInt(element.dataset.dsgoAnimationDuration) || 600;
	const hasExitAnimation =
		element.dataset.dsgoExitAnimation &&
		element.dataset.dsgoExitAnimation !== '';
	let isAnimating = false;

	element.addEventListener('mouseenter', () => {
		if (!isAnimating) {
			isAnimating = true;
			element.classList.remove('dsgo-exit-active');
			element.classList.add('dsgo-entrance-active');

			// Reset after animation completes
			setTimeout(() => {
				isAnimating = false;
			}, duration);
		}
	});

	element.addEventListener('mouseleave', () => {
		// Trigger exit animation when mouse leaves
		if (hasExitAnimation && !isAnimating) {
			isAnimating = true;
			element.classList.remove('dsgo-entrance-active');
			element.classList.add('dsgo-exit-active');

			setTimeout(() => {
				element.classList.remove('dsgo-exit-active');
				isAnimating = false;
			}, duration);
		} else {
			element.classList.remove('dsgo-entrance-active');
		}
	});
}

/**
 * Animate element on click
 *
 * @param {HTMLElement} element Element to animate
 */
function animateOnClick(element) {
	const duration = parseInt(element.dataset.dsgoAnimationDuration) || 600;
	let isAnimating = false;

	element.addEventListener('click', (e) => {
		// Don't prevent default or stop propagation for links/buttons
		if (e.target.closest?.('a, button')) {
			return;
		}

		if (!isAnimating) {
			isAnimating = true;
			element.classList.remove('dsgo-exit-active');
			element.classList.add('dsgo-entrance-active');

			// Reset after animation completes
			setTimeout(() => {
				element.classList.remove('dsgo-entrance-active');
				isAnimating = false;
			}, duration + 100);
		}
	});

	// Make element keyboard accessible if clickable
	if (!element.hasAttribute('tabindex')) {
		element.setAttribute('tabindex', '0');
		element.setAttribute('role', 'button');
	}

	// Handle keyboard activation
	element.addEventListener('keydown', (e) => {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			element.click();
		}
	});
}
