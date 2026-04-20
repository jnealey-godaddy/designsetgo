/**
 * context-filter.js
 *
 * Appends item-context keys to every block's `usesContext` so the visibility
 * gate (Task B5) can read designsetgo/itemIndex, designsetgo/itemMeta, and
 * designsetgo/itemTerms from any block inside a Dynamic Query template.
 *
 * @since 2.3.0
 */
import { addFilter } from '@wordpress/hooks';

/**
 * Blocks whose usesContext should NOT be touched (no runtime, or special
 * blocks that don't participate in the editor block-tree context system).
 */
const BLOCKED = new Set(['core/freeform', 'core/missing', 'core/template-part']);

/** Context keys provided by the Dynamic Query block per rendered item. */
const CONTEXT_KEYS = [
	'designsetgo/itemIndex',
	'designsetgo/itemMeta',
	'designsetgo/itemTerms',
];

/**
 * Filter callback — appends CONTEXT_KEYS to settings.usesContext, deduped.
 *
 * @param {Object} settings Block type settings.
 * @param {string} name     Block type name.
 * @return {Object} Updated settings.
 */
function addItemContextUses(settings, name) {
	if (BLOCKED.has(name)) {
		return settings;
	}
	const existing = settings.usesContext ?? [];
	const merged = [...new Set([...existing, ...CONTEXT_KEYS])];
	return { ...settings, usesContext: merged };
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/visibility/uses-context',
	addItemContextUses
);
