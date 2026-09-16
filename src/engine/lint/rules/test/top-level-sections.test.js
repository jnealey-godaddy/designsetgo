/**
 * `top-level-sections` (warning, page-level): flags the anti-pattern where
 * a whole page is wrapped in a single top-level container block that
 * itself holds 2+ container children, instead of each section being its
 * own top-level block (see the project convention this mirrors).
 */
import { lint } from '../../index';
import rule from '../top-level-sections';

function tree(blocks) {
	return { version: 1, blocks };
}

function run(blocks) {
	return lint(tree(blocks), {}, { rules: [rule] });
}

function section(name = 'designsetgo/section') {
	return {
		name,
		attributes: {},
		innerBlocks: [{ name: 'core/paragraph', attributes: {} }],
	};
}

describe('top-level-sections', () => {
	test('has the documented id/severity', () => {
		expect(rule.id).toBe('top-level-sections');
		expect(rule.severity).toBe('warning');
	});

	test('flags a single top-level core/group wrapping 2+ container children', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: {},
				innerBlocks: [section(), section()],
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0]).toMatchObject({
			rule: 'top-level-sections',
			severity: 'warning',
			path: 'blocks[0]',
		});
	});

	test('flags a single top-level designsetgo/section wrapping 2+ container children', () => {
		const findings = run([
			{
				name: 'designsetgo/section',
				attributes: {},
				innerBlocks: [section(), section('designsetgo/section')],
			},
		]);
		expect(findings).toHaveLength(1);
	});

	test('does not flag when sections are already top-level', () => {
		const findings = run([section(), section()]);
		expect(findings).toEqual([]);
	});

	test('does not flag a single top-level container with fewer than 2 container children', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: {},
				innerBlocks: [section()],
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag a single top-level container whose children are not containers', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: {},
				innerBlocks: [
					{ name: 'core/paragraph', attributes: {} },
					{ name: 'core/paragraph', attributes: {} },
				],
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag when there is more than one top-level block', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: {},
				innerBlocks: [section(), section()],
			},
			{ name: 'core/paragraph', attributes: {} },
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag a single top-level non-container block', () => {
		const findings = run([{ name: 'core/paragraph', attributes: {} }]);
		expect(findings).toEqual([]);
	});

	test('does not crash on an empty tree', () => {
		expect(() => run([])).not.toThrow();
		expect(run([])).toEqual([]);
	});
});
