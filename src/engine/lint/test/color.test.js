/**
 * `parseColor()` accepts the small set of CSS color syntaxes agent-authored
 * attributes use; `contrastRatio()` follows WCAG 2.x relative luminance.
 */
import { parseColor, contrastRatio } from '../color';

describe('parseColor', () => {
	test('parses #rgb shorthand', () => {
		expect(parseColor('#fff')).toEqual({ r: 255, g: 255, b: 255 });
		expect(parseColor('#000')).toEqual({ r: 0, g: 0, b: 0 });
		expect(parseColor('#0f0')).toEqual({ r: 0, g: 255, b: 0 });
	});

	test('parses #rrggbb', () => {
		expect(parseColor('#336699')).toEqual({ r: 51, g: 102, b: 153 });
	});

	test('parses #rrggbbaa, ignoring alpha', () => {
		expect(parseColor('#33669980')).toEqual({ r: 51, g: 102, b: 153 });
	});

	test('parses rgb() and rgba() with integers', () => {
		expect(parseColor('rgb(51, 102, 153)')).toEqual({
			r: 51,
			g: 102,
			b: 153,
		});
		expect(parseColor('rgba(51, 102, 153, 0.5)')).toEqual({
			r: 51,
			g: 102,
			b: 153,
		});
	});

	test('is case-insensitive on hex digits', () => {
		expect(parseColor('#FFFFFF')).toEqual({ r: 255, g: 255, b: 255 });
	});

	test('returns null for anything else', () => {
		expect(parseColor('primary')).toBeNull();
		expect(parseColor('var(--wp--preset--color--primary)')).toBeNull();
		expect(parseColor('not-a-color')).toBeNull();
		expect(parseColor('')).toBeNull();
		expect(parseColor(null)).toBeNull();
		expect(parseColor(undefined)).toBeNull();
		expect(parseColor(42)).toBeNull();
		expect(parseColor('#12345')).toBeNull();
		expect(parseColor('rgb(300, 0, 0)')).toBeNull();
	});
});

describe('contrastRatio', () => {
	test('black on white is 21', () => {
		expect(contrastRatio('#000', '#fff')).toBeCloseTo(21, 5);
	});

	test('white on black is also 21 (order independent)', () => {
		expect(contrastRatio('#fff', '#000')).toBeCloseTo(21, 5);
	});

	test('#777 on #fff is approximately 4.48', () => {
		expect(contrastRatio('#777', '#fff')).toBeCloseTo(4.48, 2);
	});

	test('same color has a ratio of 1', () => {
		expect(contrastRatio('#336699', '#336699')).toBeCloseTo(1, 5);
	});

	test('accepts pre-parsed {r,g,b} objects', () => {
		expect(
			contrastRatio({ r: 0, g: 0, b: 0 }, { r: 255, g: 255, b: 255 })
		).toBeCloseTo(21, 5);
	});

	test('returns null when either color fails to parse', () => {
		expect(contrastRatio('not-a-color', '#fff')).toBeNull();
		expect(contrastRatio('#fff', 'nope')).toBeNull();
	});
});
