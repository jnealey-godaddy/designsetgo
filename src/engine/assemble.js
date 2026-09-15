/**
 * Assembles an agent-submitted JSON block tree into real markup via the
 * block editor's own `createBlock`/`serialize`, then validates the result.
 * This is the engine's single write path: every surface (CLI, editor,
 * remote finish) that turns agent intent into stored block markup goes
 * through this function.
 */
import { checkTreeShape, walkTree } from './tree';
import { withQuietConsole } from './quiet';
import { findInvalidBlocks } from './validate';
import { findUnknownAttributes } from './attributes';
import { sha256Hex } from './hash';
import {
	findDroppedInnerBlocks,
	locateThrowingBlock,
	assembleErrorEntry,
} from './structure';

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
 * Exported (not just internal to `assemble()`) so the CLI's `lint` command
 * can report `checkTreeShape()` problems in the same `{ path, block, reason,
 * code }` shape `assemble()`'s own invalid output uses, without duplicating
 * this resolution logic — see `src/engine/node/run.js`.
 *
 * @param {Object} problem Shape problem from `checkTreeShape()`.
 * @param {Object} tree    The tree the problem was found in.
 * @return {{ path: string, block: string, reason: string, code: string }} Invalid entry.
 */
export function toInvalidEntry(problem, tree) {
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

	return blocksApi.createBlock(
		node.name,
		withoutEmptyObjects(node.attributes ?? {}),
		children
	);
}

/**
 * Drops attributes whose value is an empty plain object (`style: {}`), so
 * the block's own default applies instead. Agents send these routinely, and
 * a PHP round trip can't tell `{}` from "unset"; handed to `createBlock()`
 * as-is, an explicit `{}` can serialize markup that doesn't re-parse
 * identically. Top-level attributes only.
 *
 * @param {Object} attributes Node attributes.
 * @return {Object} A copy without empty-object values.
 */
function withoutEmptyObjects(attributes) {
	return Object.fromEntries(
		Object.entries(attributes).filter(
			([, value]) =>
				!(
					value !== null &&
					typeof value === 'object' &&
					!Array.isArray(value) &&
					Object.keys(value).length === 0
				)
		)
	);
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
 *    a shape or unknown-block problem (assembly never ran) or an assemble
 *    error (`designsetgo_assemble_error`: a `save()` threw); it can still be
 *    non-empty and invalid when the built markup itself fails validation or
 *    drops submitted children (`designsetgo_dropped_inner_blocks`). Never
 *    throws.
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

	// Unknown blocks and unknown attributes are both "an agent typed
	// something that isn't real" problems, so they're collected and reported
	// together, before any block is built — same as unknown blocks alone
	// used to behave. findUnknownAttributes() itself skips nodes whose block
	// name isn't registered (nothing above reports those independently).
	const unknownProblems = [
		...unknownBlockProblems,
		...findUnknownAttributes(blocksApi, tree),
	];

	if (unknownProblems.length) {
		return {
			status: 'invalid',
			markup: '',
			invalid: unknownProblems,
			treeHash,
		};
	}

	// A block's save() (or createBlock/parse) can throw. Never let that
	// escape: every surface — CLI, finish plugin, panel — expects a report.
	let blocks = [];
	let markup;
	let parsedBlocks;
	try {
		withQuietConsole(() => {
			blocks = tree.blocks.map((node) => buildBlock(blocksApi, node));
			markup = blocksApi.serialize(blocks);
			parsedBlocks = blocksApi.parse(markup);
		});
	} catch (error) {
		const location = withQuietConsole(() => {
			try {
				// serialize() swallows a throwing save(); getSaveContent()
				// never does, so it pinpoints the block.
				return locateThrowingBlock(
					(block) =>
						blocksApi.getSaveContent
							? blocksApi.getSaveContent(
									block.name,
									block.attributes,
									block.innerBlocks
								)
							: blocksApi.serialize(block),
					blocks
				);
			} catch (locateError) {
				return null;
			}
		});

		return {
			status: 'invalid',
			markup: '',
			invalid: [assembleErrorEntry(error, location)],
			treeHash,
		};
	}

	const invalid = [
		...findInvalidBlocks(parsedBlocks),
		...findDroppedInnerBlocks(tree.blocks, parsedBlocks),
	];

	return {
		status: invalid.length ? 'invalid' : 'valid',
		markup,
		invalid,
		treeHash,
	};
}
