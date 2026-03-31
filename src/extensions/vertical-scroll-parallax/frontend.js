/**
 * Scroll Parallax - Frontend
 *
 * Handles scroll-based parallax effects on the frontend.
 * Supports vertical (up/down) and horizontal (left/right) movement,
 * and scroll-driven rotation (clockwise/counter-clockwise).
 *
 * @package
 * @since 1.0.0
 */

/* global IntersectionObserver, requestAnimationFrame */

import { BREAKPOINTS } from './constants';

/**
 * Get current device type based on viewport width
 *
 * @return {string} 'desktop', 'tablet', or 'mobile'
 */
function getDeviceType() {
	const width = window.innerWidth;
	if (width >= BREAKPOINTS.desktop) {
		return 'desktop';
	}
	if (width >= BREAKPOINTS.tablet) {
		return 'tablet';
	}
	return 'mobile';
}

/**
 * Check if effect should be applied on current device
 *
 * @param {HTMLElement} element Element with parallax
 * @return {boolean} Whether to apply effect
 */
function shouldApplyOnDevice(element) {
	const deviceType = getDeviceType();
	const enableDesktop = element.dataset.dsgoParallaxDesktop !== 'false';
	const enableTablet = element.dataset.dsgoParallaxTablet !== 'false';
	const enableMobile = element.dataset.dsgoParallaxMobile === 'true';

	switch (deviceType) {
		case 'desktop':
			return enableDesktop;
		case 'tablet':
			return enableTablet;
		case 'mobile':
			return enableMobile;
		default:
			return true;
	}
}

/**
 * Parse settings from data attributes
 *
 * @param {HTMLElement} element Element with parallax
 * @return {Object} Parsed settings
 */
function parseSettings(element) {
	return {
		direction: element.dataset.dsgoParallaxDirection || 'up',
		speed: parseInt(element.dataset.dsgoParallaxSpeed, 10) || 5,
		viewportStart:
			parseInt(element.dataset.dsgoParallaxViewportStart, 10) || 0,
		viewportEnd:
			parseInt(element.dataset.dsgoParallaxViewportEnd, 10) || 100,
		relativeTo: element.dataset.dsgoParallaxRelativeTo || 'viewport',
		rotateEnabled: element.dataset.dsgoParallaxRotateEnabled === 'true',
		rotateDirection: element.dataset.dsgoParallaxRotateDirection || 'cw',
		rotateSpeed: Number.isFinite(
			parseInt(element.dataset.dsgoParallaxRotateSpeed, 10)
		)
			? parseInt(element.dataset.dsgoParallaxRotateSpeed, 10)
			: 3,
	};
}

/**
 * Clamp a value between min and max
 *
 * @param {number} value Value to clamp
 * @param {number} min   Minimum value
 * @param {number} max   Maximum value
 * @return {number} Clamped value
 */
function clamp(value, min, max) {
	return Math.min(Math.max(value, min), max);
}

/**
 * Get an element's absolute top position from the document (layout position).
 *
 * Uses offsetTop chain instead of getBoundingClientRect() because offsetTop
 * is NOT affected by CSS transforms. This prevents a feedback loop where the
 * parallax transform shifts the bounding rect, causing the next frame to
 * calculate a different offset. This fix is critical for large elements like
 * sections where the feedback loop was dampening the parallax effect.
 *
 * @param {HTMLElement} element Target element
 * @return {number} Absolute top position in pixels from document top
 */
function getAbsoluteTop(element) {
	let top = 0;
	let el = element;
	while (el) {
		top += el.offsetTop;
		el = el.offsetParent;
	}
	return top;
}

/**
 * Calculate parallax offset for an element
 *
 * Uses a centered offset: elements reach their natural position (offset = 0)
 * at the midpoint of the configured scroll range defined by viewportStart and
 * viewportEnd. Before this midpoint, elements are offset in the opposite
 * direction; after, they move in the specified direction. With the default
 * range of 0-100, this midpoint corresponds to the element being centered
 * in the viewport.
 *
 * @param {HTMLElement} element        Element with parallax
 * @param {Object}      settings       Parsed settings
 * @param {number}      scrollY        Current scroll position
 * @param {number}      viewportHeight Viewport height
 * @return {Object} Object with x/y offset values in pixels and rotation in degrees
 */
