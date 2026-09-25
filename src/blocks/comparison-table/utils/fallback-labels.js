/**
 * Comparison Table fallback labels.
 *
 * save() renders two defaults through __(): the "Popular" badge and the
 * "Get Started" text of a linked CTA with no text of its own. Both are read
 * back from the stored markup (featuredBadgeText, savedCtaTexts), so the post
 * stays valid when it is opened in another editor language. The editor shows
 * the same resolved labels, so authors see what visitors see.
 */

import { __ } from '@wordpress/i18n';

/**
 * The stored CTA text for a column, or '' when there is none.
 *
 * savedCtaTexts is positional: one entry per column that renders a link, in
 * column order, because that is what `a.dsgo-comparison-table__cta` matches.
 *
 * @param {Array}  columns       Columns.
 * @param {Array}  savedCtaTexts Texts read back from the stored links.
 * @param {number} colIndex      Column index.
 * @return {string} Stored text.
 */
export function getSavedCtaText(columns, savedCtaTexts, colIndex) {
	const position = columns
		.slice(0, colIndex)
		.filter((col) => col.link).length;
	return savedCtaTexts?.[position]?.text || '';
}

/**
 * The text a linked CTA renders when its column has no linkText.
 *
 * @param {Array}  columns       Columns.
 * @param {Array}  savedCtaTexts Texts read back from the stored links.
 * @param {number} colIndex      Column index.
 * @return {string} Fallback CTA text.
 */
export function getCtaFallback(columns, savedCtaTexts, colIndex) {
	return (
		getSavedCtaText(columns, savedCtaTexts, colIndex) ||
		__('Get Started', 'designsetgo')
	);
}

/**
 * The text the featured badge renders.
 *
 * @param {string|undefined} featuredBadgeText Text read back from the badge.
 * @return {string} Badge text.
 */
export function getFeaturedBadgeText(featuredBadgeText) {
	return featuredBadgeText || __('Popular', 'designsetgo');
}

/**
 * Carry the stored CTA fallbacks across a column edit.
 *
 * An edit to one column must not change another column's button, so each
 * stored fallback follows its column instead of being dropped wholesale. A
 * fallback is kept only where it was, and still is, a fallback: a linked
 * column with no CTA text of its own. Stored text that was the column's own
 * linkText is dropped, so clearing that field shows the default rather than
 * bringing the old custom label back.
 *
 * @param {Array}              columns       Columns before the edit.
 * @param {Array}              nextColumns   Columns after the edit.
 * @param {Array|undefined}    savedCtaTexts Texts read back from the stored links.
 * @param {Array<number|null>} sourceIndex   For each next column, its index in
 *                                           `columns`, or null for a new column.
 * @return {Array|undefined} Stored texts for nextColumns.
 */
export function remapSavedCtaTexts(
	columns,
	nextColumns,
	savedCtaTexts,
	sourceIndex
) {
	if (!savedCtaTexts?.length) {
		return savedCtaTexts;
	}
	const next = [];
	nextColumns.forEach((col, i) => {
		if (!col.link) {
			return;
		}
		const from = sourceIndex[i] ?? null;
		const previous = null === from ? null : columns[from];
		const keep =
			previous && previous.link && !previous.linkText && !col.linkText;
		next.push({
			text: keep ? getSavedCtaText(columns, savedCtaTexts, from) : '',
		});
	});
	return next.some((entry) => entry.text) ? next : undefined;
}
