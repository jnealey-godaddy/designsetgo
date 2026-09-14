/**
 * `lint()` returns findings sorted by path in *document* (walk) order, not
 * lexicographic string order — `blocks[10]` must come after `blocks[2]`.
 */
import { lint } from '../index';

function tree(blocks) {
	return { version: 1, blocks };
}

describe('lint findings ordering', () => {
	test('sorts findings by document (walk) order, not lexicographically', () => {
		const blocks = [];
		for (let i = 0; i < 12; i++) {
			blocks.push({ name: 'core/paragraph' });
		}

		const rule = {
			id: 'test/all',
			severity: 'warning',
			check(node, ctx) {
				ctx.report(`at ${ctx.path}`);
			},
		};

		const findings = lint(tree(blocks), {}, { rules: [rule] });
		const paths = findings.map((f) => f.path);

		expect(paths).toEqual([
			'blocks[0]',
			'blocks[1]',
			'blocks[2]',
			'blocks[3]',
			'blocks[4]',
			'blocks[5]',
			'blocks[6]',
			'blocks[7]',
			'blocks[8]',
			'blocks[9]',
			'blocks[10]',
			'blocks[11]',
		]);
	});

	test('sorts a parent before its children, and siblings by index', () => {
		const testTree = tree([
			{
				name: 'core/group',
				innerBlocks: [
					{ name: 'core/paragraph' },
					{ name: 'core/paragraph' },
				],
			},
			{ name: 'core/paragraph' },
		]);

		const rule = {
			id: 'test/all',
			severity: 'warning',
			check(node, ctx) {
				ctx.report('x');
			},
		};

		const findings = lint(testTree, {}, { rules: [rule] });
		expect(findings.map((f) => f.path)).toEqual([
			'blocks[0]',
			'blocks[0].innerBlocks[0]',
			'blocks[0].innerBlocks[1]',
			'blocks[1]',
		]);
	});

	test('a checkTree finding (empty path) sorts before every node finding', () => {
		const rule = {
			id: 'test/mixed',
			severity: 'warning',
			checkTree(t, ctx) {
				ctx.report('', 'page level');
			},
			check(node, ctx) {
				ctx.report('node level');
			},
		};

		const findings = lint(
			tree([{ name: 'core/paragraph' }]),
			{},
			{ rules: [rule] }
		);

		expect(findings.map((f) => f.message)).toEqual([
			'page level',
			'node level',
		]);
	});
});
