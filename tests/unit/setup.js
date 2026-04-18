/**
 * Jest Setup File
 *
 * This file runs before all tests and sets up the testing environment.
 * Provides comprehensive mocks for WordPress and browser APIs.
 *
 * @package
 */

// Add custom matchers for DOM assertions
import '@testing-library/jest-dom';

// Mock WordPress global functions if they're not already mocked by @wordpress/scripts
global.wp = global.wp || {
	blocks: {},
	data: {},
	element: {},
	hooks: {},
};

// Mock common WordPress i18n functions
global.__ = jest.fn((text) => text);
global._x = jest.fn((text) => text);
global._n = jest.fn((single, plural, number) =>
	number === 1 ? single : plural
);
global._nx = jest.fn((single, plural, number) =>
	number === 1 ? single : plural
);
global.sprintf = jest.fn((format, ...args) => {
	let result = format;
	args.forEach((arg) => {
		result = result.replace(/%[sd]/, arg);
	});
	return result;
});

// Set up global test utilities
global.testUtils = {
	/**
	 * Helper to simulate attribute changes
	 *
	 * @param {Function} setAttributes - Setter function.
	 * @param {Object}   changes       - Changes to apply.
	 * @return {Object} The changes object.
	 */
	simulateAttributeChange: (setAttributes, changes) => {
		setAttributes(changes);
		return changes;
	},

	/**
	 * Helper to create mock block context
	 *
	 * @param {Object} values - Context values.
	 * @return {Object} Mock context object.
	 */
	createMockContext: (values = {}) => ({
		'designsetgo/form/formId': values.formId || 'test-form',
		'designsetgo/accordion/allowMultipleOpen':
			values.allowMultipleOpen || false,
		...values,
	}),

	/**
	 * Helper to get all mock calls for a function
	 *
	 * @param {Function} mockFn - Mock function.
	 * @return {Array} Array of calls.
	 */
	getCallsFor: (mockFn) => mockFn.mock.calls,
};

// Mock browser APIs not implemented in JSDOM
window.scrollTo = jest.fn();
window.scroll = jest.fn();
window.scrollBy = jest.fn();

// Mock requestAnimationFrame
window.requestAnimationFrame = jest.fn((cb) => setTimeout(cb, 16));
window.cancelAnimationFrame = jest.fn((id) => clearTimeout(id));

// Mock matchMedia with configurable responses
const createMatchMedia = (matches = false) =>
	jest.fn().mockImplementation((query) => ({
		matches,
		media: query,
		onchange: null,
		addListener: jest.fn(),
		removeListener: jest.fn(),
		addEventListener: jest.fn(),
		removeEventListener: jest.fn(),
		dispatchEvent: jest.fn(),
	}));

Object.defineProperty(window, 'matchMedia', {
	writable: true,
	configurable: true,
	value: createMatchMedia(false),
});

// Helper to change matchMedia response in tests
global.setMatchMedia = (matches) => {
	window.matchMedia = createMatchMedia(matches);
};

// Mock IntersectionObserver with callback support
global.IntersectionObserver = class IntersectionObserver {
	constructor(callback, options = {}) {
		this.callback = callback;
		this.options = options;
		this.observedElements = new Set();
		this.observe = jest.fn((element) => {
			this.observedElements.add(element);
		});
		this.unobserve = jest.fn((element) => {
			this.observedElements.delete(element);
		});
		this.disconnect = jest.fn(() => {
			this.observedElements.clear();
		});
		// Helper to simulate intersection
		this.simulateIntersection = (entries) => {
			this.callback(entries, this);
		};
	}
};

// Mock ResizeObserver with callback support
global.ResizeObserver = class ResizeObserver {
	constructor(callback) {
		this.callback = callback;
		this.observedElements = new Set();
		this.observe = jest.fn((element) => {
			this.observedElements.add(element);
		});
		this.unobserve = jest.fn((element) => {
			this.observedElements.delete(element);
		});
		this.disconnect = jest.fn(() => {
			this.observedElements.clear();
		});
		// Helper to simulate resize
		this.simulateResize = (entries) => {
			this.callback(entries, this);
		};
	}
};

// Mock MutationObserver
global.MutationObserver = class MutationObserver {
	constructor(callback) {
		this.callback = callback;
		this.observe = jest.fn();
		this.disconnect = jest.fn();
		this.takeRecords = jest.fn(() => []);
	}
};

// Mock getComputedStyle
window.getComputedStyle = jest.fn(() => ({
	getPropertyValue: jest.fn(() => ''),
	getPropertyPriority: jest.fn(() => ''),
}));

// Mock localStorage
const localStorageMock = {
	store: {},
	getItem: jest.fn((key) => localStorageMock.store[key] || null),
	setItem: jest.fn((key, value) => {
		localStorageMock.store[key] = String(value);
	}),
	removeItem: jest.fn((key) => {
		delete localStorageMock.store[key];
	}),
	clear: jest.fn(() => {
		localStorageMock.store = {};
	}),
	get length() {
		return Object.keys(localStorageMock.store).length;
	},
	key: jest.fn((index) => Object.keys(localStorageMock.store)[index] || null),
};

Object.defineProperty(window, 'localStorage', {
	value: localStorageMock,
	writable: true,
});

// Clean up localStorage between tests
beforeEach(() => {
	localStorageMock.clear();
});

// Mock fetch for API tests
global.fetch = jest.fn(() =>
	Promise.resolve({
		ok: true,
		json: () => Promise.resolve({}),
		text: () => Promise.resolve(''),
	})
);

// Reset fetch mock between tests
beforeEach(() => {
	global.fetch.mockClear();
});

// Custom Jest matchers for common assertions
expect.extend({
	/**
	 * Check if a CSS string contains a specific property-value pair
	 *
	 * @param {string} received - CSS string to check.
	 * @param {string} selector - CSS selector.
	 * @param {string} property - CSS property name.
	 * @param {string} value    - CSS property value.
	 * @return {Object} Jest matcher result.
	 */
	toContainCSSRule(received, selector, property, value) {
		const escapeRegex = (str) => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		const escapedSelector = escapeRegex(selector);
		const escapedProperty = escapeRegex(property);
		const escapedValue = escapeRegex(value);
		const regex = new RegExp(
			`${escapedSelector}[^{]*\\{[^}]*${escapedProperty}\\s*:\\s*${escapedValue}`
		);
		const pass = regex.test(received);

		return {
			pass,
			message: () =>
				pass
					? `Expected CSS not to contain rule "${selector} { ${property}: ${value} }"`
					: `Expected CSS to contain rule "${selector} { ${property}: ${value} }"`,
		};
	},

	/**
	 * Check if value is a valid CSS class name
	 *
	 * @param {string} received - String to validate.
	 * @return {Object} Jest matcher result.
	 */
	toBeValidCSSClassName(received) {
		const pass = /^[a-zA-Z_-][a-zA-Z0-9_-]*$/.test(received);
		return {
			pass,
			message: () =>
				pass
					? `Expected "${received}" not to be a valid CSS class name`
					: `Expected "${received}" to be a valid CSS class name`,
		};
	},
});
