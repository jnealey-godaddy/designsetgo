/**
 * Text reveal - scroll progress.
 *
 * The reveal used to finish only when the element's centre reached the
 * viewport centre. Copy near the bottom of a document, or on a page too
 * short to scroll, can never travel that far - which the colour effect
 * survived (it just stayed the base colour) but "Fade & Rise" does not:
 * its unrevealed units sit at opacity 0, so the text stays invisible.
 *
 * @package
 */

import { updateRevealProgress } from '../../../src/extensions/text-reveal/frontend';

const VIEWPORT = 800;

/**
 * Build an element whose measured box and page scroll are both controlled.
 *
 * @param {Object} options            Options.
 * @param {number} options.top        Element top, in viewport coordinates.
 * @param {number} options.height     Element height.
 * @param {number} options.scrollY    Current scroll position.
 * @param {number} options.pageHeight Total document height.
 * @return {Object} `{ element, spans }`.
 */
function setup({ top, height = 100, scrollY = 0, pageHeight = VIEWPORT }) {
	document.body.innerHTML =
		'<p id="t">' +
		'<span class="dsgo-text-reveal-unit">a</span>' +
		'<span class="dsgo-text-reveal-unit">b</span>' +
		'<span class="dsgo-text-reveal-unit">c</span>' +
		'<span class="dsgo-text-reveal-unit">d</span>' +
		'</p>';

	const element = document.getElementById('t');
	element.getBoundingClientRect = () => ({ top, height });

	window.innerHeight = VIEWPORT;
	window.scrollY = scrollY;
	Object.defineProperty(document.documentElement, 'scrollHeight', {
		configurable: true,
		value: pageHeight,
	});
	Object.defineProperty(document.body, 'scrollHeight', {
		configurable: true,
		value: pageHeight,
	});

	return {
		element,
		spans: element.querySelectorAll('.dsgo-text-reveal-unit'),
	};
}

const revealed = (element) =>
	element.querySelectorAll('.dsgo-text-reveal-unit.is-revealed').length;

describe('updateRevealProgress', () => {
	it('reveals nothing before the element enters the reveal band', () => {
		const { element, spans } = setup({ top: 780, pageHeight: 4000 });
		updateRevealProgress(element, spans, spans.length);
		expect(revealed(element)).toBe(0);
	});

	it('reveals everything once the element centre hits the viewport centre', () => {
		const { element, spans } = setup({
			top: 350,
			scrollY: 500,
			pageHeight: 4000,
		});
		updateRevealProgress(element, spans, spans.length);
		expect(revealed(element)).toBe(spans.length);
	});

	it('completes at the bottom of a long page even though the centre never rises that far', () => {
		// Fully scrolled: 4000 - 800 = 3200, and the element sits low in the
		// viewport, so its centre stays well below the halfway line.
		const { element, spans } = setup({
			top: 700,
			scrollY: 3200,
			pageHeight: 4000,
		});
		updateRevealProgress(element, spans, spans.length);
		expect(revealed(element)).toBe(spans.length);
	});

	it('completes on a page too short to scroll at all', () => {
		const { element, spans } = setup({ top: 700, pageHeight: VIEWPORT });
		updateRevealProgress(element, spans, spans.length);
		expect(revealed(element)).toBe(spans.length);
	});

	it('still ramps partway through with scroll left to give', () => {
		const { element, spans } = setup({
			top: 500,
			scrollY: 500,
			pageHeight: 4000,
		});
		updateRevealProgress(element, spans, spans.length);

		const count = revealed(element);
		expect(count).toBeGreaterThan(0);
		expect(count).toBeLessThan(spans.length);
	});
});
