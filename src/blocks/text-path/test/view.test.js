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
