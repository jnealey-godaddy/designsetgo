/**
 * Expanding Background Extension - Frontend Animation
 *
 * @package
 */

/* global requestAnimationFrame, IntersectionObserver, MutationObserver */

// Track active scroll listeners for cleanup
const activeElements = new Map();

// Track observed elements to prevent duplicate observations
const observedElements = new WeakSet();
let intersectionObserver = null;

// Store initial element positions to ensure consistent animation across viewport exits/re-entries
const elementInitialPositions = new WeakMap();

/**
 * Check if user prefers reduced motion
 *
 * @return {boolean} True if reduced motion is preferred
 */
function prefersReducedMotion() {
	return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Validate if a string is a valid CSS color value
 *
 * @param {string} color The color value to validate
 * @return {boolean} True if the color is valid
 */
function isValidColor(color) {
	if (!color || typeof color !== 'string') {
		return false;
	}

	// Check for common CSS color formats
	const validFormats = [
		/^#[0-9A-Fa-f]{3,8}$/, // Hex colors (#fff, #ffffff, #ffffffff)
		/^rgb\([^)]+\)$/, // RGB format
		/^rgba\([^)]+\)$/, // RGBA format
		/^hsl\([^)]+\)$/, // HSL format
		/^hsla\([^)]+\)$/, // HSLA format
		/^[a-z]+$/i, // Named colors (red, blue, etc.)
	];

	return validFormats.some((format) => format.test(color.trim()));
}

/**
 * Calculate scroll progress for an element
 *
 * @param {HTMLElement} element           The element to track
 * @param {number}      triggerOffset     Trigger offset percentage (0-100)
 * @param {number}      initialElementTop Initial element top position when tracking started
 * @return {number} Progress value between 0 and 1
 */
function calculateScrollProgress(element, triggerOffset, initialElementTop) {
	const rect = element.getBoundingClientRect();
	const elementHeight = rect.height;
	const elementTop = rect.top;
	const viewportHeight = window.innerHeight;

	// Calculate how much we've scrolled from the initial position
	const scrolledFromInitial = initialElementTop - elementTop;

	// If we haven't scrolled from initial position, return 0
	if (scrolledFromInitial <= 0) {
		return 0;
	}

	// Total distance to scroll through (element height + viewport height)
	const totalScrollDistance = elementHeight + viewportHeight;

	// Apply trigger offset
	const offsetDistance = (totalScrollDistance * triggerOffset) / 100;
	const adjustedDistance = scrolledFromInitial - offsetDistance;

	// Normalize to 0-1 range
	let progress = adjustedDistance / totalScrollDistance;

	// Clamp between 0 and 1
	progress = Math.max(0, Math.min(1, progress));

	return progress;
}

/**
 * Update expanding background effect
 *
 * @param {HTMLElement} element           The element with expanding background
 * @param {Object}      settings          Effect settings
 * @param {number}      initialElementTop Initial element top position
 */
function updateExpandingBackground(element, settings, initialElementTop) {
	const { color, initialSize, blur, speed, triggerOffset, completionPoint } =
		settings;

	// Calculate scroll progress
	let progress = calculateScrollProgress(
		element,
		triggerOffset,
		initialElementTop
	);

	// Scale progress so it reaches 100% at the user-defined completion point
	const completionFraction = Math.max(0.01, completionPoint / 100); // Prevent division by zero
	progress = Math.min(1, (progress / completionFraction) * speed);

	// Get element dimensions and position
	const rect = element.getBoundingClientRect();
	const maxDimension = Math.max(rect.width, rect.height);

	// Calculate viewport center position relative to element
	const viewportCenterY = window.innerHeight / 2;
	const elementTop = rect.top;
	const circleTopPosition = viewportCenterY - elementTop;

	// Calculate circle size (from initialSize to maxDimension * 1.5 for coverage)
	const targetSize = maxDimension * 1.5;
	const currentSize = initialSize + progress * (targetSize - initialSize);

	// Calculate blur (from initial blur to 0)
	const currentBlur = blur * (1 - progress);

	// Update CSS custom properties
	element.style.setProperty('--dsgo-expanding-bg-progress', progress);
	element.style.setProperty('--dsgo-expanding-bg-size', `${currentSize}px`);
	element.style.setProperty('--dsgo-expanding-bg-blur', `${currentBlur}px`);
	element.style.setProperty('--dsgo-expanding-bg-color', color);
	element.style.setProperty(
		'--dsgo-expanding-bg-top',
		`${circleTopPosition}px`
	);
}

