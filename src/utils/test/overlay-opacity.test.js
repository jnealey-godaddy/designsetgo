/**
 * Tests for overlayOpacityFraction — the shared percent→fraction clamp/fallback
 * used by scroll-slides save.js and edit.js (and mirrored in render.php).
 *
 * @package
 */

import { overlayOpacityFraction } from '../overlay-opacity';

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