function calculateParallaxOffset(element, settings, scrollY, viewportHeight) {
	const elementTop = getAbsoluteTop(element);
	const elementHeight = element.offsetHeight;

	// Calculate scroll range based on relativeTo setting
	let scrollStart, scrollEnd;

	if (settings.relativeTo === 'viewport') {
		// Effect relative to when element enters/exits viewport
		scrollStart = elementTop - viewportHeight;
		scrollEnd = elementTop + elementHeight;
	} else {
		// Effect relative to entire page
		scrollStart = 0;
		scrollEnd = document.body.scrollHeight - viewportHeight;
	}

	// Apply viewport range limits (user-defined start/end percentages)
	// Ensure viewportStart is not greater than viewportEnd
	const viewportStart = Math.min(
		settings.viewportStart,
		settings.viewportEnd
	);
	const viewportEnd = Math.max(settings.viewportStart, settings.viewportEnd);

	const totalRange = scrollEnd - scrollStart;
	const adjustedStart = scrollStart + (totalRange * viewportStart) / 100;
	const adjustedEnd = scrollStart + (totalRange * viewportEnd) / 100;

	// Calculate progress within range (0 to 1), guard against division by zero
	const rangeDiff = adjustedEnd - adjustedStart;
	const progress =
		rangeDiff === 0
			? 0
			: clamp((scrollY - adjustedStart) / rangeDiff, 0, 1);

	// Convert speed (0-10) to max pixel offset
	// Speed 10 = 200px max movement, Speed 0 = 0px
	const maxOffset = settings.speed * 20;

	// Centered offset: natural position (0) at midpoint of scroll range.
	// Progress 0   = entering viewport = maxOffset in opposite direction
	// Progress 0.5 = center of viewport = natural position (no offset)
	// Progress 1   = exiting viewport  = maxOffset in specified direction
	const centeredProgress = progress - 0.5;

	let offsetX = 0;
	let offsetY = 0;

	switch (settings.direction) {
		case 'up':
			offsetY = -(centeredProgress * maxOffset);
			break;
		case 'down':
			offsetY = centeredProgress * maxOffset;
			break;
		case 'left':
			offsetX = -(centeredProgress * maxOffset);
			break;
		case 'right':
			offsetX = centeredProgress * maxOffset;
			break;
		default:
			offsetY = -(centeredProgress * maxOffset);
	}

	// Calculate rotation if enabled.
	// Uses the same centered approach as movement: rotation is 0° at the
	// midpoint and swings symmetrically in both directions (e.g. -180° to
	// +180° at speed 10). This keeps rotation consistent with translate behavior.
	let rotation = 0;
	if (settings.rotateEnabled) {
		const maxRotation = settings.rotateSpeed * 36; // speed 10 = 360°
		rotation = centeredProgress * maxRotation;
		if (settings.rotateDirection === 'ccw') {
			rotation = -rotation;
		}
	}

	return { x: offsetX, y: offsetY, rotation };
}

/**
 * Initialize parallax effects
 */
// Module-scope handler refs for teardown (prevents listener accumulation on soft nav)
let parallaxScrollHandler = null;
let parallaxResizeHandler = null;

function teardownParallax() {
	if (parallaxScrollHandler) {
		window.removeEventListener('scroll', parallaxScrollHandler);
		parallaxScrollHandler = null;
	}
	if (parallaxResizeHandler) {
		window.removeEventListener('resize', parallaxResizeHandler);
		parallaxResizeHandler = null;
	}
}

function initParallax() {
	// Prevent multiple initializations
	if (window.dsgoParallaxInitialized) {
		return;
	}
	window.dsgoParallaxInitialized = true;

	// Check for reduced motion preference
	const prefersReducedMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	if (prefersReducedMotion) {
		return;
	}

	// Find all elements with parallax enabled
	const parallaxElements = document.querySelectorAll(
		'[data-dsgo-parallax-enabled="true"]'
	);

	if (parallaxElements.length === 0) {
		return;
	}

	// Cache viewport height
	let viewportHeight = window.innerHeight;
	let ticking = false;

	// Track visible elements for performance
	const visibleElements = new Set();

	// Create IntersectionObserver to track visibility
	const observer = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting) {
					visibleElements.add(entry.target);
				} else {
					visibleElements.delete(entry.target);
					// Reset transform when out of view
					entry.target.style.transform = '';
				}
			});
		},
		{
			rootMargin: '50% 0px 50% 0px', // Extend observation area (top, right, bottom, left)
			threshold: 0,
		}
	);

	// Observe all parallax elements
	parallaxElements.forEach((element) => {
		// Add will-change for performance
		element.style.willChange = 'transform';
		observer.observe(element);
	});

	/**
	 * Update parallax transforms
	 */
	function updateParallax() {
		const scrollY = window.scrollY;

		visibleElements.forEach((element) => {
			// Check if effect should apply on current device
			if (!shouldApplyOnDevice(element)) {
				element.style.transform = '';
				return;
			}

			const settings = parseSettings(element);
			const offset = calculateParallaxOffset(
				element,
				settings,
				scrollY,
				viewportHeight
			);

			// Apply transform using translate3d for GPU acceleration
			// Compose with rotation when enabled
			let transform = `translate3d(${offset.x}px, ${offset.y}px, 0)`;
			if (offset.rotation !== 0) {
				transform += ` rotate(${offset.rotation}deg)`;
			}
			element.style.transform = transform;
		});

		ticking = false;
	}

	/**
	 * Request animation frame throttling
	 */
	function requestTick() {
		if (!ticking) {
			requestAnimationFrame(updateParallax);
			ticking = true;
		}
	}

	// Store handlers at module scope so they can be torn down on re-init
	parallaxScrollHandler = requestTick;

	let resizeTimeout;
	parallaxResizeHandler = () => {
		clearTimeout(resizeTimeout);
		resizeTimeout = setTimeout(() => {
			viewportHeight = window.innerHeight;
			requestTick();
		}, 150);
	};

	// Add scroll listener with passive flag for performance
	window.addEventListener('scroll', parallaxScrollHandler, { passive: true });

	// Update viewport height on resize (debounced)
	window.addEventListener('resize', parallaxResizeHandler, { passive: true });

	// Initial update
	requestTick();
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initParallax);
} else {
	initParallax();
}

// Re-initialize after soft navigation (bfcache, AJAX)
document.addEventListener('dsgo-content-loaded', () => {
	// Tear down previous scroll/resize listeners before re-initializing
	teardownParallax();
	delete window.dsgoParallaxInitialized;
	initParallax();
});