/**
 * Setup scroll listener for an element
 *
 * @param {HTMLElement} element The element to track
 */
function setupScrollListener(element) {
	// Skip if already tracking or user prefers reduced motion
	if (activeElements.has(element) || prefersReducedMotion()) {
		return;
	}

	// Get settings from data attributes
	const rawColor = element.dataset.dsgoExpandingBgColor || '#e8e8e8';
	const settings = {
		color: isValidColor(rawColor) ? rawColor : '#e8e8e8', // Validate color for security
		initialSize:
			parseInt(element.dataset.dsgoExpandingBgInitialSize, 10) || 50,
		blur: parseInt(element.dataset.dsgoExpandingBgBlur, 10) || 30,
		speed: parseFloat(element.dataset.dsgoExpandingBgSpeed) || 1,
		triggerOffset:
			parseInt(element.dataset.dsgoExpandingBgTriggerOffset, 10) || 0,
		completionPoint:
			parseInt(element.dataset.dsgoExpandingBgCompletionPoint, 10) || 80,
	};

	// Track initial element position for progress calculation
	// Store position only once to maintain consistent animation across viewport exits/re-entries
	if (!elementInitialPositions.has(element)) {
		elementInitialPositions.set(
			element,
			element.getBoundingClientRect().top
		);
	}
	const initialElementTop = elementInitialPositions.get(element);

	// Animation frame request ID for throttling
	let rafId = null;

	// Scroll handler with requestAnimationFrame throttling
	const handleScroll = () => {
		if (rafId) {
			return;
		}

		rafId = requestAnimationFrame(() => {
			updateExpandingBackground(element, settings, initialElementTop);
			rafId = null;
		});
	};

	// Set initial state (visible at starting size with blur)
	updateExpandingBackground(element, settings, initialElementTop);

	// Add scroll listener
	window.addEventListener('scroll', handleScroll, { passive: true });

	// Store listener for cleanup
	activeElements.set(element, handleScroll);
}

/**
 * Remove scroll listener for an element
 *
 * @param {HTMLElement} element The element to stop tracking
 */
function removeScrollListener(element) {
	const handleScroll = activeElements.get(element);

	if (handleScroll) {
		window.removeEventListener('scroll', handleScroll);
		activeElements.delete(element);
	}
}

/**
 * Initialize expanding background for all elements
 */
function initExpandingBackgrounds() {
	const elements = document.querySelectorAll(
		'[data-dsgo-expanding-bg-enabled="true"]'
	);

	if (elements.length === 0) {
		return;
	}

	// Create intersection observer only once (reuse for all elements)
	if (!intersectionObserver) {
		intersectionObserver = new IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					if (entry.isIntersecting) {
						// Element entered viewport, start tracking
						setupScrollListener(entry.target);
					} else {
						// Element left viewport, stop tracking for performance
						removeScrollListener(entry.target);
					}
				});
			},
			{
				// Start tracking slightly before element enters viewport
				rootMargin: '100px 0px',
				threshold: 0,
			}
		);
	}

	// Observe all elements (but skip already-observed ones)
	elements.forEach((element) => {
		if (!observedElements.has(element)) {
			intersectionObserver.observe(element);
			observedElements.add(element);
		}
	});
}

/**
 * Setup MutationObserver to detect dynamically added elements
 */
function setupMutationObserver() {
	if (typeof window === 'undefined' || !('MutationObserver' in window)) {
		return;
	}

	// Ensure document.body exists before observing
	if (!document.body) {
		return;
	}

	const bodyObserver = new MutationObserver((mutations) => {
		let shouldReinit = false;

		mutations.forEach((mutation) => {
			mutation.addedNodes.forEach((node) => {
				if (
					node.nodeType === 1 &&
					(node.hasAttribute('data-dsgo-expanding-bg-enabled') ||
						node.querySelector('[data-dsgo-expanding-bg-enabled]'))
				) {
					shouldReinit = true;
				}
			});
		});

		if (shouldReinit) {
			initExpandingBackgrounds();
		}
	});

	bodyObserver.observe(document.body, {
		childList: true,
		subtree: true,
	});
}

/**
 * Initialize both expanding backgrounds and mutation observer
 */
function init() {
	initExpandingBackgrounds();
	setupMutationObserver();
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}

// Re-initialize after soft navigation (bfcache, AJAX)
document.addEventListener('dsgo-content-loaded', init);
