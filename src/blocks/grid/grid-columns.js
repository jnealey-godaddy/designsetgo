/**
 * Grid Block - Desktop column track builder
 *
 * Shared by edit.js and save.js so the editor and the stored markup can never
 * drift apart.
 *
 * @since 2.6.3
 */

/**
 * Build the desktop `grid-template-columns` value.
 *
 * The old form — `repeat(N, minmax(<min>, 1fr))` — overflows its container
 * whenever `N * <min> + gaps` exceeds the available width. A theme with a
 * narrow contentSize (650px, say) plus a 3-column grid with a 480px column
 * min width pushed the columns straight out of the content column instead of
 * wrapping, because a fixed repeat count can never drop a track.
 *
 * `auto-fill` can. The track minimum is the LARGER of the author's min width
 * and the exact 1/N share of the container, so:
 *
 * - wide enough → the share wins, exactly N tracks fit, and the author's
 *   column count is honoured as before;
 * - too narrow → the min width wins, fewer tracks fit, and the remaining
 *   items wrap to the next row.
 *
 * The outer `min(100%, …)` handles the last-resort case where the container
 * is narrower than a single column's min width: the track collapses to the
 * container width rather than overflowing it.
 *
 * A custom `columnTemplate` (a free-form `grid-template-columns` value such as
 * `minmax(0, .7fr) minmax(0, 1.3fr)`) wins over both: it is the only way to
 * express an asymmetric desktop layout. Tablet and mobile keep the column
 * counts, because the responsive stylesheet overrides the inline value below
 * those breakpoints.
 *
 * @param {string} columnMinWidth Author's per-column min width ('' when unset).
 * @param {number} desktopColumns Desktop column count.
 * @param {string} columnGap      Resolved column gap (CSS length or var()).
 * @param {string} columnTemplate Author's desktop track list ('' when unset).
 * @return {string} A `grid-template-columns` value.
 */
export function getGridTemplateColumns(
	columnMinWidth,
	desktopColumns,
	columnGap,
	columnTemplate = ''
) {
	const template = (columnTemplate || '').trim();
	if (template) {
		return template;
	}

	const columns = desktopColumns || 3;

	if (!columnMinWidth) {
		return `repeat(${columns}, 1fr)`;
	}

	// The width one of N columns gets once the gaps between them are removed.
	// `auto-fill` (not `auto-fit`) so unused tracks stay in place: a 2-item,
	// 3-column grid keeps its items one third wide, exactly as the fixed
	// repeat count did.
	// Percentages inside a grid track minimum resolve against the grid
	// container's inline size, so this is exact — N tracks of this size plus
	// N-1 gaps add back up to 100%.
	const share =
		columns > 1
			? `(100% - ${columns - 1} * ${columnGap}) / ${columns}`
			: '100%';

	return `repeat(auto-fill, minmax(min(100%, max(${columnMinWidth}, ${share})), 1fr))`;
}

/**
 * Strip what a column template may never contain: anything that could end
 * the inline declaration or the style attribute, and `url()`. The PHP
 * inserter refuses the same set in Block_Inserter::find_invalid_attribute_values().
 *
 * @param {string} value Raw inspector input.
 * @return {string} The value with unsafe characters removed.
 */
export function sanitizeColumnTemplate(value) {
	return (value || '').replace(/[;{}<>"']/g, '').replace(/url\s*\(/gi, '');
}
