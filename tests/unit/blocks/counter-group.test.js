/**
 * Counter Group Block - Frontend Unit Tests
 *
 * Tests for the counter group view.js frontend script.
 * Since view.js has no exports (side-effect module), each test uses
 * jest.isolateModules() to re-require a fresh instance.
 *
 * @package
 */

// Mock countup.js before any imports
jest.mock('countup.js', () => {
	const MockCountUp = jest.fn().mockImplementation(() => ({
		error: false,
		start: jest.fn(),
		reset: jest.fn(),
		update: jest.fn(),
	}));

	return { CountUp: MockCountUp };
});

/**
 * Create a counter group DOM fixture.
 *
 * @param {Array} counters Array of counter config objects.
 * @return {HTMLElement} The counter group element.
 */
function createCounterGroupFixture(counters = []) {
	const group = document.createElement('div');
	group.classList.add('dsgo-counter-group');

	counters.forEach((config) => {
		const counter = document.createElement('div');
		counter.classList.add('dsgo-counter');

		// Set data attributes from config
		if (config.startValue !== undefined) {
			counter.dataset.startValue = String(config.startValue);
		}
		if (config.endValue !== undefined) {
			counter.dataset.endValue = String(config.endValue);
		}
		if (config.decimals !== undefined) {
			counter.dataset.decimals = String(config.decimals);
		}
		if (config.prefix !== undefined) {
			counter.dataset.prefix = config.prefix;
		}
		if (config.suffix !== undefined) {
			counter.dataset.suffix = config.suffix;
		}
		if (config.duration !== undefined) {
			counter.dataset.duration = String(config.duration);
		}
		if (config.delay !== undefined) {
			counter.dataset.delay = String(config.delay);
		}
		if (config.easing !== undefined) {
			counter.dataset.easing = config.easing;
		}
		if (config.useGrouping !== undefined) {
			counter.dataset.useGrouping = String(config.useGrouping);
		}
		if (config.separator !== undefined) {
			counter.dataset.separator = config.separator;
		}
		if (config.decimal !== undefined) {
			counter.dataset.decimal = config.decimal;
		}

		if (config.omitValueElement) {
			// Intentionally skip creating the value element
		} else {
			const valueEl = document.createElement('span');
			valueEl.classList.add('dsgo-counter__value');
			valueEl.textContent = '0';
			counter.appendChild(valueEl);
		}

		group.appendChild(counter);
	});

	document.body.appendChild(group);
	return group;
}

/**
 * Get the value element text for a counter at a given index.
 *
 * @param {HTMLElement} group Counter group element.
 * @param {number}      index Counter index.
 * @return {string|null} The text content, or null if not found.
 */
function getCounterValue(group, index) {
	const counters = group.querySelectorAll('.dsgo-counter');
	const valueEl = counters[index]?.querySelector('.dsgo-counter__value');
	return valueEl ? valueEl.textContent : null;
}

/**
 * Load the view.js module in isolation, fire DOMContentLoaded, and capture
 * the IntersectionObserver instance so we can simulate intersection entries.
 *
 * The counter-group module creates its IntersectionObserver inside a
 * DOMContentLoaded handler, so we must keep the patched observer class
 * active while dispatching the event.
 */
function loadView() {
	let observer;
	const OriginalObserver = global.IntersectionObserver;

	global.IntersectionObserver = class extends OriginalObserver {
		constructor(callback, options) {
			super(callback, options);
			observer = this;
		}
	};

	jest.isolateModules(() => {
		require('../../../src/blocks/counter-group/view.js');
	});

	// Fire DOMContentLoaded while the patched observer is still in place
	document.dispatchEvent(new Event('DOMContentLoaded'));

	global.IntersectionObserver = OriginalObserver;
	return observer;
}

/**
 * Load view.js in reduced motion mode (no IntersectionObserver expected).
 * Also fires DOMContentLoaded to trigger initialization.
 */
function loadViewReducedMotion() {
	jest.isolateModules(() => {
		require('../../../src/blocks/counter-group/view.js');
	});
	document.dispatchEvent(new Event('DOMContentLoaded'));
}

/**
 * Simulate an element becoming visible via IntersectionObserver.
 *
 * @param {Object}      observer The captured IntersectionObserver instance.
 * @param {HTMLElement} element  The element to intersect.
 */
function simulateIntersection(observer, element) {
	observer.simulateIntersection([{ isIntersecting: true, target: element }]);
}

/**
 * Clean up DOM and restore defaults between tests.
 */
function cleanup() {
	while (document.body.firstChild) {
		document.body.removeChild(document.body.firstChild);
	}
	global.setMatchMedia(false);
	jest.restoreAllMocks();

	// Clear the CountUp mock calls between tests
	const { CountUp } = require('countup.js');
	CountUp.mockClear();
}

