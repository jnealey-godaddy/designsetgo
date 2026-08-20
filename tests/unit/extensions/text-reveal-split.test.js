/**
 * Text reveal - split-text units.
 *
 * The extension already splits into words/characters; these pin the unit
 * contract the "Fade & Rise" effect depends on (ordering index, accessible
 * text, markup preservation, idempotency).
 *
 * @package
 */

import { wrapTextNodes } from '../../../src/extensions/text-reveal/frontend';

const units = (el) => el.querySelectorAll('.dsgo-text-reveal-unit');

describe('wrapTextNodes', () => {
	let el;

	beforeEach(() => {
		document.body.innerHTML = '<h2 id="t">Hello brave world</h2>';
		el = document.getElementById('t');
	});

	it('wraps each word in word mode', () => {
		wrapTextNodes(el, 'word');
		expect(units(el)).toHaveLength(3);
	});

	it('indexes the units in order', () => {
		wrapTextNodes(el, 'word');
		const indices = Array.from(units(el)).map((u) =>
			u.style.getPropertyValue('--dsgo-unit-index')
		);
		expect(indices).toEqual(['0', '1', '2']);
	});

	it('wraps each character in character mode, excluding spaces', () => {
		document.body.innerHTML = '<h2 id="t">ab cd</h2>';
		const chars = document.getElementById('t');
		wrapTextNodes(chars, 'character');
		expect(units(chars)).toHaveLength(4);
	});

	it('preserves the original text for assistive tech', () => {
		wrapTextNodes(el, 'word');
		expect(el.getAttribute('aria-label')).toBe('Hello brave world');
		expect(units(el)[0].getAttribute('aria-hidden')).toBe('true');
	});

	it('leaves the visible text unchanged', () => {
		wrapTextNodes(el, 'word');
		expect(el.textContent.replace(/\s+/g, ' ').trim()).toBe(
			'Hello brave world'
		);
	});

	it('is idempotent - splitting twice does not nest units', () => {
		wrapTextNodes(el, 'word');
		wrapTextNodes(el, 'word');
		expect(units(el)).toHaveLength(3);
	});

	it('is idempotent for a single animated word reused by another effect', () => {
		document.body.innerHTML =
			'<span id="animated" aria-live="polite">Animated</span>';
		const animated = document.getElementById('animated');

		wrapTextNodes(animated, 'character');
		wrapTextNodes(animated, 'character');

		expect(units(animated)).toHaveLength(8);
		expect(animated).toHaveAttribute('aria-label', 'Animated');
		expect(animated).toHaveAttribute('aria-live', 'polite');
	});

	it('keeps inline markup rather than flattening it', () => {
		document.body.innerHTML = '<h2 id="t">Hi <a href="#">link</a></h2>';
		const withMarkup = document.getElementById('t');
		wrapTextNodes(withMarkup, 'word');
		expect(withMarkup.querySelector('a')).not.toBeNull();
		expect(units(withMarkup)).toHaveLength(2);
	});

	it('indexes across separate text nodes continuously', () => {
		document.body.innerHTML = '<h2 id="t">one <em>two</em> three</h2>';
		const mixed = document.getElementById('t');
		wrapTextNodes(mixed, 'word');
		const indices = Array.from(units(mixed)).map((u) =>
			u.style.getPropertyValue('--dsgo-unit-index')
		);
		expect(indices).toEqual(['0', '1', '2']);
	});

	it('does nothing for empty text', () => {
		document.body.innerHTML = '<h2 id="t"></h2>';
		const empty = document.getElementById('t');
		wrapTextNodes(empty, 'word');
		expect(units(empty)).toHaveLength(0);
	});
});
