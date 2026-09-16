/**
 * `mobile-layout` (warning): flags designsetgo/grid and designsetgo/row
 * configurations that will not adapt on small screens. Attribute names and
 * defaults are read from `src/blocks/grid/block.json` and
 * `src/blocks/row/block.json` — see the rule module header for exact
 * citations. `core/columns` is out of scope here (handled by
 * prefer-dsgo-layout).
 */
import { lint } from '../../index';
import rule from '../mobile-layout';

function tree(blocks) {
	return { version: 1, blocks };
}

function run(blocks) {
	return lint(tree(blocks), {}, { rules: [rule] });
}

function paragraphs(count) {
	return Array.from({ length: count }, () => ({
		name: 'core/paragraph',
		attributes: {},
	}));
}

describe('mobile-layout: designsetgo/grid', () => {
	test('has the documented id/severity', () => {
		expect(rule.id).toBe('mobile-layout');
		expect(rule.severity).toBe('warning');
	});

	test('does not flag grid with all-default columns (desktop 3, mobile 1)', () => {
		const findings = run([{ name: 'designsetgo/grid', attributes: {} }]);
		expect(findings).toEqual([]);
	});

	test('flags grid with an explicit mobileColumns >= 3 alongside default desktopColumns', () => {
		const findings = run([
			{ name: 'designsetgo/grid', attributes: { mobileColumns: 3 } },
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0]).toMatchObject({
			rule: 'mobile-layout',
			severity: 'warning',
			path: 'blocks[0]',
		});
	});

	test('does not flag grid with desktopColumns < 3 even if mobileColumns >= 3', () => {
		const findings = run([
			{
				name: 'designsetgo/grid',
				attributes: { desktopColumns: 2, mobileColumns: 3 },
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag grid with an explicit mobileColumns < 3', () => {
		const findings = run([
			{
				name: 'designsetgo/grid',
				attributes: { desktopColumns: 4, mobileColumns: 2 },
			},
		]);
		expect(findings).toEqual([]);
	});
});

describe('mobile-layout: designsetgo/row', () => {
	test('flags a row with 3+ children, mobileStack false, and nowrap (all defaults)', () => {
		const findings = run([
			{
				name: 'designsetgo/row',
				attributes: {},
				innerBlocks: paragraphs(3),
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0].path).toBe('blocks[0]');
	});

	test('does not flag a row with fewer than 3 children', () => {
		const findings = run([
			{
				name: 'designsetgo/row',
				attributes: {},
				innerBlocks: paragraphs(2),
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag a row with mobileStack true', () => {
		const findings = run([
			{
				name: 'designsetgo/row',
				attributes: { mobileStack: true },
				innerBlocks: paragraphs(3),
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag a row whose layout.flexWrap is "wrap"', () => {
		const findings = run([
			{
				name: 'designsetgo/row',
				attributes: { layout: { type: 'flex', flexWrap: 'wrap' } },
				innerBlocks: paragraphs(3),
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not crash on a block with no attributes/innerBlocks', () => {
		expect(() => run([{ name: 'designsetgo/row' }])).not.toThrow();
		expect(() => run([{ name: 'designsetgo/grid' }])).not.toThrow();
	});

	test('does not flag core/columns (handled by prefer-dsgo-layout)', () => {
		const findings = run([
			{
				name: 'core/columns',
				attributes: {},
				innerBlocks: paragraphs(3),
			},
		]);
		expect(findings).toEqual([]);
	});
});
