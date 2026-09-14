/**
 * Block tree contract tests: shape checking, path format, and DFS walking.
 */
import { TREE_VERSION, childPath, checkTreeShape, walkTree } from '../tree';

function codesOf(problems) {
	return problems.map((problem) => problem.code);
}

test('TREE_VERSION is 1', () => {
	expect(TREE_VERSION).toBe(1);
});

describe('childPath', () => {
	test('root list uses blocks[index]', () => {
		expect(childPath('', 0)).toBe('blocks[0]');
		expect(childPath('', 2)).toBe('blocks[2]');
	});

	test('nested list uses parentPath.innerBlocks[index]', () => {
		expect(childPath('blocks[1]', 0)).toBe('blocks[1].innerBlocks[0]');
	});
});

describe('checkTreeShape', () => {
	test('returns [] for a valid nested tree', () => {
		const tree = {
			version: 1,
			blocks: [
				{ name: 'core/paragraph', attributes: { content: 'Hi' } },
				{
					name: 'core/group',
					innerBlocks: [{ name: 'core/paragraph' }],
				},
			],
		};
		expect(checkTreeShape(tree)).toEqual([]);
	});

	test('never throws on wildly wrong input', () => {
		const inputs = [
			null,
			undefined,
			42,
			'a string',
			[],
			{},
			{ blocks: null },
			{ blocks: 'nope' },
			{ version: 1, blocks: [null, 42, 'x', [], { name: 1 }] },
		];
		for (const input of inputs) {
			expect(() => checkTreeShape(input)).not.toThrow();
		}
	});

	test('reports designsetgo_invalid_tree and stops when root is not an object', () => {
		for (const input of [null, undefined, 42, 'a string', []]) {
			const problems = checkTreeShape(input);
			expect(codesOf(problems)).toEqual(['designsetgo_invalid_tree']);
			expect(problems[0].path).toBe('blocks');
		}
	});

	test('reports designsetgo_invalid_tree and stops when blocks is not an array', () => {
		const problems = checkTreeShape({ version: 1, blocks: 'nope' });
		expect(codesOf(problems)).toEqual(['designsetgo_invalid_tree']);
		expect(problems[0].path).toBe('blocks');
	});

	test('does not check version when the root shape itself is invalid', () => {
		const problems = checkTreeShape({ blocks: null });
		expect(codesOf(problems)).toEqual(['designsetgo_invalid_tree']);
	});

	test('reports designsetgo_unsupported_tree_version when version is missing', () => {
		const problems = checkTreeShape({ blocks: [] });
		expect(codesOf(problems)).toEqual([
			'designsetgo_unsupported_tree_version',
		]);
		expect(problems[0].path).toBe('version');
	});

	test('reports designsetgo_unsupported_tree_version when version is not 1', () => {
		const problems = checkTreeShape({ version: 2, blocks: [] });
		expect(codesOf(problems)).toEqual([
			'designsetgo_unsupported_tree_version',
		]);
		expect(problems[0].path).toBe('version');
	});

	test('reports designsetgo_invalid_block_definition when a node is not an object', () => {
		const problems = checkTreeShape({
			version: 1,
			blocks: [null, 'nope', 42],
		});
		expect(codesOf(problems)).toEqual([
			'designsetgo_invalid_block_definition',
			'designsetgo_invalid_block_definition',
			'designsetgo_invalid_block_definition',
		]);
		expect(problems.map((p) => p.path)).toEqual([
			'blocks[0]',
			'blocks[1]',
			'blocks[2]',
		]);
	});

	test('reports designsetgo_invalid_block_definition for a malformed name', () => {
		const badNames = [
			undefined,
			'',
			'NoSlash',
			'core/',
			'/paragraph',
			'Core/Paragraph',
			'core/Paragraph',
			'0core/paragraph',
			'core/0paragraph',
		];
		for (const name of badNames) {
			const problems = checkTreeShape({
				version: 1,
				blocks: [{ name }],
			});
			expect(codesOf(problems)).toEqual([
				'designsetgo_invalid_block_definition',
			]);
			expect(problems[0].path).toBe('blocks[0]');
		}
	});

	test('accepts well-formed block names', () => {
		for (const name of [
			'core/paragraph',
			'designsetgo/query-filter',
			'a/b',
		]) {
			expect(checkTreeShape({ version: 1, blocks: [{ name }] })).toEqual(
				[]
			);
		}
	});

	test('reports designsetgo_invalid_block_definition when attributes is present and not a plain object', () => {
		for (const attributes of [null, 'nope', 42, []]) {
			const problems = checkTreeShape({
				version: 1,
				blocks: [{ name: 'core/paragraph', attributes }],
			});
			expect(codesOf(problems)).toEqual([
				'designsetgo_invalid_block_definition',
			]);
		}
	});

	test('reports designsetgo_invalid_block_definition when innerBlocks is present and not an array', () => {
		for (const innerBlocks of [null, 'nope', 42, {}]) {
			const problems = checkTreeShape({
				version: 1,
				blocks: [{ name: 'core/paragraph', innerBlocks }],
			});
			expect(codesOf(problems)).toEqual([
				'designsetgo_invalid_block_definition',
			]);
		}
	});

	test('paths use blocks[1].innerBlocks[0] format for nested problems', () => {
		const problems = checkTreeShape({
			version: 1,
			blocks: [
				{ name: 'core/paragraph' },
				{
					name: 'core/group',
					innerBlocks: [{ name: 'Not Valid' }],
				},
			],
		});
		expect(codesOf(problems)).toEqual([
			'designsetgo_invalid_block_definition',
		]);
		expect(problems[0].path).toBe('blocks[1].innerBlocks[0]');
	});

	test('collects every problem in the tree, not just the first', () => {
		const problems = checkTreeShape({
			version: 2,
			blocks: [
				{ name: 'Bad Name' },
				{ name: 'core/group', attributes: 5 },
			],
		});
		expect(codesOf(problems)).toEqual([
			'designsetgo_unsupported_tree_version',
			'designsetgo_invalid_block_definition',
			'designsetgo_invalid_block_definition',
		]);
	});
});

