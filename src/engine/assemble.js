/**
 * Assembles an agent-submitted JSON block tree into real markup via the
 * block editor's own `createBlock`/`serialize`, then validates the result.
 * This is the engine's single write path: every surface (CLI, editor,
 * remote finish) that turns agent intent into stored block markup goes
 * through this function.
 */
import { checkTreeShape, walkTree } from './tree';
import { withQuietConsole } from './quiet';
import { validate } from './validate';
import { sha256Hex } from './hash';

/**
 * Resolves the node a shape-check `path` points at, so a shape problem can
 * report the offending block's name. Shape problems can also point at
 * non-block paths (`'blocks'`, `'version'`), which resolve to `null`.
 *
 * @param {Object} tree Candidate tree (already known to be a plain object).
 * @param {string} path Path in `childPath()` format, e.g. `blocks[0].innerBlocks[1]`.
 * @return {Object|null} The node at `path`, or `null` when it can't be resolved.
 */
function nodeAtPath(tree, path) {
	const segments = path.match(/[a-zA-Z]+\[\d+\]/g);
	if (!segments) {
		return null;
	}

	let node = tree;
	for (const segment of segments) {
		const [, key, indexStr] = segment.match(/^([a-zA-Z]+)\[(\d+)\]$/);
		const list = node && node[key];
		if (!Array.isArray(list)) {
			return null;
		}
		node = list[Number(indexStr)];
	}

	return node || null;
}

/**
 * @param {Object} problem Shape problem from `checkTreeShape()`.
 * @param {Object} tree    The tree the problem was found in.
 * @return {{ path: string, block: string, reason: string, code: string }} Invalid entry.
 */
function toInvalidEntry(problem, tree) {
	const node = nodeAtPath(tree, problem.path);
	return {
		path: problem.path,
		block: node && typeof node.name === 'string' ? node.name : '',
		reason: problem.message,
		code: problem.code,
	};
}

/**
 * Recursively builds a real block object from a tree node.
 *
 * @param {Object} blocksApi Object exposing `createBlock`.
 * @param {Object} node      Tree node: `{ name, attributes?, innerBlocks? }`.
 * @return {Object} A block object as returned by `createBlock()`.
 */
function buildBlock(blocksApi, node) {
	const children = Array.isArray(node.innerBlocks)
		? node.innerBlocks.map((child) => buildBlock(blocksApi, child))
		: [];

	return blocksApi.createBlock(node.name, node.attributes ?? {}, children);
}

/**
 * @param {Object} blocksApi Object exposing `createBlock, serialize, parse, getBlockType`.
 * @param {Object} tree      Candidate agent block tree: `{ version, blocks }`.
 * @return {{
 *   status: 'valid'|'invalid',
 *   markup: string,
 *   invalid: object[],
 *   treeHash: string,
 * }} Assembly result. `markup` is `''` whenever `status` is `'invalid'` from
 *    a shape or unknown-block problem (assembly never ran); it can still be
 *    non-empty and invalid when the built markup itself fails validation.
 */
export function assemble(blocksApi, tree) {
	const treeHash = sha256Hex(JSON.stringify(tree));

	const shapeProblems = checkTreeShape(tree);
	if (shapeProblems.length) {
		return {
			status: 'invalid',
			markup: '',
			invalid: shapeProblems.map((problem) =>
				toInvalidEntry(problem, tree)
			),
			treeHash,
		};
	}

	const unknownBlockProblems = [];
	walkTree(tree.blocks, (node, path) => {
		if (!blocksApi.getBlockType(node.name)) {
			unknownBlockProblems.push({
				path,
				block: node.name,
				reason: `Block "${node.name}" is not registered.`,
				code: 'designsetgo_unknown_block',
			});
		}
	});

	if (unknownBlockProblems.length) {
		return {
			status: 'invalid',
			markup: '',
			invalid: unknownBlockProblems,
			treeHash,
		};
	}

	const markup = withQuietConsole(() => {
		const blocks = tree.blocks.map((node) => buildBlock(blocksApi, node));
		return blocksApi.serialize(blocks);
	});

	const { status, invalid } = validate(blocksApi, markup);

	return { status, markup, invalid, treeHash };
}
