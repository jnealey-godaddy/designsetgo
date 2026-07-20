/**
 * Grid "Align Rows" — editor store wiring.
 *
 * Reads the per-card row count and the previewed device type from the editor
 * stores and feeds them to the pure `deriveRowMatch()` helper. Kept out of
 * edit.js so the component stays lean and the store dependencies live in one
 * place. The math itself is unit-tested via derive-row-match.test.js.
 */

import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { deriveRowMatch } from './derive-row-match';

// Card containers that participate in row matching. The set of "supported
// cards" is encoded in FOUR places that must stay in correspondence — keep
// this list in sync with them:
//   1. style.scss  — subgrid allowlist        (.dsgo-stack, .dsgo-flex, .wp-block-group)
//   2. style.scss  — nested-wrapper rule      (.dsgo-stack__inner, .dsgo-flex__inner)
//   3. view.js     — countCardRows()          (same DOM classes)
//   4. this list   — editor block names       (below)
//
// The three CSS/DOM checks match by class, which is rename-proof (Section still
// renders `.dsgo-stack`, Row still renders `.dsgo-flex`). This list matches by
// block NAME, which changed in the stack→section / flex→row rename, so it must
// include BOTH the current and legacy names or the editor preview drifts from
// the frontend. Other blocks (e.g. the Card block) are intentionally absent, so
// they neither align nor inflate the shared row count.
const SUPPORTED_CARD_BLOCKS = [
	'designsetgo/section', // renders .dsgo-stack / .dsgo-stack__inner
	'designsetgo/stack', // legacy alias of section
	'designsetgo/row', // renders .dsgo-flex / .dsgo-flex__inner
	'designsetgo/flex', // legacy alias of row
	'core/group', // renders .wp-block-group
];

/**
 * Derive the editor's row-matching state for a Grid block.
 *
 * @param {string}  clientId                The Grid block's client id.
 * @param {Object}  columns                 Column configuration + toggle.
 * @param {boolean} columns.matchRowHeights Whether "Align Rows" is on.
 * @param {number}  columns.desktopColumns  Desktop column count.
 * @param {number}  columns.tabletColumns   Tablet column count.
 * @param {number}  columns.mobileColumns   Mobile column count.
 * @return {{isRowMatchActive: boolean, matchRowCount: number}} Activation + row count.
 */
export function useGridRowMatch(
	clientId,
	{ matchRowHeights, desktopColumns, tabletColumns, mobileColumns }
) {
	// Per-card row counts. This is a block-count approximation: it counts each
	// card's direct child *blocks*, whereas the frontend (view.js
	// countCardRows) counts rendered DOM *elements*. They're equal for normal
	// content, but can diverge for blocks that don't map 1:1 to a rendered root
	// element — e.g. a `core/block` (reusable / synced pattern) reference counts
	// as one block here but unpacks to N sibling elements on the page, and a
	// block whose save() renders nothing counts as one block but zero elements.
	// The effect is a cosmetic editor↔page difference in `--dsgo-row-count`; it
	// is never serialized (this only drives the editor preview), so it can't
	// corrupt content. Resolving reusable refs to their real element count would
	// need the entity store and isn't worth it for this edge.
	// Unsupported card blocks contribute 0 so they don't inflate the row count.
	const cardChildCounts = useSelect(
		(select) => {
			const { getBlock } = select(blockEditorStore);
			const block = getBlock(clientId);
			return (block?.innerBlocks || []).map((card) =>
				SUPPORTED_CARD_BLOCKS.includes(card?.name)
					? card?.innerBlocks?.length || 0
					: 0
			);
		},
		[clientId]
	);

	// The previewed device type. `getDeviceType` on core/editor has been stable
	// since WP 6.5 (the plugin requires 6.7), so no experimental fallback.
	const deviceType = useSelect(
		(select) => select('core/editor')?.getDeviceType?.() ?? 'Desktop',
		[]
	);

	const { isActive, rowCount } = deriveRowMatch({
		matchRowHeights,
		cardChildCounts,
		deviceType,
		desktopColumns,
		tabletColumns,
		mobileColumns,
	});

	return { isRowMatchActive: isActive, matchRowCount: rowCount };
}
