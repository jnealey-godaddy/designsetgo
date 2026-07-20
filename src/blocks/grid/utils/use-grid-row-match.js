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

// Card containers that participate in row matching, mirroring the CSS allowlist
// (Section / Flex / Group). Other blocks (e.g. the Card block) are left alone,
// so they neither align nor inflate the shared row count.
const SUPPORTED_CARD_BLOCKS = [
	'designsetgo/section',
	'designsetgo/flex',
	'core/group',
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
	// Per-card direct child counts (the frontend counts these from the DOM).
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
