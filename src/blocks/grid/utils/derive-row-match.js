/**
 * Grid "Align Rows" — editor-side derivation.
 *
 * Pure helper so the editor's row-matching math can be unit-tested without
 * rendering the block. It mirrors view.js's frontend contract: publish a
 * per-card row count and only activate the subgrid when the grid actually
 * shows 2+ columns at the previewed width.
 */

/**
 * Map the editor's preview device type to the column count in effect at that
 * width. Mirrors the responsive `@media` breakpoints in the stylesheets.
 *
 * @param {string} deviceType      Preview device: 'Desktop' | 'Tablet' | 'Mobile'.
 * @param {Object} columns         Configured column counts.
 * @param {number} columns.desktop Desktop column count.
 * @param {number} columns.tablet  Tablet column count.
 * @param {number} columns.mobile  Mobile column count.
 * @return {number} Columns effective at the previewed width.
 */
export function effectiveColumnsForDevice(
	deviceType,
	{ desktop, tablet, mobile }
) {
	return { Mobile: mobile, Tablet: tablet }[deviceType] ?? desktop;
}

/**
 * Derive whether subgrid row-matching should be active in the editor and the
 * row count to publish.
 *
 * @param {Object}   opts                 Options.
 * @param {boolean}  opts.matchRowHeights Whether the toggle is on.
 * @param {number[]} opts.cardChildCounts Direct-child count per card block.
 * @param {string}   opts.deviceType      Preview device type.
 * @param {number}   opts.desktopColumns  Desktop column count.
 * @param {number}   opts.tabletColumns   Tablet column count.
 * @param {number}   opts.mobileColumns   Mobile column count.
 * @return {{isActive: boolean, rowCount: number}} Activation + published row count.
 */
export function deriveRowMatch({
	matchRowHeights,
	cardChildCounts = [],
	deviceType = 'Desktop',
	desktopColumns,
	tabletColumns,
	mobileColumns,
}) {
	if (!matchRowHeights) {
		return { isActive: false, rowCount: 0 };
	}

	const rowCount = cardChildCounts.reduce(
		(max, count) => Math.max(max, count || 0),
		0
	);
	const effectiveColumns = effectiveColumnsForDevice(deviceType, {
		desktop: desktopColumns,
		tablet: tabletColumns,
		mobile: mobileColumns,
	});

	return { isActive: effectiveColumns > 1 && rowCount > 0, rowCount };
}