describe('Counter Group - Frontend', () => {
	afterEach(() => {
		cleanup();
	});

	describe('Zero and prefix/suffix handling', () => {
		test('numberOr keeps 0 and only falls back for non-numeric input', () => {
			let numberOr;
			jest.isolateModules(() => {
				({
					numberOr,
				} = require('../../../src/blocks/counter-group/view.js'));
			});

			expect(numberOr('0', 100)).toBe(0);
			expect(numberOr('-5', 100)).toBe(-5);
			expect(numberOr(undefined, 100)).toBe(100);
			expect(numberOr('abc', 100)).toBe(100);
		});

		test('reduced motion shows an end value of 0 with prefix and suffix', () => {
			global.setMatchMedia(true);

			const group = createCounterGroupFixture([{ endValue: 0 }]);
			const counter = group.querySelector('.dsgo-counter');
			counter.dataset.prefix = '$';
			counter.dataset.suffix = '%';

			loadViewReducedMotion();

			expect(getCounterValue(group, 0)).toBe('$0%');
		});
	});

	describe('Reduced motion', () => {
		test('shows final value immediately when reduced motion preferred', () => {
			global.setMatchMedia(true);

			const group = createCounterGroupFixture([
				{ endValue: 500, decimals: 0 },
			]);

			loadViewReducedMotion();

			expect(getCounterValue(group, 0)).toBe('500');
		});

		test('shows all final values without animation', () => {
			global.setMatchMedia(true);

			const group = createCounterGroupFixture([
				{ endValue: 100 },
				{ endValue: 250 },
				{ endValue: 999 },
			]);

			loadViewReducedMotion();

			expect(getCounterValue(group, 0)).toBe('100');
			expect(getCounterValue(group, 1)).toBe('250');
			expect(getCounterValue(group, 2)).toBe('999');
		});
	});

	describe('Number formatting', () => {
		test('formats number with decimal places', () => {
			global.setMatchMedia(true);

			const group = createCounterGroupFixture([
				{ endValue: 99.5, decimals: 2 },
			]);

			loadViewReducedMotion();

			expect(getCounterValue(group, 0)).toBe('99.50');
		});

		test('formats number with thousands separator', () => {
			global.setMatchMedia(true);

			const group = createCounterGroupFixture([
				{ endValue: 1234567, useGrouping: true, separator: ',' },
			]);

			loadViewReducedMotion();

			expect(getCounterValue(group, 0)).toBe('1,234,567');
		});

		test('uses custom decimal point character', () => {
			global.setMatchMedia(true);

			const group = createCounterGroupFixture([
				{ endValue: 99.5, decimals: 2, decimal: ',' },
			]);

			loadViewReducedMotion();

			expect(getCounterValue(group, 0)).toBe('99,50');
		});

		test('uses custom separator character', () => {
			global.setMatchMedia(true);

			const group = createCounterGroupFixture([
				{ endValue: 1000000, useGrouping: true, separator: '.' },
			]);

			loadViewReducedMotion();

			expect(getCounterValue(group, 0)).toBe('1.000.000');
		});
	});

	describe('Escape replacement', () => {
		test('handles $ characters safely in separators', () => {
			global.setMatchMedia(true);

			const group = createCounterGroupFixture([
				{ endValue: 1000, useGrouping: true, separator: '$' },
			]);

			loadViewReducedMotion();

			// The $ should appear literally in the formatted output
			expect(getCounterValue(group, 0)).toBe('1$000');
		});
	});

	describe('IntersectionObserver lazy animation', () => {
		test('uses IntersectionObserver for lazy animation', () => {
			createCounterGroupFixture([{ endValue: 100 }]);

			const observer = loadView();

			expect(observer).toBeDefined();
			expect(observer.observe).toHaveBeenCalled();
		});
	});

	describe('Missing value element', () => {
		test('handles missing value element gracefully', () => {
			global.setMatchMedia(true);

			createCounterGroupFixture([
				{ endValue: 100, omitValueElement: true },
			]);

			// Should not throw
			expect(() => {
				loadViewReducedMotion();
			}).not.toThrow();
		});
	});

	describe('Easing functions', () => {
		test('linear easing function calculates correctly', () => {
			createCounterGroupFixture([{ endValue: 100, easing: 'linear' }]);

			const observer = loadView();
			simulateIntersection(
				observer,
				document.querySelector('.dsgo-counter-group')
			);

			const { CountUp } = require('countup.js');
			const callOptions = CountUp.mock.calls[0][2];

			// linear: (t, b, c, d) => (c * t) / d + b
			// t=1, b=0, c=100, d=2 => (100 * 1) / 2 + 0 = 50
			expect(callOptions.easingFn(1, 0, 100, 2)).toBe(50);
			// t=2, b=0, c=100, d=2 => (100 * 2) / 2 + 0 = 100
			expect(callOptions.easingFn(2, 0, 100, 2)).toBe(100);
		});

		test('easeOutQuad easing function calculates correctly', () => {
			createCounterGroupFixture([
				{ endValue: 100, easing: 'easeOutQuad' },
			]);

			const observer = loadView();
			simulateIntersection(
				observer,
				document.querySelector('.dsgo-counter-group')
			);

			const { CountUp } = require('countup.js');
			const callOptions = CountUp.mock.calls[0][2];

			// easeOutQuad: (t, b, c, d) => { t /= d; return -c * t * (t - 2) + b; }
			// t=1, b=0, c=100, d=2 => t=0.5, -100 * 0.5 * (0.5 - 2) + 0 = -100 * 0.5 * -1.5 = 75
			expect(callOptions.easingFn(1, 0, 100, 2)).toBe(75);
			// t=2, b=0, c=100, d=2 => t=1, -100 * 1 * (1 - 2) + 0 = 100
			expect(callOptions.easingFn(2, 0, 100, 2)).toBe(100);
		});
	});
});
