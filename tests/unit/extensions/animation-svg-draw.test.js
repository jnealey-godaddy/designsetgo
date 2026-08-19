/**
 * SVG path drawing.
 *
 * @package
 */

import { initSvgDraw } from '../../../src/extensions/block-animations/svg-draw';

const mount = () => {
	document.body.innerHTML = `
		<div data-dsgo-svg-draw="true">
			<svg viewBox="0 0 100 100">
				<path d="M0 0 L100 100"></path>
				<path d="M0 100 L100 0"></path>
			</svg>
		</div>
	`;
	document.querySelectorAll('path').forEach((p) => {
		p.getTotalLength = () => 141;
	});
	initSvgDraw();
};

describe('svg draw', () => {
	afterEach(() => {
		document.body.innerHTML = '';
	});

	it('sets dasharray and dashoffset from the measured length', () => {
		mount();
		const path = document.querySelector('path');
		expect(path.style.strokeDasharray).toBe('141');
		expect(path.style.strokeDashoffset).toBe('141');
	});

	it('prepares every path, not just the first', () => {
		mount();
		const offsets = Array.from(document.querySelectorAll('path')).map(
			(p) => p.style.strokeDashoffset
		);
		expect(offsets).toEqual(['141', '141']);
	});

	it('marks the container as prepared', () => {
		mount();
		expect(
			document
				.querySelector('[data-dsgo-svg-draw]')
				.classList.contains('dsgo-svg-draw-ready')
		).toBe(true);
	});

	it('ignores an element with no measurable geometry', () => {
		document.body.innerHTML = `
			<div data-dsgo-svg-draw="true"><svg><path d="M0 0"></path></svg></div>
		`;
		// No getTotalLength stub: jsdom does not implement it.
		expect(() => initSvgDraw()).not.toThrow();
	});

	it('is idempotent', () => {
		mount();
		initSvgDraw();
		expect(document.querySelector('path').style.strokeDashoffset).toBe(
			'141'
		);
	});

	it('does nothing when reduced motion is preferred', () => {
		const original = window.matchMedia;
		window.matchMedia = jest.fn().mockReturnValue({ matches: true });
		mount();
		expect(document.querySelector('path').style.strokeDashoffset).toBe('');
		window.matchMedia = original;
	});
});
