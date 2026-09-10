/**
 * Scroll Accordion Block - Frontend Unit Tests
 *
 * Tests for the scroll accordion view.js frontend script.
 * Since view.js has no exports (side-effect module), each test uses
 * jest.isolateModules() to re-require a fresh instance.
 *
 * @package
 */

/**
 * Create a scroll accordion container with child items.
 *
 * @param {number} itemCount Number of child .dsgo-scroll-accordion-item elements.
 * @return {HTMLElement} The accordion container element.
 */
function createAccordion(itemCount = 3) {
	const accordion = document.createElement('div');
	accordion.classList.add('dsgo-scroll-accordion');

	for (let i = 0; i < itemCount; i++) {
		const item = document.createElement('div');
		item.classList.add('dsgo-scroll-accordion-item');
		// Mock getBoundingClientRect for each item
		item.getBoundingClientRect = jest.fn(() => ({
			top: 200 + i * 300,
			bottom: 400 + i * 300,
			left: 0,
			right: 800,
			width: 800,
			height: 200,
		}));
		accordion.appendChild(item);
	}

	document.body.appendChild(accordion);
	return accordion;
}

/**
 * Track event listeners registered by view.js so we can clean them up.
 */
const registeredListeners = [];
const originalDocAddEventListener = document.addEventListener.bind(document);
const originalWinAddEventListener = window.addEventListener.bind(window);

/**
 * Load the view.js module in isolation.
 * Because the module registers event listeners and runs init logic on load,
 * each test gets a clean copy via jest.isolateModules().
 *
 * Wraps document/window addEventListener to capture listeners for cleanup.
 */
function loadView() {
	document.addEventListener = (type, handler, options) => {
		registeredListeners.push({ target: document, type, handler });
		originalDocAddEventListener(type, handler, options);
	};

	window.addEventListener = (type, handler, options) => {
		registeredListeners.push({ target: window, type, handler });
		originalWinAddEventListener(type, handler, options);
	};

	jest.isolateModules(() => {
		require('../../../src/blocks/scroll-accordion/view.js');
	});

	document.addEventListener = originalDocAddEventListener;
	window.addEventListener = originalWinAddEventListener;
}

/**
 * Clean up DOM, event listeners, and restore defaults between tests.
 */
function cleanup() {
	while (document.body.firstChild) {
		document.body.removeChild(document.body.firstChild);
	}

	// Remove all listeners registered by previous loadView() calls.
	registeredListeners.forEach(({ target, type, handler }) => {
		target.removeEventListener(type, handler);
	});
	registeredListeners.length = 0;

	global.setMatchMedia(false);

	Object.defineProperty(window, 'innerHeight', {
		writable: true,
		configurable: true,
		value: 768,
	});
}

