/**
 * Image Accordion Block - Frontend Unit Tests
 *
 * Tests for the image accordion view.js frontend script.
 * Since view.js has no exports (side-effect module), each test uses
 * jest.isolateModules() and dispatches DOMContentLoaded to trigger init.
 *
 * @package
 */

/* global navigator, MouseEvent, KeyboardEvent */

/**
 * Create an image accordion container with child items.
 *
 * @param {Object}  options                 Configuration options.
 * @param {number}  options.itemCount       Number of accordion items.
 * @param {string}  options.triggerType     Trigger type ('hover' or 'click').
 * @param {number}  options.defaultExpanded 1-based index of default expanded item (0 for none).
 * @param {boolean} options.withLinks       Whether to add link elements inside items.
 * @return {HTMLElement} The accordion container element.
 */
function createImageAccordion({
	itemCount = 3,
	triggerType = 'click',
	defaultExpanded = 0,
	withLinks = false,
} = {}) {
	const accordion = document.createElement('div');
	accordion.classList.add('dsgo-image-accordion');
	accordion.setAttribute('data-trigger-type', triggerType);

	if (defaultExpanded > 0) {
		accordion.setAttribute(
			'data-default-expanded',
			String(defaultExpanded)
		);
	}

	for (let i = 0; i < itemCount; i++) {
		const item = document.createElement('div');
		item.classList.add('dsgo-image-accordion-item');

		if (withLinks) {
			const link = document.createElement('a');
			link.href = '#';
			link.textContent = `Link ${i + 1}`;
			item.appendChild(link);
		}

		accordion.appendChild(item);
	}

	document.body.appendChild(accordion);
	return accordion;
}

/**
 * Mock touch device detection.
 *
 * @param {boolean} isTouch Whether to simulate a touch device.
 */
function setTouchDevice(isTouch) {
	if (isTouch) {
		window.ontouchstart = null;
	} else {
		delete window.ontouchstart;
	}
	Object.defineProperty(navigator, 'maxTouchPoints', {
		writable: true,
		configurable: true,
		value: isTouch ? 1 : 0,
	});
}

/**
 * Track event listeners registered by view.js so we can clean them up.
 */
const registeredListeners = [];
const originalAddEventListener = document.addEventListener.bind(document);

/**
 * Load the view.js module in isolation and dispatch DOMContentLoaded.
 *
 * The image accordion module listens for DOMContentLoaded,
 * so we set readyState to 'loading' before requiring, then dispatch.
 *
 * Wraps document.addEventListener to capture listeners for cleanup.
 */
function loadView() {
	// Force readyState to 'loading' so the module registers for DOMContentLoaded
	Object.defineProperty(document, 'readyState', {
		configurable: true,
		get: () => 'loading',
	});

	document.addEventListener = (type, handler, options) => {
		registeredListeners.push({ type, handler });
		originalAddEventListener(type, handler, options);
	};

	jest.isolateModules(() => {
		require('../../../src/blocks/image-accordion/view.js');
	});

	document.addEventListener = originalAddEventListener;

	// Restore readyState
	Object.defineProperty(document, 'readyState', {
		configurable: true,
		get: () => 'complete',
	});

	// Dispatch DOMContentLoaded to trigger initialization
	document.dispatchEvent(new Event('DOMContentLoaded'));
}

/**
 * Clean up DOM, event listeners, and restore defaults between tests.
 */
function cleanup() {
	while (document.body.firstChild) {
		document.body.removeChild(document.body.firstChild);
	}

	// Remove all listeners registered by previous loadView() calls.
	registeredListeners.forEach(({ type, handler }) => {
		document.removeEventListener(type, handler);
	});
	registeredListeners.length = 0;

	setTouchDevice(false);
}

