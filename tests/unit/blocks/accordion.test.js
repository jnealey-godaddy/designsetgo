/**
 * Accordion Block - Frontend Unit Tests
 *
 * Tests for the accordion view.js frontend script.
 * Since view.js has no exports (side-effect module), each test uses
 * jest.isolateModules() to re-require a fresh instance.
 *
 * @package
 */

/* global KeyboardEvent, Element */

/**
 * Create an accordion container with child items.
 *
 * @param {Object}  options               Configuration options.
 * @param {number}  options.itemCount     Number of accordion items.
 * @param {boolean} options.allowMultiple Whether to allow multiple open panels.
 * @param {Array}   options.initiallyOpen Array of indices to mark initially open.
 * @return {HTMLElement} The accordion container element.
 */
function createAccordion({
	itemCount = 3,
	allowMultiple = false,
	initiallyOpen = [],
} = {}) {
	const accordion = document.createElement('div');
	accordion.classList.add('dsgo-accordion');
	if (allowMultiple) {
		accordion.setAttribute('data-allow-multiple', 'true');
	}

	for (let i = 0; i < itemCount; i++) {
		const item = document.createElement('div');
		item.classList.add('dsgo-accordion-item');

		if (initiallyOpen.includes(i)) {
			item.setAttribute('data-initially-open', 'true');
		}

		const trigger = document.createElement('button');
		trigger.classList.add('dsgo-accordion-item__trigger');
		trigger.setAttribute('aria-expanded', 'false');
		trigger.textContent = `Item ${i + 1}`;

		const panel = document.createElement('div');
		panel.classList.add('dsgo-accordion-item__panel');
		panel.hidden = true;

		const content = document.createElement('div');
		content.classList.add('dsgo-accordion-item__content');
		content.textContent = `Content for item ${i + 1}`;
		panel.appendChild(content);

		item.appendChild(trigger);
		item.appendChild(panel);
		accordion.appendChild(item);
	}

	document.body.appendChild(accordion);
	return accordion;
}

/**
 * Load the view.js module in isolation.
 * Because the module registers a DOMContentLoaded listener, and JSDOM
 * The module only registers a DOMContentLoaded listener (does not check
 * readyState), so we must dispatch the event manually after loading.
 */
function loadView() {
	jest.isolateModules(() => {
		require('../../../src/blocks/accordion/view.js');
	});
	document.dispatchEvent(new Event('DOMContentLoaded'));
}

/**
 * Dispatch a keyboard event on an element.
 *
 * @param {HTMLElement} element Target element.
 * @param {string}      key     Key name (e.g. 'Enter', 'ArrowDown').
 */
function pressKey(element, key) {
	const event = new KeyboardEvent('keydown', {
		key,
		bubbles: true,
		cancelable: true,
	});
	element.dispatchEvent(event);
}

/**
 * Clean up DOM and restore defaults between tests.
 */
function cleanup() {
	while (document.body.firstChild) {
		document.body.removeChild(document.body.firstChild);
	}
	global.setMatchMedia(false);
	Object.defineProperty(window, 'innerWidth', {
		writable: true,
		configurable: true,
		value: 1024,
	});
}

// Mock scrollIntoView (not implemented in JSDOM)
Element.prototype.scrollIntoView = jest.fn();

