/**
 * The lint controller: walks a tree with `walkTree`, runs each rule's
 * `check`/`checkTree` against it, and collects findings.
 *
 * See `lint-order.test.js` for path-sorting behavior and
 * `lint-crash.test.js` for how a throwing rule is handled.
 */
import { lint } from '../index';
import { rules as defaultRules } from '../rules';

function tree(blocks) {
	return { version: 1, blocks };
}

describe('lint', () => {
	test('defaults to the rule list from rules/index.js (empty for now)', () => {
		expect(defaultRules).toEqual([]);
		expect(lint(tree([{ name: 'core/paragraph' }]))).toEqual([]);
	});

	test('runs a fake rule.check against every node with the right path/ancestors', () => {
		const seen = [];
		const rule = {
			id: 'test/fake',
			severity: 'warning',
			check(node, ctx) {
				seen.push({
					name: node.name,
					path: ctx.path,
					parentName: ctx.parent ? ctx.parent.name : null,
					ancestorNames: ctx.ancestors.map((a) => a.name),
				});
			},
		};

		const testTree = tree([
			{
				name: 'core/group',
				innerBlocks: [{ name: 'core/paragraph' }],
			},
		]);

		lint(testTree, {}, { rules: [rule] });

		expect(seen).toEqual([
			{
				name: 'core/group',
				path: 'blocks[0]',
				parentName: null,
				ancestorNames: [],
			},
			{
				name: 'core/paragraph',
				path: 'blocks[0].innerBlocks[0]',
				parentName: 'core/group',
				ancestorNames: ['core/group'],
			},
		]);
	});

	test('ctx.report() appends a finding with the rule id/severity/path', () => {
		const rule = {
			id: 'test/reporter',
			severity: 'error',
			check(node, ctx) {
				if (node.name === 'core/paragraph') {
					ctx.report('bad paragraph', 'fix it');
				}
			},
		};

		const findings = lint(
			tree([{ name: 'core/paragraph' }]),
			{},
			{ rules: [rule] }
		);

		expect(findings).toEqual([
			{
				rule: 'test/reporter',
				severity: 'error',
				path: 'blocks[0]',
				message: 'bad paragraph',
				suggestion: 'fix it',
			},
		]);
	});

	test('report() omits suggestion when not passed', () => {
		const rule = {
			id: 'test/no-suggestion',
			severity: 'warning',
			check(node, ctx) {
				ctx.report('just a message');
			},
		};

		const findings = lint(
			tree([{ name: 'core/paragraph' }]),
			{},
			{ rules: [rule] }
		);

		expect(findings[0]).not.toHaveProperty('suggestion');
	});

	test('runs checkTree once for the whole tree, with an explicit path in report()', () => {
		const calls = [];
		const rule = {
			id: 'test/page-level',
			severity: 'warning',
			checkTree(t, ctx) {
				calls.push(t.blocks.length);
				ctx.report('blocks[0]', 'page level finding');
			},
		};

		const findings = lint(
			tree([{ name: 'core/paragraph' }]),
			{},
			{ rules: [rule] }
		);

		expect(calls).toEqual([1]);
		expect(findings).toEqual([
			{
				rule: 'test/page-level',
				severity: 'warning',
				path: 'blocks[0]',
				message: 'page level finding',
			},
		]);
	});

	test('does not throw on an empty tree or missing blocks array', () => {
		expect(() => lint({ version: 1, blocks: [] })).not.toThrow();
		expect(() => lint({})).not.toThrow();
		expect(() => lint(null)).not.toThrow();
	});

	test('exposes ctx.design bound to the provided design context', () => {
		let capturedDesign;
		const rule = {
			id: 'test/design',
			severity: 'warning',
			check(node, ctx) {
				capturedDesign = ctx.design;
			},
		};

		const designContext = {
			settings: {
				color: {
					palette: { theme: [{ slug: 'primary', color: '#123456' }] },
				},
				spacing: {
					spacingSizes: { theme: [{ slug: '50', size: '1rem' }] },
				},
				typography: {
					fontSizes: { theme: [{ slug: 'small', size: '0.8rem' }] },
				},
			},
		};

		lint(tree([{ name: 'core/paragraph' }]), designContext, {
			rules: [rule],
		});

		expect(capturedDesign.palette).toBeInstanceOf(Map);
		expect(capturedDesign.palette.get('primary')).toBe('#123456');
		expect(capturedDesign.spacingSlugs).toBeInstanceOf(Set);
		expect(capturedDesign.spacingSlugs.has('50')).toBe(true);
		expect(capturedDesign.fontSizeSlugs).toBeInstanceOf(Set);
		expect(capturedDesign.fontSizeSlugs.has('small')).toBe(true);
		expect(typeof capturedDesign.presetColor).toBe('function');
		expect(capturedDesign.presetColor('primary')).toBe('#123456');
	});

	test('an unknown/empty design context produces no crash and no context-dependent findings', () => {
		const rule = {
			id: 'test/context-dependent',
			severity: 'warning',
			check(node, ctx) {
				if (ctx.design.presetColor(node.attributes?.textColor)) {
					ctx.report('should not happen');
				}
			},
		};

		const findings = lint(
			tree([
				{
					name: 'core/paragraph',
					attributes: { textColor: 'primary' },
				},
			]),
			undefined,
			{ rules: [rule] }
		);

		expect(findings).toEqual([]);
	});
});