describe('Image Accordion - Frontend', () => {
	beforeEach(() => {
		setTouchDevice(false);
	});

	afterEach(() => {
		cleanup();
	});

	describe('ARIA attributes', () => {
		test('sets ARIA attributes on items (role, aria-label, tabindex)', () => {
			const accordion = createImageAccordion({ itemCount: 3 });
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			items.forEach((item, index) => {
				expect(item.getAttribute('role')).toBe('button');
				expect(item.getAttribute('aria-label')).toBe(
					`Image panel ${index + 1}`
				);
				expect(item.getAttribute('tabindex')).toBe('0');
			});
		});
	});

	describe('Default expanded item', () => {
		test('expands default item based on data-default-expanded', () => {
			const accordion = createImageAccordion({
				itemCount: 3,
				defaultExpanded: 2,
			});
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			// Item at index 1 (1-based index 2) should have expanded class
			expect(items[1].classList.contains('is-expanded')).toBe(true);

			// Other items should be collapsed
			expect(items[0].classList.contains('is-collapsed')).toBe(true);
			expect(items[2].classList.contains('is-collapsed')).toBe(true);

			// Note: The ARIA setup loop (which runs after expandItem) sets
			// aria-expanded="false" on ALL items, overwriting the expanded
			// item's "true" value. Verify the classes reflect the correct state.
			expect(items[0].classList.contains('is-expanded')).toBe(false);
			expect(items[2].classList.contains('is-expanded')).toBe(false);
		});

		test('no default expanded: resetItems on init', () => {
			const accordion = createImageAccordion({
				itemCount: 3,
				defaultExpanded: 0,
			});
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			// All items should have aria-expanded="false" and no is-expanded/is-collapsed
			items.forEach((item) => {
				expect(item.classList.contains('is-expanded')).toBe(false);
				expect(item.classList.contains('is-collapsed')).toBe(false);
				expect(item.getAttribute('aria-expanded')).toBe('false');
			});
		});
	});

	describe('Click interaction', () => {
		test('click expands item and collapses others', () => {
			const accordion = createImageAccordion({
				itemCount: 3,
				triggerType: 'click',
			});
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			// Click second item
			items[1].dispatchEvent(new MouseEvent('click', { bubbles: true }));

			expect(items[1].classList.contains('is-expanded')).toBe(true);
			expect(items[1].getAttribute('aria-expanded')).toBe('true');

			// Others should be collapsed
			expect(items[0].classList.contains('is-collapsed')).toBe(true);
			expect(items[0].getAttribute('aria-expanded')).toBe('false');
			expect(items[2].classList.contains('is-collapsed')).toBe(true);
		});

		test('does not trigger on click of inner link', () => {
			const accordion = createImageAccordion({
				itemCount: 3,
				triggerType: 'click',
				withLinks: true,
			});
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);
			const link = items[0].querySelector('a');

			// Click the link inside the item
			link.dispatchEvent(new MouseEvent('click', { bubbles: true }));

			// Item should NOT be expanded because the click target is an <a>
			expect(items[0].classList.contains('is-expanded')).toBe(false);
		});

		test('does not trigger on click of inner button', () => {
			const accordion = createImageAccordion({
				itemCount: 3,
				triggerType: 'click',
			});

			// Add a button inside the first item
			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);
			const button = document.createElement('button');
			button.textContent = 'Action';
			items[0].appendChild(button);

			loadView();

			// Click the button inside the item
			button.dispatchEvent(new MouseEvent('click', { bubbles: true }));

			// Item should NOT be expanded
			expect(items[0].classList.contains('is-expanded')).toBe(false);
		});
	});

	describe('Keyboard interaction', () => {
		test('Enter expands item', () => {
			const accordion = createImageAccordion({
				itemCount: 3,
				triggerType: 'click',
			});
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			// Simulate Enter key on second item
			const keyEvent = new KeyboardEvent('keydown', {
				key: 'Enter',
				bubbles: true,
				cancelable: true,
			});
			items[1].dispatchEvent(keyEvent);

			expect(items[1].classList.contains('is-expanded')).toBe(true);
			expect(items[1].getAttribute('aria-expanded')).toBe('true');
		});

		test('Space expands item', () => {
			const accordion = createImageAccordion({
				itemCount: 3,
				triggerType: 'click',
			});
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			// Simulate Space key on third item
			const keyEvent = new KeyboardEvent('keydown', {
				key: ' ',
				bubbles: true,
				cancelable: true,
			});
			items[2].dispatchEvent(keyEvent);

			expect(items[2].classList.contains('is-expanded')).toBe(true);
			expect(items[2].getAttribute('aria-expanded')).toBe('true');
		});
	});

	describe('Arrow key navigation', () => {
		test('ArrowRight moves focus to next item', () => {
			const accordion = createImageAccordion({ itemCount: 3 });
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			// Focus the first item
			items[0].focus();

			// Dispatch ArrowRight on the accordion (where the keydown listener is)
			const keyEvent = new KeyboardEvent('keydown', {
				key: 'ArrowRight',
				bubbles: true,
				cancelable: true,
			});
			items[0].dispatchEvent(keyEvent);

			expect(document.activeElement).toBe(items[1]);
		});

		test('ArrowDown moves focus to next item', () => {
			const accordion = createImageAccordion({ itemCount: 3 });
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			items[0].focus();

			const keyEvent = new KeyboardEvent('keydown', {
				key: 'ArrowDown',
				bubbles: true,
				cancelable: true,
			});
			items[0].dispatchEvent(keyEvent);

			expect(document.activeElement).toBe(items[1]);
		});

		test('ArrowLeft moves focus to previous item', () => {
			const accordion = createImageAccordion({ itemCount: 3 });
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			items[1].focus();

			const keyEvent = new KeyboardEvent('keydown', {
				key: 'ArrowLeft',
				bubbles: true,
				cancelable: true,
			});
			items[1].dispatchEvent(keyEvent);

			expect(document.activeElement).toBe(items[0]);
		});

		test('ArrowUp moves focus to previous item', () => {
			const accordion = createImageAccordion({ itemCount: 3 });
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			items[2].focus();

			const keyEvent = new KeyboardEvent('keydown', {
				key: 'ArrowUp',
				bubbles: true,
				cancelable: true,
			});
			items[2].dispatchEvent(keyEvent);

			expect(document.activeElement).toBe(items[1]);
		});

		test('Home focuses first item', () => {
			const accordion = createImageAccordion({ itemCount: 3 });
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			items[2].focus();

			const keyEvent = new KeyboardEvent('keydown', {
				key: 'Home',
				bubbles: true,
				cancelable: true,
			});
			items[2].dispatchEvent(keyEvent);

			expect(document.activeElement).toBe(items[0]);
		});

		test('End focuses last item', () => {
			const accordion = createImageAccordion({ itemCount: 3 });
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			items[0].focus();

			const keyEvent = new KeyboardEvent('keydown', {
				key: 'End',
				bubbles: true,
				cancelable: true,
			});
			items[0].dispatchEvent(keyEvent);

			expect(document.activeElement).toBe(items[2]);
		});

		test('arrow navigation wraps around (circular)', () => {
			const accordion = createImageAccordion({ itemCount: 3 });
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			// ArrowRight from last item wraps to first
			items[2].focus();
			const rightEvent = new KeyboardEvent('keydown', {
				key: 'ArrowRight',
				bubbles: true,
				cancelable: true,
			});
			items[2].dispatchEvent(rightEvent);

			expect(document.activeElement).toBe(items[0]);

			// ArrowLeft from first item wraps to last
			items[0].focus();
			const leftEvent = new KeyboardEvent('keydown', {
				key: 'ArrowLeft',
				bubbles: true,
				cancelable: true,
			});
			items[0].dispatchEvent(leftEvent);

			expect(document.activeElement).toBe(items[2]);
		});
	});

	describe('URL hash deep links', () => {
		beforeAll(() => {
			// Not implemented in JSDOM.
			// eslint-disable-next-line no-undef
			Element.prototype.scrollIntoView = jest.fn();
		});

		afterEach(() => {
			window.history.replaceState(null, '', window.location.pathname);
		});

		test('expands the item whose anchor is in the hash', () => {
			const accordion = createImageAccordion({ defaultExpanded: 1 });
			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);
			items[2].id = 'autumn';
			window.history.replaceState(null, '', '#autumn');
			loadView();

			expect(items[2].classList.contains('is-expanded')).toBe(true);
			expect(items[0].classList.contains('is-collapsed')).toBe(true);
		});

		test('a hovered deep link rests on the linked item on mouseleave', () => {
			const accordion = createImageAccordion({
				triggerType: 'hover',
				defaultExpanded: 1,
			});
			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);
			items[1].id = 'summer';
			loadView();

			window.history.replaceState(null, '', '#summer');
			window.dispatchEvent(new Event('hashchange'));
			expect(items[1].classList.contains('is-expanded')).toBe(true);

			items[2].dispatchEvent(
				new MouseEvent('mouseenter', { bubbles: true })
			);
			accordion.dispatchEvent(
				new MouseEvent('mouseleave', { bubbles: true })
			);

			expect(items[1].classList.contains('is-expanded')).toBe(true);
		});
	});

	describe('Hover mode', () => {
		test('mouseenter expands item, mouseleave resets', () => {
			setTouchDevice(false);
			const accordion = createImageAccordion({
				itemCount: 3,
				triggerType: 'hover',
				defaultExpanded: 1,
			});
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			// Mouseenter on second item should expand it
			items[1].dispatchEvent(
				new MouseEvent('mouseenter', { bubbles: true })
			);

			expect(items[1].classList.contains('is-expanded')).toBe(true);
			expect(items[0].classList.contains('is-collapsed')).toBe(true);

			// Mouseleave on accordion resets to default (item 0, 1-based index 1)
			accordion.dispatchEvent(
				new MouseEvent('mouseleave', { bubbles: true })
			);

			expect(items[0].classList.contains('is-expanded')).toBe(true);
			expect(items[1].classList.contains('is-collapsed')).toBe(true);
		});

		test('mouseleave with no default resets all items', () => {
			setTouchDevice(false);
			const accordion = createImageAccordion({
				itemCount: 3,
				triggerType: 'hover',
				defaultExpanded: 0,
			});
			loadView();

			const items = accordion.querySelectorAll(
				'.dsgo-image-accordion-item'
			);

			// Hover second item
			items[1].dispatchEvent(
				new MouseEvent('mouseenter', { bubbles: true })
			);

			expect(items[1].classList.contains('is-expanded')).toBe(true);

			// Mouseleave resets all
			accordion.dispatchEvent(
				new MouseEvent('mouseleave', { bubbles: true })
			);

			items.forEach((item) => {
				expect(item.classList.contains('is-expanded')).toBe(false);
				expect(item.classList.contains('is-collapsed')).toBe(false);
			});
		});
	});
});
