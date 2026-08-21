import {
	getTextPathData,
	getTextPathShapeOptions,
	TEXT_PATH_PRESETS,
	extractTextPathFromSvg,
	normaliseTextPathData,
} from '../../src/utils/svg-paths';

describe('text path presets', () => {
	it('exposes the stable preset names with safe path data', () => {
		expect(Object.keys(TEXT_PATH_PRESETS)).toEqual([
			'wave',
			'arc',
			'circle',
			'line',
			'oval',
			'spiral',
		]);

		Object.values(TEXT_PATH_PRESETS).forEach((preset) => {
			expect(normaliseTextPathData(preset)).toEqual(preset);
		});
	});

	it('uses the shared shape registry for options and arc geometry', () => {
		expect(getTextPathShapeOptions()).toEqual([
			{ label: 'Wave', value: 'wave' },
			{ label: 'Arc', value: 'arc' },
			{ label: 'Circle', value: 'circle' },
			{ label: 'Line', value: 'line' },
			{ label: 'Oval', value: 'oval' },
			{ label: 'Spiral', value: 'spiral' },
		]);

		expect(getTextPathData({ pathType: 'arc', arcSize: 0 })).toEqual({
			viewBox: '0 0 1000 200',
			d: 'M 0 200 Q 500 200 1000 200',
		});
		expect(getTextPathData({ pathType: 'missing' })).toEqual(
			TEXT_PATH_PRESETS.wave
		);
	});
});

describe('normaliseTextPathData', () => {
	it('returns only normalized, safe viewBox and path data', () => {
		expect(
			normaliseTextPathData({
				viewBox: ' 0 0 100 50 ',
				d: ' M 0 25 C 25 0 75 50 100 25 ',
				extra: 'discarded',
			})
		).toEqual({
			viewBox: '0 0 100 50',
			d: 'M 0 25 C 25 0 75 50 100 25',
		});
	});

	it('accepts a closed path', () => {
		expect(
			normaliseTextPathData({
				viewBox: '0 0 100 100',
				d: 'M 0 0 L 100 0 L 100 100 Z',
			})
		).toEqual({
			viewBox: '0 0 100 100',
			d: 'M 0 0 L 100 0 L 100 100 Z',
		});
	});

	it('accepts a valid comma-separated closed path', () => {
		expect(
			normaliseTextPathData({
				viewBox: '0 0 100 100',
				d: 'M0,0 L100,0 L100,100 Z',
			})
		).toEqual({
			viewBox: '0 0 100 100',
			d: 'M0,0 L100,0 L100,100 Z',
		});
	});

	it.each(['M,,0,,0 L,,1,,1', 'M 0 0 L 1 1,,', 'M 0 0 L 1 1 M L 2 2'])(
		'rejects malformed SVG separators and incomplete commands: %s',
		(d) => {
			expect(
				normaliseTextPathData({
					viewBox: '0 0 100 100',
					d,
				})
			).toBeNull();
		}
	);

	it.each([
		[null],
		[{}],
		[{ viewBox: '0 0 100', d: 'M0 0 L100 0' }],
		[{ viewBox: '0 0 0 100', d: 'M0 0 L100 0' }],
		[{ viewBox: '0 0 100 infinity', d: 'M0 0 L100 0' }],
		[{ viewBox: '0 0 100 100', d: '' }],
		[{ viewBox: '0 0 100 100', d: 'M0 0' }],
		[{ viewBox: '0 0 100 100', d: 'L100 0' }],
		[{ viewBox: '0 0 100 100', d: 'M0 0 <script>' }],
		[{ viewBox: '0 0 100 100', d: 'M0 0 url(javascript:alert(1))' }],
	])('rejects unsafe or invalid path data: %p', (candidate) => {
		expect(normaliseTextPathData(candidate)).toBeNull();
	});

	it('rejects path data over 12KB before parsing', () => {
		expect(
			normaliseTextPathData({
				viewBox: '0 0 100 100',
				d: `M0 0 ${'L1 1 '.repeat(3000)}`,
			})
		).toBeNull();
	});
});

describe('extractTextPathFromSvg', () => {
	it('extracts the first usable path from a safe SVG document', () => {
		const svg = [
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">',
			'<path d="M0 50 C50 0 150 100 200 50" />',
			'<path d="M0 0 L200 100" />',
			'</svg>',
		].join('');

		expect(extractTextPathFromSvg(svg)).toEqual({
			viewBox: '0 0 200 100',
			d: 'M0 50 C50 0 150 100 200 50',
		});
	});

	it.each([
		[''],
		['<svg viewBox="0 0 100 100"><path d="M0 0"></svg>'],
		[
			'<!DOCTYPE svg><svg viewBox="0 0 100 100"><path d="M0 0 L100 0" /></svg>',
		],
		[
			'<!ENTITY xxe SYSTEM "file:///etc/passwd"><svg viewBox="0 0 100 100"><path d="M0 0 L100 0" /></svg>',
		],
		[
			'<svg viewBox="0 0 100 100"><script>alert(1)</script><path d="M0 0 L100 0" /></svg>',
		],
		[
			'<svg viewBox="0 0 100 100"><foreignObject><div>nope</div></foreignObject><path d="M0 0 L100 0" /></svg>',
		],
		['<div viewBox="0 0 100 100"><path d="M0 0 L100 0" /></div>'],
		['<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="50" /></svg>'],
	])('rejects unsafe or unusable SVG: %s', (svg) => {
		expect(extractTextPathFromSvg(svg)).toBeNull();
	});

	it('rejects SVG over 12KB before parsing', () => {
		expect(
			extractTextPathFromSvg(
				`<svg viewBox="0 0 100 100"><path d="M0 0 ${'L1 1 '.repeat(3000)}" /></svg>`
			)
		).toBeNull();
	});
});
