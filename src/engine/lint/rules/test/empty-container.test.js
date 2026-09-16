/**
 * `empty-container` (warning): flags container blocks with no innerBlocks —
 * `designsetgo/section|row|grid|card` and `core/group|columns|column`.
 */
import { lint } from '../../index';
import rule from '../empty-container';

function tree(blocks) {
	return { version: 1, blocks };
}

function run(blocks) {
	return lint(tree(blocks), {}, { rules: [rule] });
}

describe('empty-container', () => {
	test('has the documented id/severity', () => {
		expect(rule.id).toBe('empty-container');
		expect(rule.severity).toBe('warning');
	});

	test.each([
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
		'designsetgo/card',
		'core/group',
		'core/columns',
		'core/column',
	])('flags %s with no innerBlocks key at all', (name) => {
		const findings = run([{ name, attributes: {} }]);
		expect(findings).toHaveLength(1);
		expect(findings[0]).toMatchObject({
			rule: 'empty-container',
			severity: 'warning',
			path: 'blocks[0]',
		});
	});

	test.each([
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
		'designsetgo/card',
		'core/group',
		'core/columns',
		'core/column',
	])('flags %s with an empty innerBlocks array', (name) => {
		const findings = run([{ name, attributes: {}, innerBlocks: [] }]);
		expect(findings).toHaveLength(1);
	});

	test('does not flag a container with children', () => {
		const findings = run([
			{
				name: 'designsetgo/section',
				attributes: {},
				innerBlocks: [{ name: 'core/paragraph', attributes: {} }],
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag a non-container block with no innerBlocks', () => {
		const findings = run([{ name: 'core/paragraph', attributes: {} }]);
		expect(findings).toEqual([]);
	});

	test('does not crash on a block with no attributes', () => {
		expect(() => run([{ name: 'designsetgo/section' }])).not.toThrow();
	});
});