describe('Accordion - Frontend', () => {
	afterEach(() => {
		cleanup();
	});

	describe('Initialization', () => {
		test('initializes and marks container with data-dsgo-initialized', () => {
			const accordion = createAccordion();
			loadView();

			expect(accordion.hasAttribute('data-dsgo-initialized')).toBe(true);
			expect(accordion.getAttribute('data-dsgo-initialized')).toBe(
				'true'
			);
		});

		test('opens initially-open items on load', () => {
			const accordion = createAccordion({
				allowMultiple: true,
				initiallyOpen: [0, 2],
			});
			loadView();

			const items = accordion.querySelectorAll('.dsgo-accordion-item');

			expect(
				items[0].classList.contains('dsgo-accordion-item--open')
			).toBe(true);
			expect(
				items[0].querySelector('.dsgo-accordion-item__panel').hidden
			).toBe(false);

			expect(
				items[1].classList.contains('dsgo-accordion-item--closed')
			).toBe(true);
			expect(
				items[1].querySelector('.dsgo-accordion-item__panel').hidden
			).toBe(true);

			expect(
				items[2].classList.contains('dsgo-accordion-item--open')
			).toBe(true);
			expect(
				items[2].querySelector('.dsgo-accordion-item__panel').hidden
			).toBe(false);
		});

		test('single mode: only the first initially-open item opens', () => {
			const accordion = createAccordion({ initiallyOpen: [1, 2] });
			loadView();

			const items = accordion.querySelectorAll('.dsgo-accordion-item');

			expect(
				items[1].classList.contains('dsgo-accordion-item--open')
			).toBe(true);
			expect(
				items[2].classList.contains('dsgo-accordion-item--closed')
			).toBe(true);
			expect(
				items[2].querySelector('.dsgo-accordion-item__panel').hidden
			).toBe(true);
		});

		test('a nested accordion keeps its own items', () => {
			const outer = createAccordion({ itemCount: 2, initiallyOpen: [0] });
			const inner = createAccordion({ itemCount: 2, initiallyOpen: [0] });
			outer
				.querySelector('.dsgo-accordion-item__content')
				.appendChild(inner);
			loadView();

			const innerFirst = inner.querySelector('.dsgo-accordion-item');
			expect(
				innerFirst.classList.contains('dsgo-accordion-item--open')
			).toBe(true);

			// Opening the outer second item must not close the inner item.
			// (Not `:scope >` — JSDOM matches descendants with it.)
			const outerItems = Array.from(outer.children).filter((el) =>
				el.classList.contains('dsgo-accordion-item')
			);
			outerItems[1]
				.querySelector('.dsgo-accordion-item__trigger')
				.click();
			expect(
				outerItems[1].classList.contains('dsgo-accordion-item--open')
			).toBe(true);
			expect(
				innerFirst.classList.contains('dsgo-accordion-item--open')
			).toBe(true);
		});

		test('does not double-initialize the same container', () => {
			const accordion = createAccordion();
			loadView();

			const skipLinks = accordion.querySelectorAll(
				'.dsgo-accordion__skip-link'
			);
			expect(skipLinks).toHaveLength(1);

			// Load again; the guard attribute should prevent re-init
			loadView();

			const skipLinksAfter = accordion.querySelectorAll(
				'.dsgo-accordion__skip-link'
			);
			expect(skipLinksAfter).toHaveLength(1);
		});

		test('creates skip link for accessibility', () => {
			const accordion = createAccordion();
			loadView();

			const skipLink = accordion.querySelector(
				'.dsgo-accordion__skip-link'
			);
			expect(skipLink).not.toBeNull();
			expect(skipLink.textContent).toBe('Skip accordion');
			expect(skipLink.href).toContain('#end-of-accordion');
		});
	});

	describe('Click interaction', () => {
		test('click toggles panel open then closed', () => {
			const accordion = createAccordion();
			loadView();

			const item = accordion.querySelector('.dsgo-accordion-item');
			const trigger = item.querySelector('.dsgo-accordion-item__trigger');
			const panel = item.querySelector('.dsgo-accordion-item__panel');

			// Initially closed
			expect(item.classList.contains('dsgo-accordion-item--closed')).toBe(
				true
			);
			expect(panel.hidden).toBe(true);

			// Click to open
			trigger.click();
			expect(item.classList.contains('dsgo-accordion-item--open')).toBe(
				true
			);
			expect(item.classList.contains('dsgo-accordion-item--closed')).toBe(
				false
			);
			expect(panel.hidden).toBe(false);

			// Click to close
			trigger.click();
			expect(item.classList.contains('dsgo-accordion-item--closed')).toBe(
				true
			);
			expect(item.classList.contains('dsgo-accordion-item--open')).toBe(
				false
			);
		});

		test('single mode: clicking one closes all others', () => {
			const accordion = createAccordion({
				allowMultiple: false,
				initiallyOpen: [0],
			});
			loadView();

			const items = accordion.querySelectorAll('.dsgo-accordion-item');
			const secondTrigger = items[1].querySelector(
				'.dsgo-accordion-item__trigger'
			);

			// First item is open
			expect(
				items[0].classList.contains('dsgo-accordion-item--open')
			).toBe(true);

			// Click the second trigger
			secondTrigger.click();

			// Second item opens, first item closes
			expect(
				items[1].classList.contains('dsgo-accordion-item--open')
			).toBe(true);
			expect(
				items[0].classList.contains('dsgo-accordion-item--open')
			).toBe(false);
			expect(
				items[0].classList.contains('dsgo-accordion-item--closed')
			).toBe(true);
		});

		test('multiple mode: allows multiple panels open simultaneously', () => {
			const accordion = createAccordion({
				allowMultiple: true,
				initiallyOpen: [0],
			});
			loadView();

			const items = accordion.querySelectorAll('.dsgo-accordion-item');
			const secondTrigger = items[1].querySelector(
				'.dsgo-accordion-item__trigger'
			);

			// First item is already open
			expect(
				items[0].classList.contains('dsgo-accordion-item--open')
			).toBe(true);

			// Click second trigger
			secondTrigger.click();

			// Both should be open
			expect(
				items[0].classList.contains('dsgo-accordion-item--open')
			).toBe(true);
			expect(
				items[1].classList.contains('dsgo-accordion-item--open')
			).toBe(true);
		});
	});

	describe('ARIA attributes', () => {
		test('sets aria-expanded correctly on open and close', () => {
			const accordion = createAccordion();
			loadView();

			const item = accordion.querySelector('.dsgo-accordion-item');
			const trigger = item.querySelector('.dsgo-accordion-item__trigger');

			// Initially closed
			expect(trigger.getAttribute('aria-expanded')).toBe('false');

			// Open
			trigger.click();
			expect(trigger.getAttribute('aria-expanded')).toBe('true');

			// Close
			trigger.click();
			expect(trigger.getAttribute('aria-expanded')).toBe('false');
		});

		test('panel hidden attribute is set correctly', () => {
			// Use fake timers so we can flush the close animation
			// (closePanel uses requestAnimationFrame + setTimeout)
			jest.useFakeTimers();

			const accordion = createAccordion();
			loadView();

			const item = accordion.querySelector('.dsgo-accordion-item');
			const trigger = item.querySelector('.dsgo-accordion-item__trigger');
			const panel = item.querySelector('.dsgo-accordion-item__panel');

			// Closed state: panel is hidden
			expect(panel.hidden).toBe(true);

			// Open state: panel is visible
			trigger.click();
			jest.runAllTimers();
			expect(panel.hidden).toBe(false);

			// Close again: flush RAF (16ms) + animation duration timers
			trigger.click();
			jest.runAllTimers();
			expect(panel.hidden).toBe(true);

			jest.useRealTimers();
		});
	});

	describe('URL hash deep links', () => {
		afterEach(() => {
			window.history.replaceState(null, '', window.location.pathname);
		});

		test('opens the item whose anchor is in the hash', () => {
			const accordion = createAccordion({ initiallyOpen: [0] });
			const items = accordion.querySelectorAll('.dsgo-accordion-item');
			items[2].id = 'pricing-faq';
			window.history.replaceState(null, '', '#pricing-faq');
			Element.prototype.scrollIntoView.mockClear();
			loadView();

			expect(
				items[2].classList.contains('dsgo-accordion-item--open')
			).toBe(true);
			expect(
				items[0].classList.contains('dsgo-accordion-item--open')
			).toBe(false);
			expect(items[2].scrollIntoView).toHaveBeenCalled();
		});

		test('opens the item holding an element the hash targets', () => {
			const accordion = createAccordion();
			const items = accordion.querySelectorAll('.dsgo-accordion-item');
			const heading = document.createElement('h3');
			heading.id = 'refunds';
			items[1]
				.querySelector('.dsgo-accordion-item__content')
				.appendChild(heading);
			window.history.replaceState(null, '', '#refunds');
			loadView();

			expect(
				items[1].querySelector('.dsgo-accordion-item__panel').hidden
			).toBe(false);
		});

		test('opens the item when the hash changes after load', () => {
			const accordion = createAccordion();
			const items = accordion.querySelectorAll('.dsgo-accordion-item');
			items[1].id = 'shipping';
			loadView();

			expect(
				items[1].classList.contains('dsgo-accordion-item--open')
			).toBe(false);

			window.history.replaceState(null, '', '#shipping');
			window.dispatchEvent(new Event('hashchange'));

			expect(
				items[1].classList.contains('dsgo-accordion-item--open')
			).toBe(true);
		});

		test('ignores a hash that matches nothing', () => {
			const accordion = createAccordion({ initiallyOpen: [0] });
			window.history.replaceState(null, '', '#nope');
			loadView();

			expect(
				accordion
					.querySelector('.dsgo-accordion-item')
					.classList.contains('dsgo-accordion-item--open')
			).toBe(true);
		});
	});

	describe('Keyboard navigation', () => {
		test('Enter toggles panel', () => {
			const accordion = createAccordion();
			loadView();

			const item = accordion.querySelector('.dsgo-accordion-item');
			const trigger = item.querySelector('.dsgo-accordion-item__trigger');

			expect(item.classList.contains('dsgo-accordion-item--closed')).toBe(
				true
			);

			pressKey(trigger, 'Enter');
			expect(item.classList.contains('dsgo-accordion-item--open')).toBe(
				true
			);

			pressKey(trigger, 'Enter');
			expect(item.classList.contains('dsgo-accordion-item--closed')).toBe(
				true
			);
		});

		test('Space toggles panel', () => {
			const accordion = createAccordion();
			loadView();

			const item = accordion.querySelector('.dsgo-accordion-item');
			const trigger = item.querySelector('.dsgo-accordion-item__trigger');

			expect(item.classList.contains('dsgo-accordion-item--closed')).toBe(
				true
			);

			pressKey(trigger, ' ');
			expect(item.classList.contains('dsgo-accordion-item--open')).toBe(
				true
			);
		});

		test('ArrowDown focuses next trigger', () => {
			const accordion = createAccordion();
			loadView();

			const items = accordion.querySelectorAll('.dsgo-accordion-item');
			const firstTrigger = items[0].querySelector(
				'.dsgo-accordion-item__trigger'
			);
			const secondTrigger = items[1].querySelector(
				'.dsgo-accordion-item__trigger'
			);

			firstTrigger.focus();
			pressKey(firstTrigger, 'ArrowDown');

			expect(document.activeElement).toBe(secondTrigger);
		});

		test('ArrowUp focuses previous trigger', () => {
			const accordion = createAccordion();
			loadView();

			const items = accordion.querySelectorAll('.dsgo-accordion-item');
			const firstTrigger = items[0].querySelector(
				'.dsgo-accordion-item__trigger'
			);
			const secondTrigger = items[1].querySelector(
				'.dsgo-accordion-item__trigger'
			);

			secondTrigger.focus();
			pressKey(secondTrigger, 'ArrowUp');

			expect(document.activeElement).toBe(firstTrigger);
		});

		test('Home focuses first trigger', () => {
			const accordion = createAccordion();
			loadView();

			const items = accordion.querySelectorAll('.dsgo-accordion-item');
			const firstTrigger = items[0].querySelector(
				'.dsgo-accordion-item__trigger'
			);
			const lastTrigger = items[2].querySelector(
				'.dsgo-accordion-item__trigger'
			);

			lastTrigger.focus();
			pressKey(lastTrigger, 'Home');

			expect(document.activeElement).toBe(firstTrigger);
		});

		test('End focuses last trigger', () => {
			const accordion = createAccordion();
			loadView();

			const items = accordion.querySelectorAll('.dsgo-accordion-item');
			const firstTrigger = items[0].querySelector(
				'.dsgo-accordion-item__trigger'
			);
			const lastTrigger = items[2].querySelector(
				'.dsgo-accordion-item__trigger'
			);

			firstTrigger.focus();
			pressKey(firstTrigger, 'End');

			expect(document.activeElement).toBe(lastTrigger);
		});
	});
});