describe('Scroll Accordion - Frontend', () => {
	beforeEach(() => {
		jest.useFakeTimers({
			doNotFake: ['requestAnimationFrame', 'cancelAnimationFrame'],
		});
		Object.defineProperty(window, 'innerHeight', {
			writable: true,
			configurable: true,
			value: 768,
		});
		window.requestAnimationFrame.mockClear();
	});

	afterEach(() => {
		cleanup();
		jest.useRealTimers();
	});

	describe('Reduced motion', () => {
		test('does not animate when reduced motion is preferred', () => {
			const accordion = createAccordion(3);
			const items = accordion.querySelectorAll(
				'.dsgo-scroll-accordion-item'
			);

			global.setMatchMedia(true);
			loadView();

			// Flush rAF
			jest.advanceTimersByTime(20);

			// Items should not have transform applied
			items.forEach((item) => {
				expect(item.style.transform).toBe('');
			});
		});
	});

	describe('Scale calculations', () => {
		test('sets scale(1) for items at or above viewport center', () => {
			const accordion = createAccordion(3);
			const items = accordion.querySelectorAll(
				'.dsgo-scroll-accordion-item'
			);

			// Position item above viewport center (viewportCenter = 384)
			items[0].getBoundingClientRect = jest.fn(() => ({
				top: 100,
				bottom: 300,
				left: 0,
				right: 800,
				width: 800,
				height: 200,
			}));

			loadView();

			// Flush the initial updateCards call via rAF
			jest.advanceTimersByTime(20);

			expect(items[0].style.transform).toBe('scale(1)');
		});

		test('sets reduced scale for items below viewport center', () => {
			const accordion = createAccordion(3);
			const items = accordion.querySelectorAll(
				'.dsgo-scroll-accordion-item'
			);

			// Position item below viewport center (viewportCenter = 384)
			items[0].getBoundingClientRect = jest.fn(() => ({
				top: 600,
				bottom: 800,
				left: 0,
				right: 800,
				width: 800,
				height: 200,
			}));

			loadView();
			jest.advanceTimersByTime(20);

			// distanceFromCenter = 600 - 384 = 216
			// scaleValue = max(0.85, 1 - (216 / 768) * 0.3) = max(0.85, 0.9156...) = 0.9156...
			const transform = items[0].style.transform;
			expect(transform).toMatch(/^scale\(/);

			const scaleVal = parseFloat(
				transform.match(/scale\(([\d.]+)\)/)[1]
			);
			expect(scaleVal).toBeLessThan(1);
			expect(scaleVal).toBeGreaterThan(0.85);
		});

		test('scale does not go below 0.85', () => {
			const accordion = createAccordion(1);
			const items = accordion.querySelectorAll(
				'.dsgo-scroll-accordion-item'
			);

			// Position item far below viewport center to force minimum scale
			// distanceFromCenter = 5000 - 384 = 4616
			// 1 - (4616 / 768) * 0.3 = 1 - 1.803 = -0.803 (clamped to 0.85)
			items[0].getBoundingClientRect = jest.fn(() => ({
				top: 5000,
				bottom: 5200,
				left: 0,
				right: 800,
				width: 800,
				height: 200,
			}));

			loadView();
			jest.advanceTimersByTime(20);

			const transform = items[0].style.transform;
			const scaleVal = parseFloat(
				transform.match(/scale\(([\d.]+)\)/)[1]
			);
			expect(scaleVal).toBe(0.85);
		});
	});

	describe('Scroll events', () => {
		test('does not retain a removed accordion in the scroll listener', () => {
			const accordion = createAccordion(1);
			const item = accordion.querySelector('.dsgo-scroll-accordion-item');
			loadView();
			jest.advanceTimersByTime(20);
			item.getBoundingClientRect.mockClear();

			accordion.remove();
			window.dispatchEvent(new Event('scroll'));
			jest.advanceTimersByTime(20);

			expect(item.getBoundingClientRect).not.toHaveBeenCalled();
		});

		test('responds to scroll events with rAF throttle', () => {
			createAccordion(3);
			loadView();

			// Flush initial updateCards
			jest.advanceTimersByTime(20);
			window.requestAnimationFrame.mockClear();

			// Dispatch scroll event
			window.dispatchEvent(new Event('scroll'));

			// Should have called requestAnimationFrame exactly once
			expect(window.requestAnimationFrame).toHaveBeenCalledTimes(1);

			// Dispatch another scroll before rAF fires — should be throttled
			window.dispatchEvent(new Event('scroll'));
			expect(window.requestAnimationFrame).toHaveBeenCalledTimes(1);

			// After rAF fires, another scroll should schedule a new frame
			jest.advanceTimersByTime(20);
			window.dispatchEvent(new Event('scroll'));
			expect(window.requestAnimationFrame).toHaveBeenCalledTimes(2);
		});
	});

	describe('Resize handling', () => {
		test('updates viewport dimensions on resize', () => {
			const accordion = createAccordion(1);
			const items = accordion.querySelectorAll(
				'.dsgo-scroll-accordion-item'
			);

			loadView();
			jest.advanceTimersByTime(20);

			// Change viewport height
			Object.defineProperty(window, 'innerHeight', {
				writable: true,
				configurable: true,
				value: 1200,
			});

			// Position item between old center (384) and new center (600)
			// At viewportHeight=1200, center=600, distanceFromCenter = 500-600 = -100 (above center)
			items[0].getBoundingClientRect = jest.fn(() => ({
				top: 500,
				bottom: 700,
				left: 0,
				right: 800,
				width: 800,
				height: 200,
			}));

			// Fire resize and wait for debounce (150ms) + rAF (16ms)
			window.dispatchEvent(new Event('resize'));
			jest.advanceTimersByTime(170);

			// With updated viewport (center=600), item at top=500 is above center
			expect(items[0].style.transform).toBe('scale(1)');
		});
	});

	describe('Empty accordion', () => {
		test('handles empty accordion gracefully', () => {
			// Create accordion with no items
			const accordion = document.createElement('div');
			accordion.classList.add('dsgo-scroll-accordion');
			document.body.appendChild(accordion);

			// Should not throw
			expect(() => {
				loadView();
				jest.advanceTimersByTime(20);
			}).not.toThrow();
		});
	});

	describe('Custom reinit event', () => {
		test('responds to custom scroll-accordion:reinit event', () => {
			// Load with no accordion in the DOM so nothing initializes,
			// but the reinit listener gets registered.
			loadView();

			// Now add an accordion after initial load.
			const accordion = createAccordion(2);
			const items = accordion.querySelectorAll(
				'.dsgo-scroll-accordion-item'
			);

			// Position first item above center
			items[0].getBoundingClientRect = jest.fn(() => ({
				top: 100,
				bottom: 300,
				left: 0,
				right: 800,
				width: 800,
				height: 200,
			}));

			// Dispatch the reinit event to trigger initialization.
			document.dispatchEvent(new Event('scroll-accordion:reinit'));
			jest.advanceTimersByTime(20);

			// Items should have transforms applied after reinit
			expect(items[0].style.transform).toBe('scale(1)');
		});
	});
});
