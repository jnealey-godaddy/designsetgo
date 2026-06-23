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
		if (!fs.existsSync(file)) {
			continue;
		}
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

module.exports = {
	SKIP,
	listTopLevelDesignSetGoBlocks,
};
