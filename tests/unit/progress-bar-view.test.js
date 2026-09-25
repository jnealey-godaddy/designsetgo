/**
 * Progress Bar Block - view.js resolveTargetPercent()
 *
 * Covers the animateOnScroll path's target-width resolution, in particular
 * that it floors `--dsgo-progress-max` at 1 the same way save.js's
 * STATIC_WIDTH_FORMULA and the PHP save mirror (Progress_Bar_Serializer) do —
 * a bound `--dsgo-progress-max` resolving to 0 (or negative) must not produce
 * `Infinity`/`NaN`/a negative percentage.
 *
 * jsdom does not implement CSS custom-property resolution in
 * `getComputedStyle()` (it always reads back `''` for a `--foo` property,
 * even on an attached element with the property set inline), so real custom
 * properties can't drive this test the way a browser would. `getComputedStyle`
 * is mocked instead, returning canned `getPropertyValue()` results per test —
 * this is exactly the seam resolveTargetPercent() itself reads through, so
 * the mock matches production's actual call shape.
 *
 * @package
 */

import { resolveTargetPercent } from '../../src/blocks/progress-bar/view';

/**
 * Mocks `getComputedStyle` (as resolveTargetPercent() calls it — the bare
 * global, not `window.getComputedStyle`) to return the given custom
 * property values from `getPropertyValue()`.
 *
 * @param {Object<string, string>} customProps Custom property name → value
 *                                             (use '' or omit for "not set").
 */
function mockComputedCustomProps(customProps) {
	jest.spyOn(global, 'getComputedStyle').mockReturnValue({
		getPropertyValue: (prop) => customProps[prop] ?? '',
	});
}

describe('progress-bar view.js - resolveTargetPercent', () => {
	const el = {}; // Opaque to resolveTargetPercent(); only passed through to getComputedStyle().

	afterEach(() => {
		jest.restoreAllMocks();
	});

	it('falls back to the static percentage when neither var is set', () => {
		mockComputedCustomProps({});
		expect(resolveTargetPercent(el, 42)).toBe(42);
	});

	it('uses the bound --dsgo-progress value against the default max of 100', () => {
		mockComputedCustomProps({ '--dsgo-progress': '30' });
		expect(resolveTargetPercent(el, 75)).toBe(30);
	});

	it('scales the bound value against a bound --dsgo-progress-max', () => {
		mockComputedCustomProps({
			'--dsgo-progress': '5',
			'--dsgo-progress-max': '10',
		});
		expect(resolveTargetPercent(el, 75)).toBe(50);
	});

	it('floors --dsgo-progress-max at 1 when it resolves to 0, instead of dividing by zero', () => {
		mockComputedCustomProps({
			'--dsgo-progress': '5',
			'--dsgo-progress-max': '0',
		});
		const result = resolveTargetPercent(el, 75);
		expect(Number.isFinite(result)).toBe(true);
		expect(result).not.toBeNaN();
		// max(1, 0) === 1, so 5 / 1 * 100% clamped to 100.
		expect(result).toBe(100);
	});

	it('floors a negative --dsgo-progress-max at 1 the same way', () => {
		mockComputedCustomProps({
			'--dsgo-progress': '5',
			'--dsgo-progress-max': '-10',
		});
		const result = resolveTargetPercent(el, 75);
		expect(Number.isFinite(result)).toBe(true);
		expect(result).not.toBeNaN();
		expect(result).toBe(100);
	});

	it('clamps the result to 0-100 even when the ratio would exceed it', () => {
		mockComputedCustomProps({
			'--dsgo-progress': '999',
			'--dsgo-progress-max': '10',
		});
		expect(resolveTargetPercent(el, 75)).toBe(100);
	});

	it('clamps a negative bound value to 0', () => {
		mockComputedCustomProps({ '--dsgo-progress': '-20' });
		expect(resolveTargetPercent(el, 75)).toBe(0);
	});

	it('ignores a non-numeric --dsgo-progress-max and falls back to 100', () => {
		mockComputedCustomProps({
			'--dsgo-progress': '5',
			'--dsgo-progress-max': 'not-a-number',
		});
		expect(resolveTargetPercent(el, 75)).toBe(5);
	});
});
