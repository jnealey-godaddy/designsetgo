/**
 * Form field width helper tests.
 *
 * Expectations come from tests/fixtures/form-field-width-cases.json, which the
 * PHPUnit test for designsetgo_form_field_width_style() also reads, so the
 * editor preview and the server render emit the same strings.
 */

import { getFormFieldWidth, getFormFieldWidthStyle } from '../field-width';
import fixture from '../../../../../tests/fixtures/form-field-width-cases.json';

/**
 * Parse `calc(P% - var(--dsgo-form-field-spacing, 1.5rem) * F)`.
 *
 * @param {string} value Width value.
 * @return {{percent: number, gapFactor: number}} Parsed parts.
 */
function parseWidth(value) {
	if (value === '100%') {
		return { percent: 100, gapFactor: 0 };
	}
	const match = value.match(
		/^calc\(([\d.]+)% - var\(--dsgo-form-field-spacing, 1\.5rem\) \* ([\d.]+)\)$/
	);
	if (!match) {
		throw new Error(`Unexpected width value: ${value}`);
	}
	return { percent: parseFloat(match[1]), gapFactor: parseFloat(match[2]) };
}

describe('getFormFieldWidth', () => {
	it.each(fixture.cases)(
		'matches the shared PHP/JS expectation for width "$width"',
		({ width, value }) => {
			expect(getFormFieldWidth(width)).toBe(value);
		}
	);

	it.each(fixture.cases)(
		'builds the same declarations as the PHP style string for width "$width"',
		({ width, style }) => {
			const { flexBasis, maxWidth } = getFormFieldWidthStyle(width);
			expect(`flex-basis:${flexBasis};max-width:${maxWidth}`).toBe(style);
		}
	);

	it('treats missing or non-numeric widths as full width', () => {
		expect(getFormFieldWidth(undefined)).toBe('100%');
		expect(getFormFieldWidth('abc')).toBe('100%');
		expect(getFormFieldWidth(50)).toBe(getFormFieldWidth('50'));
	});

	// N fields of width W plus N-1 gaps must fill exactly one row. Percent and
	// gap are independent units, so each must sum on its own: N * P = 100 and
	// N * F = N - 1 (the gaps the fields give up equal the gaps in the row).
	it.each([
		['50', 2],
		['33', 3],
		['25', 4],
	])('%s%% x %i fields plus gaps fill exactly one row', (width, count) => {
		const { percent, gapFactor } = parseWidth(getFormFieldWidth(width));
		const gapsInRow = count - 1;

		expect(count * percent).toBeCloseTo(100, 2);
		expect(count * gapFactor).toBeCloseTo(gapsInRow, 3);
		// Rounding must never over-fill the row: fields may give up slightly
		// more gap and take slightly less percent, never the reverse. (The old
		// "half a gap" formula gave up only 1.5 gaps for a 2-gap row.)
		expect(count * gapFactor).toBeGreaterThanOrEqual(gapsInRow);
		expect(count * percent).toBeLessThanOrEqual(100);
	});

	it('66% + 33% fields fill exactly one row', () => {
		const two = parseWidth(getFormFieldWidth('66'));
		const one = parseWidth(getFormFieldWidth('33'));

		expect(two.percent + one.percent).toBeCloseTo(100, 3);
		expect(two.percent + one.percent).toBeLessThanOrEqual(100);
		expect(two.gapFactor + one.gapFactor).toBeCloseTo(1, 3);
	});
});
