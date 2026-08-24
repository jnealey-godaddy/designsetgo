import { existsSync } from 'fs';
import { resolve } from 'path';

describe('text path motion', () => {
	test('ships a frontend motion controller', () => {
		expect(existsSync(resolve(__dirname, '../view.js'))).toBe(true);
	});

	test('advances the saved textPath offset over its configured duration', () => {
		const frames = [];
		window.matchMedia = jest.fn(() => ({ matches: false }));
		global.requestAnimationFrame = jest.fn((callback) => {
			frames.push(callback);
			return frames.length;
		});
		document.body.innerHTML = `
			<div data-dsgo-text-path-motion="true" data-dsgo-text-path-motion-duration="12" data-dsgo-text-path-motion-direction="forward">
				<svg><text><textPath data-dsgo-text-path-offset="5" startOffset="5%">Moving text</textPath></text></svg>
			</div>
		`;

		jest.isolateModules(() => {
			require('../view');
		});
		frames.shift()(1000);
		frames.shift()(7000);

		expect(
			document.querySelector('textPath').getAttribute('startOffset')
		).toBe('55%');
	});

	test('resumes from its current offset after the document becomes visible again', () => {
		const frames = [];
		window.matchMedia = jest.fn(() => ({ matches: false }));
		global.requestAnimationFrame = jest.fn((callback) => {
			frames.push(callback);
			return frames.length;
		});
		global.cancelAnimationFrame = jest.fn();
		document.body.innerHTML = `
			<div data-dsgo-text-path-motion="true" data-dsgo-text-path-motion-duration="12" data-dsgo-text-path-motion-direction="forward">
				<svg><text><textPath data-dsgo-text-path-offset="5" startOffset="5%">Moving text</textPath></text></svg>
			</div>
		`;

		jest.isolateModules(() => {
			require('../view');
		});
		frames.shift()(1000);
		frames.shift()(7000);
		expect(
			document.querySelector('textPath').getAttribute('startOffset')
		).toBe('55%');

		Object.defineProperty(document, 'hidden', {
			configurable: true,
			value: true,
		});
		document.dispatchEvent(new Event('visibilitychange'));
		Object.defineProperty(document, 'hidden', {
			configurable: true,
			value: false,
		});
		document.dispatchEvent(new Event('visibilitychange'));

		frames.pop()(17000);

		expect(
			document.querySelector('textPath').getAttribute('startOffset')
		).toBe('55%');
	});

	test('keeps animated text on an open spiral path instead of running it off the end', () => {
		const frames = [];
		window.matchMedia = jest.fn(() => ({ matches: false }));
		global.requestAnimationFrame = jest.fn((callback) => {
			frames.push(callback);
			return frames.length;
		});
		document.body.innerHTML = `
			<div data-dsgo-text-path-motion="true" data-dsgo-text-path-motion-duration="12" data-dsgo-text-path-motion-direction="forward">
				<svg>
					<defs><path id="spiral-guide" /></defs>
					<text><textPath href="#spiral-guide" data-dsgo-text-path-offset="0" startOffset="0%">Moving text</textPath></text>
				</svg>
			</div>
		`;
		const guide = document.getElementById('spiral-guide');
		guide.getTotalLength = () => 1000;
		guide.getPointAtLength = (length) =>
			length === 0 ? { x: 0, y: 0 } : { x: 1000, y: 0 };
		document.querySelector('textPath').getComputedTextLength = () => 200;

		jest.isolateModules(() => {
			require('../view');
		});
		frames.shift()(1000);
		frames.shift()(12000);

		expect(
			Number(
				document
					.querySelector('textPath')
					.getAttribute('startOffset')
					.replace('%', '')
			)
		).toBeLessThanOrEqual(80);
	});

	test('bounds a spiral when the browser does not expose SVG length APIs', () => {
		const frames = [];
		window.matchMedia = jest.fn(() => ({ matches: false }));
		global.requestAnimationFrame = jest.fn((callback) => {
			frames.push(callback);
			return frames.length;
		});
		document.body.innerHTML = `
			<div data-dsgo-text-path-motion="true" data-dsgo-text-path-motion-duration="12" data-dsgo-text-path-motion-direction="forward">
				<svg>
					<defs><path id="spiral-guide" d="M 500 500 C 500 250 850 250 850 500 C 850 850 150 850 150 500 C 150 50 950 50 950 500" /></defs>
					<text><textPath href="#spiral-guide" data-dsgo-text-path-offset="0" startOffset="0%">Moving text</textPath></text>
				</svg>
			</div>
		`;

		jest.isolateModules(() => {
			require('../view');
		});
		frames.shift()(1000);
		frames.shift()(12000);

		expect(
			Number(
				document
					.querySelector('textPath')
					.getAttribute('startOffset')
					.replace('%', '')
			)
		).toBeLessThanOrEqual(80);
	});

	test('leaves the readable static offset in place when reduced motion is requested', () => {
		window.matchMedia = jest.fn(() => ({ matches: true }));
		global.requestAnimationFrame = jest.fn();
		document.body.innerHTML = `
			<div data-dsgo-text-path-motion="true" data-dsgo-text-path-motion-duration="12" data-dsgo-text-path-motion-direction="forward">
				<svg><text><textPath data-dsgo-text-path-offset="5" startOffset="5%">Static text</textPath></text></svg>
			</div>
		`;

		jest.isolateModules(() => {
			require('../view');
		});

		expect(global.requestAnimationFrame).not.toHaveBeenCalled();
		expect(
			document.querySelector('textPath').getAttribute('startOffset')
		).toBe('5%');
	});
});
