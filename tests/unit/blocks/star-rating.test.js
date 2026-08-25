/**
 * Star Rating — value math and default parity.
 *
 * Two things are worth pinning here. The math, because three consumers
 * (editor preview, render.php, JSON-LD builder) have to agree on it. And the
 * defaults, because the editor's reset-to-default behaviour reads them from a
 * JS object while WordPress reads them from block.json — a value changed in
 * one place and not the other resets a control to something that was never
 * the default.
 *
 * @package
 */

import blockJson from '../../../src/blocks/star-rating/block.json';
import { DEFAULTS } from '../../../src/blocks/star-rating/utils/defaults';
import {
	clampMaxRating,
	clampRating,
	formatCount,
	formatRatingValue,
	getFillPercent,
	snapToPrecision,
	MAX_MAX_RATING,
} from '../../../src/blocks/star-rating/utils/rating';

describe('star-rating defaults', () => {
	it('matches block.json for every attribute it declares', () => {
		Object.entries(DEFAULTS).forEach(([name, value]) => {
			expect(blockJson.attributes[name]).toBeDefined();
			expect(blockJson.attributes[name].default).toBe(value);
		});
	});

	it('covers every attribute block.json gives a default', () => {
		const withDefaults = Object.entries(blockJson.attributes)
			.filter(([, schema]) => schema.default !== undefined)
			.map(([name]) => name);

		expect(withDefaults.sort()).toEqual(Object.keys(DEFAULTS).sort());
	});
});

describe('clampMaxRating', () => {
	it('rounds to a whole number of icons', () => {
		expect(clampMaxRating(5.4)).toBe(5);
		expect(clampMaxRating(5.6)).toBe(6);
	});

	it('caps a runaway value from a bound source', () => {
		expect(clampMaxRating(10000)).toBe(MAX_MAX_RATING);
		expect(clampMaxRating(0)).toBe(1);
		expect(clampMaxRating(-3)).toBe(1);
	});

	it('falls back to five for junk', () => {
		expect(clampMaxRating('not a number')).toBe(5);
		expect(clampMaxRating(undefined)).toBe(5);
	});
});

describe('clampRating', () => {
	it('holds the value inside 0..max', () => {
		expect(clampRating(7, 5)).toBe(5);
		expect(clampRating(-2, 5)).toBe(0);
		expect(clampRating(3.7, 5)).toBe(3.7);
	});

	it('reads a numeric string, since bound sources return those', () => {
		expect(clampRating('4.00', 5)).toBe(4);
	});

	it('reads junk as no rating rather than NaN', () => {
		expect(clampRating('', 5)).toBe(0);
		expect(clampRating(null, 5)).toBe(0);
	});
});

describe('snapToPrecision', () => {
	it('rounds to halves', () => {
		expect(snapToPrecision(4.3, 'half')).toBe(4.5);
		expect(snapToPrecision(4.2, 'half')).toBe(4);
	});

	it('rounds to whole stars', () => {
		expect(snapToPrecision(4.5, 'full')).toBe(5);
		expect(snapToPrecision(4.4, 'full')).toBe(4);
	});

	it('leaves an exact value alone', () => {
		expect(snapToPrecision(4.37, 'exact')).toBe(4.37);
	});
});

describe('getFillPercent', () => {
	it('measures the snapped value against the scale', () => {
		expect(getFillPercent(4.5, 5, 'half')).toBe(90);
		expect(getFillPercent(4.3, 5, 'exact')).toBe(86);
		expect(getFillPercent(4.3, 5, 'full')).toBe(80);
	});

	it('never leaves the 0..100 range', () => {
		expect(getFillPercent(99, 5, 'exact')).toBe(100);
		expect(getFillPercent(-1, 5, 'exact')).toBe(0);
	});

	it('clamps the scale before dividing by it', () => {
		expect(getFillPercent(3, 0, 'exact')).toBe(100);
	});
});

describe('formatRatingValue', () => {
	it('drops the decimal on whole numbers', () => {
		expect(formatRatingValue(4)).toBe('4');
		expect(formatRatingValue(4.0)).toBe('4');
	});

	it('keeps one place otherwise', () => {
		expect(formatRatingValue(4.5)).toBe('4.5');
		expect(formatRatingValue(4.44)).toBe('4.4');
	});
});

describe('formatCount', () => {
	it('substitutes the placeholder', () => {
		expect(formatCount('(%s)', 128)).toBe('(128)');
		expect(formatCount('%s reviews', 3)).toBe('3 reviews');
	});

	it('falls back to the bare number when the template has no placeholder', () => {
		expect(formatCount('reviews', 12)).toBe('12');
		expect(formatCount('', 12)).toBe('12');
	});

	it('survives a template holding other percent tokens', () => {
		// The template is author input; sprintf semantics would throw here.
		expect(formatCount('%s of 100%', 40)).toBe('40 of 100%');
	});

	// The PHP twin runs the count through number_format_i18n(), which groups
	// thousands. A bare String() here would print "1284" in the canvas next to
	// "1,284" on the published page.
	it('groups thousands the way number_format_i18n() does', () => {
		expect(formatCount('(%s)', 1284)).toBe('(1,284)');
	});
});
