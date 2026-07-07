import { resolveInheritedPattern } from '../../../../src/extensions/svg-patterns/utils/resolve-inherited-pattern';
import {
	INHERIT_FALLBACK,
	RANGES,
} from '../../../../src/extensions/svg-patterns/constants';
import { PATTERNS } from '../../../../src/extensions/svg-patterns/pattern-data';

test('falls back to in-plugin defaults when theme preset is empty', () => {
	expect(resolveInheritedPattern(undefined)).toEqual(INHERIT_FALLBACK);
	expect(resolveInheritedPattern({})).toEqual(INHERIT_FALLBACK);
});

test('uses theme values when present', () => {
	const themed = resolveInheritedPattern({
		type: 'waves',
		color: '#123456',
		opacity: 0.2,
		scale: 2,
	});
	expect(themed).toEqual({
		type: 'waves',
		color: '#123456',
		opacity: 0.2,
		scale: 2,
	});
});

test('each field falls back independently', () => {
	const partial = resolveInheritedPattern({ type: 'grain' });
	expect(partial.type).toBe('grain');
	expect(partial.color).toBe(INHERIT_FALLBACK.color);
	expect(partial.opacity).toBe(INHERIT_FALLBACK.opacity);
	expect(partial.scale).toBe(INHERIT_FALLBACK.scale);
});

test('rejects an unknown theme pattern slug and falls back', () => {
	const bad = resolveInheritedPattern({ type: 'not-a-real-pattern' });
	expect(PATTERNS[bad.type]).toBeDefined();
	expect(bad.type).toBe(INHERIT_FALLBACK.type);
});

test('clamps zero opacity/scale to the range minimum (matches PHP)', () => {
	// PHP clamps opacity to >= 0.05 and scale to >= 0.25; 0 must NOT fall
	// back to the default (which would render differently on the frontend).
	const clamped = resolveInheritedPattern({ opacity: 0, scale: 0 });
	expect(clamped.opacity).toBe(RANGES.opacity.min);
	expect(clamped.scale).toBe(RANGES.scale.min);
});

test('clamps out-of-range opacity/scale to the range bounds', () => {
	const high = resolveInheritedPattern({ opacity: 5, scale: 99 });
	expect(high.opacity).toBe(RANGES.opacity.max);
	expect(high.scale).toBe(RANGES.scale.max);

	const low = resolveInheritedPattern({ opacity: -1, scale: -3 });
	expect(low.opacity).toBe(RANGES.opacity.min);
	expect(low.scale).toBe(RANGES.scale.min);
});

test('non-finite numeric values fall back to defaults', () => {
	const nan = resolveInheritedPattern({
		opacity: Number.NaN,
		scale: Number.POSITIVE_INFINITY,
	});
	expect(nan.opacity).toBe(INHERIT_FALLBACK.opacity);
	expect(nan.scale).toBe(INHERIT_FALLBACK.scale);
});
