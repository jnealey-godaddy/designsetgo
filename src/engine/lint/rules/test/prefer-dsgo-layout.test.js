/**
 * `prefer-dsgo-layout` (warning): steers agents toward DesignSetGo's own
 * layout blocks instead of core equivalents — core/columns always,
 * core/group with a flex or grid layout, and any top-level core/group.
 */
import { lint } from '../../index';
import rule from '../prefer-dsgo-layout';

function tree(blocks) {
	return { version: 1, blocks };
}

function run(blocks) {
	return lint(tree(blocks), {}, { rules: [rule] });
}

describe('prefer-dsgo-layout', () => {
	test('has the documented id/severity', () => {
		expect(rule.id).toBe('prefer-dsgo-layout');
		expect(rule.severity).toBe('warning');
	});

	test('flags core/columns', () => {
		const findings = run([{ name: 'core/columns', attributes: {} }]);
		expect(findings).toHaveLength(1);
		expect(findings[0]).toMatchObject({
			rule: 'prefer-dsgo-layout',
			severity: 'warning',
			path: 'blocks[0]',
		});
	});

	test('flags core/group with layout.type "flex"', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: { layout: { type: 'flex' } },
				innerBlocks: [{ name: 'core/paragraph', attributes: {} }],
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0].suggestion).toMatch(/designsetgo\/row/);
	});

	test('flags core/group with layout.type "grid"', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: { layout: { type: 'grid' } },
				innerBlocks: [{ name: 'core/paragraph', attributes: {} }],
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0].suggestion).toMatch(/designsetgo\/grid/);
	});

	test('flags a top-level core/group with a default layout', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: {},
				innerBlocks: [{ name: 'core/paragraph', attributes: {} }],
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0].suggestion).toMatch(/designsetgo\/section/);
	});

	test('does not flag a nested core/group with a default (non-flex/grid) layout', () => {
		const findings = run([
			{
				name: 'designsetgo/section',
				attributes: {},
				innerBlocks: [
					{
						name: 'core/group',
						attributes: {},
						innerBlocks: [
							{ name: 'core/paragraph', attributes: {} },
						],
					},
				],
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag designsetgo/row or designsetgo/grid', () => {
		const findings = run([
			{ name: 'designsetgo/row', attributes: {} },
			{ name: 'designsetgo/grid', attributes: {} },
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag an unrelated block', () => {
		const findings = run([{ name: 'core/paragraph', attributes: {} }]);
		expect(findings).toEqual([]);
	});

	test('does not crash on a core/group with no attributes at top level', () => {
		expect(() => run([{ name: 'core/group' }])).not.toThrow();
		expect(run([{ name: 'core/group' }])).toHaveLength(1);
	});
});
