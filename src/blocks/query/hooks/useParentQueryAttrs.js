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
 * @param {string}  clientId Current block clientId.
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
			const parents = getBlockParents(clientId);
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