describe('walkTree', () => {
	function buildTree() {
		return [
			{ name: 'core/paragraph' },
			{
				name: 'core/group',
				innerBlocks: [
					{ name: 'core/heading' },
					{
						name: 'core/columns',
						innerBlocks: [{ name: 'core/column' }],
					},
				],
			},
		];
	}

	test('visits every node in document (depth-first) order', () => {
		const visited = [];
		walkTree(buildTree(), (node) => visited.push(node.name));
		expect(visited).toEqual([
			'core/paragraph',
			'core/group',
			'core/heading',
			'core/columns',
			'core/column',
		]);
	});

	test('passes the correct path for each node', () => {
		const paths = [];
		walkTree(buildTree(), (node, path) => paths.push(path));
		expect(paths).toEqual([
			'blocks[0]',
			'blocks[1]',
			'blocks[1].innerBlocks[0]',
			'blocks[1].innerBlocks[1]',
			'blocks[1].innerBlocks[1].innerBlocks[0]',
		]);
	});

	test('passes parentNode as null at the root and the immediate parent otherwise', () => {
		const parents = [];
		walkTree(buildTree(), (node, path, parentNode) =>
			parents.push(parentNode ? parentNode.name : null)
		);
		expect(parents).toEqual([
			null,
			null,
			'core/group',
			'core/group',
			'core/columns',
		]);
	});

	test('passes ancestors as an array of ancestor nodes, nearest last', () => {
		const ancestorNames = [];
		walkTree(buildTree(), (node, path, parentNode, ancestors) =>
			ancestorNames.push(ancestors.map((n) => n.name))
		);
		expect(ancestorNames).toEqual([
			[],
			[],
			['core/group'],
			['core/group'],
			['core/group', 'core/columns'],
		]);
	});

	test('does nothing when blocks is not an array', () => {
		const callback = jest.fn();
		walkTree(null, callback);
		walkTree('nope', callback);
		walkTree(undefined, callback);
		expect(callback).not.toHaveBeenCalled();
	});
});
