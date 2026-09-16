/**
 * Structural checks `assemble()` runs around the real `serialize()`/`parse()`
 * round trip: children a block's `save()` never renders, and locating the
 * block whose `save()` threw.
 */
import { childPath } from './tree';

/** Cap on an assemble-error `reason`, matching `validate()`'s cap. */
const MAX_REASON_LENGTH = 500;

/**
 * Compares the submitted tree with the blocks parsed back from its markup.
 * `createBlock()` happily accepts children for any block, but a block whose
 * `save()` never renders `<InnerBlocks.Content />` (e.g. `core/paragraph`)
 * drops them from the markup without complaint. Each node whose parsed
 * child count differs from what was submitted is reported at its own path;
 * its subtree is not compared further.
 *
 * A parsed `core/missing` block is left to `validate()`, which already
 * reports it.
 *
 * @param {Object[]} nodes        Submitted tree nodes.
 * @param {Object[]} parsedBlocks Blocks parsed back from the assembled markup.
 * @param {string}   [parentPath] Path of the parent, or '' for the root.
 * @return {{ path: string, block: string, reason: string, code: string }[]} Mismatches.
 */
export function findDroppedInnerBlocks(nodes, parsedBlocks, parentPath = '') {
	const problems = [];

	if (!parentPath && nodes.length !== parsedBlocks.length) {
		return [
			{
				path: 'blocks',
				block: '',
				reason: `Submitted ${nodes.length} top-level blocks but the assembled markup holds ${parsedBlocks.length}.`,
				code: 'designsetgo_dropped_inner_blocks',
			},
		];
	}

	nodes.forEach((node, index) => {
		const parsed = parsedBlocks[index];

		if (!parsed || parsed.name === 'core/missing') {
			return;
		}

		const path = childPath(parentPath, index);

		const children = Array.isArray(node.innerBlocks)
			? node.innerBlocks
			: [];
		const parsedChildren = parsed.innerBlocks || [];

		if (parsed.name !== node.name) {
			problems.push({
				path,
				block: node.name,
				reason: `Submitted ${node.name} but the assembled markup holds ${parsed.name} here.`,
				code: 'designsetgo_dropped_inner_blocks',
			});
			return;
		}

		if (children.length !== parsedChildren.length) {
			problems.push({
				path,
				block: node.name,
				reason: `${node.name} was given ${children.length} inner blocks but its saved markup keeps ${parsedChildren.length}; this block cannot hold them.`,
				code: 'designsetgo_dropped_inner_blocks',
			});
			return;
		}

		problems.push(
			...findDroppedInnerBlocks(children, parsedChildren, path)
		);
	});

	return problems;
}

/**
 * Finds the deepest block whose own `save()` throws, so an assemble error can
 * name the offending block instead of the whole tree. Children are checked
 * before their parent: `serialize()` swallows a child's throw while rendering
 * the parent's `<InnerBlocks.Content />`, so a parent rarely throws for its
 * child — the throw surfaces later, when `parse()` re-validates.
 *
 * @param {Function} render       `(block) => string` — renders one block's save content; must throw when its `save()` does.
 * @param {Object[]} blocks       Built blocks, parallel to the tree's nodes.
 * @param {string}   [parentPath] Path of the parent, or '' for the root.
 * @return {{ path: string, block: string }|null} The failing block, or `null` when none throws alone.
 */
export function locateThrowingBlock(render, blocks, parentPath = '') {
	for (let index = 0; index < blocks.length; index += 1) {
		const block = blocks[index];
		const path = childPath(parentPath, index);
		const deeper = locateThrowingBlock(
			render,
			block.innerBlocks || [],
			path
		);

		if (deeper) {
			return deeper;
		}

		try {
			render(block);
		} catch (error) {
			return { path, block: block.name };
		}
	}

	return null;
}

/**
 * @param {Error|*}                              error    Whatever `createBlock`/`serialize`/`parse` threw.
 * @param {{ path: string, block: string }|null} location Failing block, when known.
 * @return {{ path: string, block: string, reason: string, code: string }} Invalid entry.
 */
export function assembleErrorEntry(error, location) {
	const message = error && error.message ? error.message : String(error);

	return {
		path: location ? location.path : '',
		block: location ? location.block : '',
		reason: `assemble failed: ${message}`.slice(0, MAX_REASON_LENGTH),
		code: 'designsetgo_assemble_error',
	};
}
