/**
 * Block helpers for DesignSetGo e2e tests.
 *
 * Two concerns:
 *  1. listTopLevelDesignSetGoBlocks() — synchronous, filesystem-derived list of
 *     the blocks the happy-path sweep should insert. Top-level only (children
 *     ride in via their parent's inner-block template) and minus the
 *     WooCommerce-gated product blocks, which aren't registered without Woo.
 *  2. insertBlockByName() — programmatic, registry-backed block insertion
 *     (added in a later task).
 */

const fs = require('fs');
const path = require('path');

// tests/e2e/helpers -> repo root -> src/blocks
const BLOCKS_DIR = path.join(__dirname, '..', '..', '..', 'src', 'blocks');

// WooCommerce-gated blocks (see includes/class-plugin.php gate_woocommerce_blocks)
// are not registered without WooCommerce, so they can't be inserted in the bare
// wp-env test environment. Documented coverage gap.
const SKIP = new Set([
	'designsetgo/product-showcase-hero',
	'designsetgo/product-categories-grid',
]);

/**
 * List top-level DesignSetGo block names from src/blocks/*\/block.json.
 *
 * @return {string[]} Sorted block names with no parent/ancestor and not in SKIP.
 */
function listTopLevelDesignSetGoBlocks() {
	const names = [];
	for (const entry of fs.readdirSync(BLOCKS_DIR, { withFileTypes: true })) {
		if (!entry.isDirectory()) {
			continue;
		}
		const file = path.join(BLOCKS_DIR, entry.name, 'block.json');
		let json;
		try {
			json = JSON.parse(fs.readFileSync(file, 'utf8'));
		} catch {
			continue;
		}
		const name = json.name;
		if (typeof name !== 'string' || !name.startsWith('designsetgo/')) {
			continue;
		}
		const isChild =
			(Array.isArray(json.parent) && json.parent.length > 0) ||
			(Array.isArray(json.ancestor) && json.ancestor.length > 0);
		if (isChild || SKIP.has(name)) {
			continue;
		}
		names.push(name);
	}
	return names.sort();
}

/**
 * Insert a single block into the editor programmatically (no inserter UI).
 *
 * Uses wp.blocks.createBlock against the LIVE registry — throws if the block
 * isn't registered, so registry truth is the insertion gate. Builds content
 * from the block's registered `example` when present (richer, representative
 * happy-path markup); otherwise inserts defaults and lets the parent's
 * InnerBlocks template hydrate its children. An optional override supplies
 * explicit { attributes, innerBlocks } for blocks the defaults don't cover.
 *
 * @param {import('@playwright/test').Page}            page        - Playwright page object.
 * @param {string}                                     name        - Block name, e.g. 'designsetgo/card'.
 * @param {{attributes?: object, innerBlocks?: Array}} [overrides] - Optional override spec.
 * @return {Promise<{clientId: string, blockCount: number}>} Inserted block id + total top-level count.
 */
async function insertBlockByName(page, name, overrides = {}) {
	return page.evaluate(
		({ blockName, ov }) => {
			const { createBlock, getBlockType } = wp.blocks;
			const dispatch = wp.data.dispatch('core/block-editor');
			const select = wp.data.select('core/block-editor');

			const type = getBlockType(blockName);
			if (!type) {
				throw new Error('Block not registered: ' + blockName);
			}

			// Build a block tree from a plain {name, attributes, innerBlocks} spec.
			const build = (spec) =>
				createBlock(
					spec.name,
					spec.attributes || {},
					(spec.innerBlocks || []).map(build)
				);

			let block;
			if (ov && (ov.attributes || ov.innerBlocks)) {
				block = createBlock(
					blockName,
					ov.attributes || {},
					(ov.innerBlocks || []).map(build)
				);
			} else if (type.example) {
				block = createBlock(
					blockName,
					type.example.attributes || {},
					(type.example.innerBlocks || []).map(build)
				);
			} else {
				block = createBlock(blockName);
			}

			dispatch.insertBlocks(block);
			return {
				clientId: block.clientId,
				blockCount: select.getBlockCount(),
			};
		},
		{ blockName: name, ov: overrides }
	);
}

/**
 * Insert a block NESTED one level inside a parent container block (e.g.
 * `designsetgo/section` or `designsetgo/row`), rather than at the top level
 * of the post. The parent is inserted at the top level (optionally with
 * explicit attribute overrides — e.g. `{ constrainWidth: true }`, since Row
 * defaults `constrainWidth` to false); the child is built the same way
 * insertBlockByName() would build a top-level block (example attributes
 * merged with any override).
 *
 * @param {import('@playwright/test').Page}            page               - Playwright page object.
 * @param {string}                                     parentName         - Parent block name, e.g. 'designsetgo/section'.
 * @param {string}                                     childName          - Child block name, e.g. 'designsetgo/pill'.
 * @param {{attributes?: object, innerBlocks?: Array}} [childOverrides]   - Optional override spec for the child.
 * @param {Object}                                     [parentAttributes] - Optional explicit attributes for the parent container.
 * @return {Promise<{parentClientId: string, childClientId: string, blockCount: number}>} Inserted ids + top-level count.
 */
async function insertNestedBlockByName(
	page,
	parentName,
	childName,
	childOverrides = {},
	parentAttributes = {}
) {
	return page.evaluate(
		({ parent, child, ov, parentAttrs }) => {
			const { createBlock, getBlockType } = wp.blocks;
			const dispatch = wp.data.dispatch('core/block-editor');
			const select = wp.data.select('core/block-editor');

			const type = getBlockType(child);
			if (!type) {
				throw new Error('Block not registered: ' + child);
			}

			const build = (spec) =>
				createBlock(
					spec.name,
					spec.attributes || {},
					(spec.innerBlocks || []).map(build)
				);

			let childBlock;
			if (ov && (ov.attributes || ov.innerBlocks)) {
				childBlock = createBlock(
					child,
					ov.attributes || {},
					(ov.innerBlocks || []).map(build)
				);
			} else if (type.example) {
				childBlock = createBlock(
					child,
					type.example.attributes || {},
					(type.example.innerBlocks || []).map(build)
				);
			} else {
				childBlock = createBlock(child);
			}

			const parentBlock = createBlock(parent, parentAttrs || {}, [
				childBlock,
			]);

			dispatch.insertBlocks(parentBlock);
			return {
				parentClientId: parentBlock.clientId,
				childClientId: childBlock.clientId,
				blockCount: select.getBlockCount(),
			};
		},
		{
			parent: parentName,
			child: childName,
			ov: childOverrides,
			parentAttrs: parentAttributes,
		}
	);
}

module.exports = {
	listTopLevelDesignSetGoBlocks,
	insertBlockByName,
	insertNestedBlockByName,
};
