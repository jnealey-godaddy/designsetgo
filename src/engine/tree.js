/**
 * Agent block tree contract.
 *
 * An agent submits a JSON block tree — `{ version: 1, blocks: [{ name,
 * attributes, innerBlocks }] }` — that the engine assembles into markup via
 * the real `save()`. This module owns the tree's structural shape, its path
 * format, and its problem codes. It is a plain ES module (no WordPress
 * imports) so it runs unmodified in Node (the CLI) and the browser (the
 * editor extension). Task 3 (assemble), Task 9 (lint walking), and Task 17
 * (the PHP mirror) all reuse the path format and problem codes defined here
 * verbatim.
 */

/** Current supported tree schema version. */
export const TREE_VERSION = 1;

/** A block name is a lowercase-alphanumeric-hyphen namespace/name pair. */
const BLOCK_NAME_RE = /^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/;

/**
 * Whether `value` is a plain object — not `null`, and not an array.
 *
 * @param {unknown} value Candidate value.
 * @return {boolean} True when `value` is a plain object.
 */
function isPlainObject(value) {
	return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Path for the block at `index` beneath `parentPath` ('' for the root list).
 *
 * @param {string} parentPath Path of the parent block, or '' for the root.
 * @param {number} index      Index of the block within its list.
 * @return {string} The child's path, e.g. `blocks[0]` or `blocks[0].innerBlocks[1]`.
 */
export function childPath(parentPath, index) {
	return parentPath
		? `${parentPath}.innerBlocks[${index}]`
		: `blocks[${index}]`;
}

/**
 * Structural check of a single block list, appending problems in place.
 *
 * @param {unknown[]} blocks     List of candidate block nodes.
 * @param {string}    parentPath Path of the parent block, or '' for the root.
 * @param {object[]}  problems   Accumulator; problems are pushed onto it.
 */
function checkBlocksShape(blocks, parentPath, problems) {
	blocks.forEach((node, index) => {
		const path = childPath(parentPath, index);

		if (!isPlainObject(node)) {
			problems.push({
				code: 'designsetgo_invalid_block_definition',
				path,
				message: 'Block definition must be an object.',
			});
			return;
		}

		if (typeof node.name !== 'string' || !BLOCK_NAME_RE.test(node.name)) {
			problems.push({
				code: 'designsetgo_invalid_block_definition',
				path,
				message: `Block name must match ${BLOCK_NAME_RE} (got ${JSON.stringify(
					node.name
				)}).`,
			});
		}

		if (
			Object.prototype.hasOwnProperty.call(node, 'attributes') &&
			!isPlainObject(node.attributes)
		) {
			problems.push({
				code: 'designsetgo_invalid_block_definition',
				path,
				message: '`attributes` must be a plain object when present.',
			});
		}

		const hasInnerBlocks = Object.prototype.hasOwnProperty.call(
			node,
			'innerBlocks'
		);
		if (hasInnerBlocks && !Array.isArray(node.innerBlocks)) {
			problems.push({
				code: 'designsetgo_invalid_block_definition',
				path,
				message: '`innerBlocks` must be an array when present.',
			});
		} else if (hasInnerBlocks) {
			checkBlocksShape(node.innerBlocks, path, problems);
		}
	});
}

/**
 * Structural check of a block tree. Never throws.
 *
 * @param {unknown} tree Candidate tree.
 * @return {{ code: string, path: string, message: string }[]} Problems; empty when well formed.
 */
export function checkTreeShape(tree) {
	const problems = [];

	if (!isPlainObject(tree) || !Array.isArray(tree.blocks)) {
		problems.push({
			code: 'designsetgo_invalid_tree',
			path: 'blocks',
			message: 'The tree must be an object with a `blocks` array.',
		});
		return problems;
	}

	if (tree.version !== TREE_VERSION) {
		problems.push({
			code: 'designsetgo_unsupported_tree_version',
			path: 'version',
			message: `Unsupported tree version ${JSON.stringify(
				tree.version
			)}; expected ${TREE_VERSION}.`,
		});
	}

	checkBlocksShape(tree.blocks, '', problems);

	return problems;
}

/**
 * Depth-first visit of a block list.
 *
 * @param {unknown[]}                                                                          blocks       List of block nodes.
 * @param {(node: object, path: string, parentNode: object|null, ancestors: object[]) => void} callback
 *                                                                                                          Called for every node in document order.
 * @param {string}                                                                             [parentPath] Path of the parent block, or '' for the root.
 * @param {object[]}                                                                           [ancestors]  Ancestor nodes, nearest last.
 */
export function walkTree(blocks, callback, parentPath = '', ancestors = []) {
	if (!Array.isArray(blocks)) {
		return;
	}

	const parentNode = ancestors.length
		? ancestors[ancestors.length - 1]
		: null;

	blocks.forEach((node, index) => {
		const path = childPath(parentPath, index);
		callback(node, path, parentNode, ancestors);

		if (node && Array.isArray(node.innerBlocks)) {
			walkTree(node.innerBlocks, callback, path, [...ancestors, node]);
		}
	});
}
