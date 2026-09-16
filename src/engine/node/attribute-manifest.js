/**
 * Generates the committed JS-registered-attribute manifest
 * (`includes/abilities/agent-build/data/attribute-manifest.json`) that PHP's
 * `Tree_Attributes` consults before rejecting an unknown attribute name.
 *
 * PHP cannot enumerate "every attribute name a block carries once the real
 * editor has registered it" on its own — block-support attributes such as
 * `anchor` are added to `getBlockType().attributes` entirely client-side
 * (`wp-includes/block-supports/anchor.php` never adds `anchor` to
 * `WP_Block_Type->attributes`; it only reads `$attributes['anchor']` off
 * whatever was already parsed), and every DesignSetGo extension attribute
 * (`dsgoAnimationEnabled`, ...) is added by a `blocks.registerBlockType` JS
 * filter with no PHP mirror at all. Measured parity (see the unknown-attribute
 * report) found only 79 of 179 compared block types where PHP's own
 * `WP_Block_Type->attributes` already covers every JS-registered name — far
 * too few for a static allowlist, so PHP instead treats "in this manifest"
 * as "known to JS", the same way it already treats "in `WP_Block_Type
 * ->attributes`" as "known to PHP": unknown to both is the only case PHP
 * rejects (`Tree_Attributes::check_unknown_attributes()`).
 *
 * Covers `designsetgo/*` and `core/*` block types — the same universe the
 * unknown-attribute-brief parity measurement used — rather than trying to
 * reverse-engineer each extension's own allowed-block list, which would
 * need updating every time an extension's target list changes.
 *
 * CommonJS, matching every other `src/engine/node/*` file: this module is
 * `require()`d from `run.js`, both under the webpack Node bundle and under
 * plain Jest (`tests/unit/engine/attribute-manifest-freshness.test.js`
 * regenerates it in memory and fails when the committed file is stale).
 */
'use strict';

/**
 * @param {Object} blocksApi A `@wordpress/blocks`-shaped module, already
 *                           populated by `registerForNode()`/`registerForJest()`.
 * @return {Object<string, string[]>} Every compared block's attribute names,
 *   sorted, keyed by block name in sorted order (so the written JSON is
 *   diff-friendly and regenerating it is a no-op when nothing changed).
 */
function buildAttributeManifest(blocksApi) {
	const manifest = {};

	blocksApi
		.getBlockTypes()
		.filter(
			(blockType) =>
				blockType.name.startsWith('designsetgo/') ||
				blockType.name.startsWith('core/')
		)
		.map((blockType) => blockType.name)
		.sort()
		.forEach((name) => {
			const blockType = blocksApi.getBlockType(name);
			manifest[name] = Object.keys(blockType.attributes ?? {}).sort();
		});

	return manifest;
}

module.exports = { buildAttributeManifest };
