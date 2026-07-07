import { resolveInheritedPattern } from '../../../../src/extensions/svg-patterns/utils/resolve-inherited-pattern';
import { INHERIT_FALLBACK } from '../../../../src/extensions/svg-patterns/constants';
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
