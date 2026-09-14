/**
 * `preset-values` (warning): flags raw color/spacing/font-size values in
 * `style.*` where a theme.json preset collection exists that a slug could
 * have been used from instead. Preset references (`var:preset|…`,
 * `var(--wp--preset--…)`) are never flagged.
 */
import { lint } from '../../index';
import rule from '../preset-values';

function tree(blocks) {
	return { version: 1, blocks };
}

const design = {
	settings: {
		color: {
			palette: {
				theme: [
					{ slug: 'primary', color: '#0000ff', name: 'Primary' },
					{ slug: 'white', color: '#ffffff', name: 'White' },
				],
			},
		},
		spacing: {
			spacingSizes: {
				theme: [
					{ slug: '30', size: '1rem' },
					{ slug: '50', size: '2rem' },
				],
			},
		},
		typography: {
			fontSizes: {
				theme: [{ slug: 'medium', size: '1.2rem' }],
			},
		},
	},
};

function run(blocks, designContext = design) {
	return lint(tree(blocks), designContext, { rules: [rule] });
}

describe('preset-values', () => {
	test('has the documented id/severity', () => {
		expect(rule.id).toBe('preset-values');
		expect(rule.severity).toBe('warning');
	});

	test('flags a raw style.color.text value and suggests the nearest palette slug', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: { style: { color: { text: '#0000fe' } } },
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0]).toMatchObject({
			rule: 'preset-values',
			severity: 'warning',
			path: 'blocks[0]',
		});
		expect(findings[0].suggestion).toMatch(/primary/);
	});

	test('flags a raw style.color.background value', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: { style: { color: { background: '#fefefe' } } },
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0].suggestion).toMatch(/white/);
	});

	test('does not flag a preset color reference (var:preset|color|slug)', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: {
					style: { color: { text: 'var:preset|color|primary' } },
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag a preset color reference (var(--wp--preset--color--slug))', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: {
					style: {
						color: {
							background: 'var(--wp--preset--color--white)',
						},
					},
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag raw colors when the palette is empty', () => {
		const findings = run(
			[
				{
					name: 'core/paragraph',
					attributes: { style: { color: { text: '#123456' } } },
				},
			],
			{}
		);
		expect(findings).toEqual([]);
	});

	test('flags a raw px spacing value in style.spacing.padding', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: {
					style: { spacing: { padding: { top: '16px' } } },
				},
			},
		]);
		expect(findings).toHaveLength(1);
	});

	test('flags a raw rem spacing value in style.spacing.margin', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: { style: { spacing: { margin: '1.5rem' } } },
			},
		]);
		expect(findings).toHaveLength(1);
	});

	test('flags a raw em spacing value in style.spacing.blockGap', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: { style: { spacing: { blockGap: '2em' } } },
			},
		]);
		expect(findings).toHaveLength(1);
	});

	test('does not flag spacing preset references', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: {
					style: { spacing: { padding: 'var:preset|spacing|30' } },
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag raw spacing when no spacing presets exist', () => {
		const findings = run(
			[
				{
					name: 'core/group',
					attributes: { style: { spacing: { padding: '16px' } } },
				},
			],
			{}
		);
		expect(findings).toEqual([]);
	});

	test('flags a raw font size value', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: { style: { typography: { fontSize: '18px' } } },
			},
		]);
		expect(findings).toHaveLength(1);
	});

	test('does not flag a font size preset reference', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: {
					style: {
						typography: { fontSize: 'var:preset|font-size|medium' },
					},
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag raw font size when no font-size presets exist', () => {
		const findings = run(
			[
				{
					name: 'core/paragraph',
					attributes: { style: { typography: { fontSize: '18px' } } },
				},
			],
			{}
		);
		expect(findings).toEqual([]);
	});

	test('does not flag a block with no style attribute', () => {
		const findings = run([{ name: 'core/paragraph', attributes: {} }]);
		expect(findings).toEqual([]);
	});

	test('does not crash and reports nothing with no design context', () => {
		// Call lint() directly: run()'s default parameter would otherwise
		// shadow an explicit `undefined` argument with the fixture design.
		const findings = lint(
			tree([
				{
					name: 'core/paragraph',
					attributes: {
						style: {
							color: { text: '#123456' },
							spacing: { padding: '16px' },
							typography: { fontSize: '18px' },
						},
					},
				},
			]),
			undefined,
			{ rules: [rule] }
		);
		expect(findings).toEqual([]);
	});
});
