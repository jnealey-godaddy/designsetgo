/**
 * `contrast` (error): resolves text/background colors (preset slug or raw
 * value), inheriting whichever side a node doesn't set itself from the
 * nearest ancestor that does, and flags a WCAG ratio below 4.5.
 */
import { lint } from '../../index';
import rule from '../contrast';

function tree(blocks) {
	return { version: 1, blocks };
}

const design = {
	settings: {
		color: {
			palette: {
				theme: [
					{ slug: 'white', color: '#ffffff', name: 'White' },
					{ slug: 'black', color: '#000000', name: 'Black' },
					{
						slug: 'light-gray',
						color: '#f0f0f0',
						name: 'Light Gray',
					},
				],
			},
		},
	},
};

function run(blocks, designContext = design) {
	return lint(tree(blocks), designContext, { rules: [rule] });
}

describe('contrast', () => {
	test('has the documented id/severity', () => {
		expect(rule.id).toBe('contrast');
		expect(rule.severity).toBe('error');
	});

	test('flags low contrast between preset textColor/backgroundColor slugs', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: {
					textColor: 'white',
					backgroundColor: 'light-gray',
				},
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0]).toMatchObject({
			rule: 'contrast',
			severity: 'error',
			path: 'blocks[0]',
		});
		expect(findings[0].message).toMatch(/4\.5/);
	});

	test('does not flag good contrast between preset textColor/backgroundColor slugs', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: { textColor: 'black', backgroundColor: 'white' },
			},
		]);
		expect(findings).toEqual([]);
	});

	test('flags low contrast via style.color.text/style.color.background raw values', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: {
					style: {
						color: { text: '#eeeeee', background: '#ffffff' },
					},
				},
			},
		]);
		expect(findings).toHaveLength(1);
	});

	test('inherits background from the nearest ancestor when the node sets only text', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: { backgroundColor: 'white' },
				innerBlocks: [
					{
						name: 'core/paragraph',
						attributes: { textColor: 'light-gray' },
					},
				],
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0].path).toBe('blocks[0].innerBlocks[0]');
	});

	test('inherits text from the nearest ancestor when the node sets only background', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: { textColor: 'light-gray' },
				innerBlocks: [
					{
						name: 'core/paragraph',
						attributes: { backgroundColor: 'white' },
					},
				],
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0].path).toBe('blocks[0].innerBlocks[0]');
	});

	test('does not check a node that sets neither side itself', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: {
					textColor: 'white',
					backgroundColor: 'light-gray',
				},
				innerBlocks: [{ name: 'core/paragraph', attributes: {} }],
			},
		]);
		// Only the ancestor itself is checked/flagged, not the plain child.
		expect(findings).toHaveLength(1);
		expect(findings[0].path).toBe('blocks[0]');
	});

	test('skips when text color is unresolvable (unknown slug)', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: {
					textColor: 'not-a-real-slug',
					backgroundColor: 'white',
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('skips when background color is unresolvable (raw value that fails to parse)', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: {
					style: {
						color: { text: '#eeeeee', background: 'not-a-color' },
					},
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('skips when no ancestor supplies the missing side', () => {
		const findings = run([
			{ name: 'core/paragraph', attributes: { textColor: 'light-gray' } },
		]);
		expect(findings).toEqual([]);
	});

	test('skips a node with a gradient attribute even if colors are set', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: {
					textColor: 'white',
					backgroundColor: 'light-gray',
					gradient: 'some-gradient',
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('skips a node with style.color.gradient', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: {
					textColor: 'white',
					backgroundColor: 'light-gray',
					style: {
						color: { gradient: 'linear-gradient(#fff,#000)' },
					},
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('skips a node with style.background.backgroundImage', () => {
		const findings = run([
			{
				name: 'core/paragraph',
				attributes: {
					textColor: 'white',
					backgroundColor: 'light-gray',
					style: {
						background: { backgroundImage: { url: 'x.jpg' } },
					},
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('skips a node inheriting background from an ancestor with a background image', () => {
		const findings = run([
			{
				name: 'core/group',
				attributes: {
					backgroundColor: 'white',
					style: {
						background: { backgroundImage: { url: 'x.jpg' } },
					},
				},
				innerBlocks: [
					{
						name: 'core/paragraph',
						attributes: { textColor: 'light-gray' },
					},
				],
			},
		]);
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
						textColor: 'white',
						backgroundColor: 'light-gray',
					},
				},
			]),
			undefined,
			{ rules: [rule] }
		);
		expect(findings).toEqual([]);
	});
});
