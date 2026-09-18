/**
 * Tests for overlayOpacityFraction — the shared percent→fraction clamp/fallback
 * used by scroll-slides save.js and edit.js (and mirrored in render.php).
 *
 * @package
 */

import {
	DEFAULT_OVERLAY_OPACITY,
	LEGACY_OVERLAY_OPACITY_PERCENT,
	getOverlayOpacity,
	overlayOpacityFraction,
	withLegacyOverlayOpacity,
} from '../overlay-opacity';
import fixture from '../../../tests/fixtures/overlay-opacity-cases.json';

describe('overlayOpacityFraction', () => {
	it('maps the 80 default to 0.8 (backward compatible)', () => {
		expect(overlayOpacityFraction(80)).toBe(0.8);
	});

	it('maps a mid-range percent to a fraction', () => {
		expect(overlayOpacityFraction(50)).toBe(0.5);
	});

	it('preserves 0 (not treated as unset)', () => {
		expect(overlayOpacityFraction(0)).toBe(0);
	});

	it('clamps above-range values to 1', () => {
		expect(overlayOpacityFraction(150)).toBe(1);
	});

	it('clamps below-range values to 0', () => {
		expect(overlayOpacityFraction(-20)).toBe(0);
	});

	it('falls back to 0.8 for non-finite values', () => {
		expect(overlayOpacityFraction(undefined)).toBe(0.8);
		expect(overlayOpacityFraction(NaN)).toBe(0.8);
		expect(overlayOpacityFraction(Infinity)).toBe(0.8);
	});
});

describe('getOverlayOpacity', () => {
	it('defaults to 0.65', () => {
		expect(DEFAULT_OVERLAY_OPACITY).toBe('0.65');
	});

	it.each([
		['var:preset|color|contrast', '0.65'],
		['#121212', '0.65'],
		['#1212127D', '1'],
		['#1217', '1'],
		['rgba(0,0,0,.4)', '1'],
		['rgb(0 0 0 / 40%)', '1'],
		['rgba(0,0,0,1)', '0.65'],
	])('%s -> %s', (color, expected) => {
		expect(getOverlayOpacity(color)).toBe(expected);
	});

	it.each(fixture.cases)(
		'matches the shared PHP/JS expectation for "$color"',
		({ color, opacity }) => {
			expect(getOverlayOpacity(color)).toBe(opacity);
		}
	);

	it('falls back to the default for non-string values', () => {
		expect(getOverlayOpacity(undefined)).toBe('0.65');
		expect(getOverlayOpacity(null)).toBe('0.65');
	});
});

describe('getOverlayOpacity with an explicit overlayOpacity', () => {
	it.each(fixture.percentCases)(
		'matches the shared PHP/JS expectation for $color at $percent',
		({ color, percent, opacity }) => {
			expect(
				getOverlayOpacity(color, percent === null ? undefined : percent)
			).toBe(opacity);
		}
	);

	it('pins the legacy strength at 0.8', () => {
		expect(
			getOverlayOpacity('#121212', LEGACY_OVERLAY_OPACITY_PERCENT)
		).toBe('0.8');
	});
});

describe('withLegacyOverlayOpacity', () => {
	it('pins 80 onto a migrated overlay and keeps the entry identity', () => {
		const entry = { migrate: (attributes) => ({ ...attributes, a: 1 }) };
		const list = [entry];

		expect(withLegacyOverlayOpacity(list)).toBe(list);
		expect(list[0]).toBe(entry);
		expect(entry.migrate({ overlayColor: '#000' })).toEqual({
			overlayColor: '#000',
			a: 1,
			overlayOpacity: 80,
		});
	});

	it('adds a migrate() to an entry without one', () => {
		const [entry] = withLegacyOverlayOpacity([{}]);
		expect(entry.migrate({ overlayColor: '#000' })).toEqual({
			overlayColor: '#000',
			overlayOpacity: 80,
		});
	});

	it('keeps the innerBlocks of a tuple-returning migrate()', () => {
		const inner = [{ name: 'core/paragraph' }];
		const [entry] = withLegacyOverlayOpacity([
			{ migrate: (attributes, innerBlocks) => [attributes, innerBlocks] },
		]);
		expect(entry.migrate({ overlayColor: '#000' }, inner)).toEqual([
			{ overlayColor: '#000', overlayOpacity: 80 },
			inner,
		]);
	});

	it('leaves blocks without an overlay colour, or with an opacity, alone', () => {
		const [entry] = withLegacyOverlayOpacity([{}]);
		expect(entry.migrate({ className: 'is-style-overlay-dark' })).toEqual({
			className: 'is-style-overlay-dark',
		});
		expect(
			entry.migrate({ overlayColor: '#000', overlayOpacity: 40 })
		).toEqual({ overlayColor: '#000', overlayOpacity: 40 });
	});

	it('wraps an entry listed twice only once', () => {
		const calls = [];
		const entry = {
			migrate: (attributes) => {
				calls.push(1);
				return attributes;
			},
		};
		withLegacyOverlayOpacity([entry]);
		withLegacyOverlayOpacity([entry]);
		entry.migrate({ overlayColor: '#000' });
		expect(calls).toHaveLength(1);
	});
});
