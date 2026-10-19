/**
 * useWasJustInserted
 *
 * Tells a block whether it arrived through an insertion in this editor
 * session (inserter, pattern, duplicate, paste) rather than by loading the
 * post. Mount effects that rewrite attributes "on insert" must check this:
 * a mount happens on every editor load too, so an unguarded effect silently
 * rewrites saved content and marks the post dirty the moment it is opened.
 *
 * Reads core's `lastBlockInserted` state through the public
 * `wasBlockJustInserted( clientId, source )` selector. Core records only the
 * top-level blocks of an insertion, so a block counts as inserted when it or
 * any ancestor was. A post load (RESET_BLOCKS) clears the record, and the web
 * inserter, patterns and duplication all insert without a `source`.
 *
 * @param {string} clientId The block clientId.
 * @return {boolean} True when the block (or an ancestor) was just inserted.
 */
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

// Sources core sets on `insertBlocks` meta. The web editor omits it; the
// native inserter passes 'inserter_menu'.
const INSERT_SOURCES = [undefined, 'inserter_menu'];

export function wasJustInserted(select, clientId) {
	const { wasBlockJustInserted, getBlockParents } = select(blockEditorStore);
	return [clientId, ...getBlockParents(clientId)].some((id) =>
		INSERT_SOURCES.some((source) => wasBlockJustInserted(id, source))
	);
}

export function useWasJustInserted(clientId) {
	return useSelect((select) => wasJustInserted(select, clientId), [clientId]);
}
