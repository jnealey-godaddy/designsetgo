/**
 * `no-custom-html` (error): flags `core/html` blocks, and any block whose
 * string attributes contain raw `<svg` markup — both bypass the block
 * editor's design system and accessibility/sanitization guarantees.
 */
import { lint } from '../../index';
import rule from '../no-custom-html';

function tree(blocks) {
	return { version: 1, blocks };
}

function run(blocks) {
	return lint(tree(blocks), {}, { rules: [rule] });
}

describe('no-custom-html', () => {
	test('has the documented id/severity', () => {
		expect(rule.id).toBe('no-custom-html');
		expect(rule.severity).toBe('error');
	});

	test('flags core/html', () => {
		const findings = run([
			{ name: 'core/html', attributes: { content: '<div>hi</div>' } },
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0]).toMatchObject({
			rule: 'no-custom-html',
			severity: 'error',
			path: 'blocks[0]',
		});
		expect(findings[0].suggestion).toEqual(expect.any(String));
	});

	test('does not flag a block that is not core/html and has no svg', () => {
		const findings = run([
			{ name: 'core/paragraph', attributes: { content: 'Hello' } },
		]);
		expect(findings).toEqual([]);
	});

	test('flags a block with an inline <svg> in a string attribute', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: { content: '<svg><path d="M0 0"/></svg>' },
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0].path).toBe('blocks[0]');
	});

	test('detects <svg> nested inside a non-string attribute (e.g. style object)', () => {
		const findings = run([
			{
				name: 'designsetgo/icon',
				attributes: {
					nested: { deep: ['<svg xmlns="x"></svg>'] },
				},
			},
		]);
		expect(findings).toHaveLength(1);
	});

	test('does not flag a block whose attributes contain no svg markup', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: { style: { color: { text: '#000000' } } },
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not crash on a block with no attributes', () => {
		expect(() => run([{ name: 'core/paragraph' }])).not.toThrow();
		expect(run([{ name: 'core/paragraph' }])).toEqual([]);
	});
});
