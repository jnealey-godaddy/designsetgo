/**
 * useQueryItemHost — which layout block is presenting a query's results.
 *
 * A Dynamic Query renders its items through exactly one "item host" child:
 * designsetgo/query-results lays them out as a grid, designsetgo/slider
 * presents them as a carousel, designsetgo/scroll-slides as pinned panels.
 * Sibling blocks sometimes need to know which one the author picked —
 * designsetgo/query-pagination adapts to it.
 *
 * Mirrors designsetgo_query_item_host_block_names() and
 * designsetgo_query_host_supports_infinite_scroll() in
 * src/blocks/query/render-helpers.php. The server is the authority for what
 * actually renders; this is the editor's preview of that decision, so the two
 * lists have to move together.
 *
 * @since 2.7.0
 */
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

/** Blocks that may act as the item host inside a Dynamic Query. */
export const QUERY_ITEM_HOST_BLOCKS = [
	'designsetgo/query-results',
	'designsetgo/slider',
	'designsetgo/scroll-slides',
];

/** The one host that grows the page vertically as items are added. */
export const GRID_ITEM_HOST_BLOCK = 'designsetgo/query-results';

/**
 * Whether a host can carry infinite-scroll pagination.
 *
 * An empty host name means the query has no host block at all (a legacy tree
 * whose item template sits directly under the query), which still renders a
 * vertical list.
 *
 * @param {string} hostName Item host block name.
 * @return {boolean} True when a scroll sentinel below the items is meaningful.
 */
export function hostSupportsInfiniteScroll(hostName) {
	return !hostName || hostName === GRID_ITEM_HOST_BLOCK;
}

/**
 * Find the item host of the Dynamic Query enclosing this block.
 *
 * @param {string} clientId Current block clientId.
 * @return {string} The host block name, or '' when there is none.
 */
export default function useQueryItemHost(clientId) {
	return useSelect(
		(select) => {
			const { getBlockParents, getBlock } = select(blockEditorStore);
			// `ascending: true` — getBlockParents() otherwise reverses the walk
			// to root-first, and .find() would resolve the OUTERMOST enclosing
			// query rather than the nearest one. The server registry is keyed
			// per queryId and so is always nearest-scoped; a pagination block
			// inside a nested query has to match that or the editor previews a
			// sentinel for a host that is not the one about to render it.
			const queryId = getBlockParents(clientId, true).find(
				(parentId) => getBlock(parentId)?.name === 'designsetgo/query'
			);
			if (!queryId) {
				return '';
			}
			// Only direct children count, matching the server's scan of the
			// query's parsed innerBlocks.
			const host = (getBlock(queryId)?.innerBlocks || []).find((child) =>
				QUERY_ITEM_HOST_BLOCKS.includes(child.name)
			);
			return host?.name || '';
		},
		[clientId]
	);
}
