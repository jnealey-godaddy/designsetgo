/**
 * useParentQueryAttrs — walk the block tree and return the nearest ancestor
 * designsetgo/query block's attributes.
 *
 * Used by layout-host edit.js files (slider, scroll-slides) so the editor
 * preview uses the same query config (postType, perPage, filters, orderBy)
 * the frontend will. Returns null when no parent designsetgo/query is found
 * or when `enabled` is false.
 *
 * @since 2.6.0
 *
 * @param {string}  clientId       Current block clientId.
 * @param {boolean} [enabled=true] Skip the lookup when false (lets callers
 *                                 pass a stable falsy value outside of
 *                                 query mode without wrapping the hook call).
 * @return {Object|null} Parent query block attributes, or null.
 */
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

export default function useParentQueryAttrs(clientId, enabled = true) {
	return useSelect(
		(select) => {
			if (!enabled) {
				return null;
			}
			const { getBlockParents, getBlock } = select(blockEditorStore);
			// `ascending: true` — getBlockParents() otherwise reverses the walk
			// to root-first, and the loop below would answer with the OUTERMOST
			// enclosing query. This hook feeds the layout host's editor preview,
			// so a Loop Carousel inside a nested query would preview the outer
			// query's posts while the front end renders the inner query's.
			const parents = getBlockParents(clientId, true);
			for (const parentId of parents) {
				const parent = getBlock(parentId);
				if (parent?.name === 'designsetgo/query') {
					return parent.attributes;
				}
			}
			return null;
		},
		[clientId, enabled]
	);
}
