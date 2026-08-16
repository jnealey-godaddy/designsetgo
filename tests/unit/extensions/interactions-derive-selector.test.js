import {
	deriveSelector,
	firstCustomClass,
	makeTargetClass,
} from '../../../src/extensions/interactions/derive-selector';

describe('firstCustomClass', () => {
	it('ignores WordPress-generated classes', () => {
		expect(
			firstCustomClass(
				'wp-block-group is-layout-flow has-background my-panel'
			)
		).toBe('my-panel');
	});

	it('returns empty when every class is generated', () => {
		expect(firstCustomClass('wp-block-group is-layout-flow')).toBe('');
	});

	it('tolerates an empty or missing class list', () => {
		expect(firstCustomClass('')).toBe('');
		expect(firstCustomClass(undefined)).toBe('');
	});
});

describe('makeTargetClass', () => {
	it('produces a dsgo-prefixed class', () => {
		expect(makeTargetClass()).toMatch(/^dsgo-target-[0-9a-f]{6}$/);
	});

	it('does not collide on consecutive calls', () => {
		const seen = new Set(
			Array.from({ length: 50 }, () => makeTargetClass())
		);
		expect(seen.size).toBeGreaterThan(45);
	});
});

describe('deriveSelector', () => {
	it('prefers the HTML anchor', () => {
		const tag = jest.fn();
		expect(
			deriveSelector(
				{
					clientId: '1',
					attributes: { anchor: 'hero', className: 'x' },
				},
				tag
			)
		).toBe('#hero');
		expect(tag).not.toHaveBeenCalled();
	});

	it('falls back to an existing custom class', () => {
		const tag = jest.fn();
		expect(
			deriveSelector(
				{
					clientId: '1',
					attributes: { className: 'wp-block-group my-panel' },
				},
				tag
			)
		).toBe('.my-panel');
		expect(tag).not.toHaveBeenCalled();
	});

	it('tags the block when it has no usable identifier', () => {
		const tag = jest.fn();
		const selector = deriveSelector(
			{ clientId: 'abc', attributes: { className: 'wp-block-group' } },
			tag
		);

		expect(selector).toMatch(/^\.dsgo-target-/);
		expect(tag).toHaveBeenCalledTimes(1);
		const [clientId, attrs] = tag.mock.calls[0];
		expect(clientId).toBe('abc');
		// The generated class is appended, never replacing what was there.
		expect(attrs.className).toContain('wp-block-group');
		expect(attrs.className).toContain(selector.slice(1));
	});

	it('handles a block with no attributes at all', () => {
		const tag = jest.fn();
		expect(deriveSelector({ clientId: 'a' }, tag)).toMatch(
			/^\.dsgo-target-/
		);
	});

	it('returns empty for a missing block', () => {
		expect(deriveSelector(null, jest.fn())).toBe('');
	});
});
