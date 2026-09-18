/**
 * Section outer box width.
 *
 * `contentWidth` caps the INNER container (`.dsgo-stack__inner`), which is
 * right for a full-bleed band: the section paints edge to edge and only its
 * content sits in a measure. It is wrong for a section used as a painted BOX —
 * one carrying a background, border, shadow or overlay that is meant to be
 * narrower than its parent. `boxWidth` caps the OUTER element instead, so every
 * painted affordance (background, border, shadow, overlay `::before`, and the
 * absolutely-positioned shape dividers, which size off the wrapper) narrows
 * with it.
 *
 * The two are independent: a 430px box can still hold a 320px measure.
 *
 * Both edit.js and save.js build the wrapper class list and inline style from
 * THIS module so the editor preview and the saved markup stay byte-identical
 * (a mismatch is a block-validation error). Nothing is emitted when `boxWidth`
 * is unset, so existing content serializes exactly as before.
 *
 * @since 2.7.0
 */

/**
 * Marker class emitted on the wrapper when a box width is active.
 *
 * It carries the box's horizontal PLACEMENT (see styles/_box-width.scss):
 * centred by default, and no margin at all inside a flex parent so the
 * parent's own alignment governs. It also opts the section OUT of the
 * nested-container overrides in style.scss / editor.scss / row / grid /
 * _utilities.scss that force `max-width: none !important` on unaligned child
 * containers — those would otherwise defeat the cap, and an `!important`
 * stylesheet declaration beats a normal inline one.
 *
 * @type {string}
 */
export const BOX_WIDTH_CLASS = 'dsgo-stack--has-box-width';

/**
 * Inline wrapper styles that cap the section's own box.
 *
 * - `width: 100%` makes the box REACH the cap. Without it a capped section
 *   inside a flex parent shrink-wraps to its content instead.
 * - `maxWidth` is the cap itself.
 *
 * Deliberately NO margins. Placement is a stylesheet concern
 * (styles/_box-width.scss) because an inline `margin: auto` cannot be
 * overridden, and inside a flex parent auto margins are resolved BEFORE
 * `align-items` / `justify-content` — so an inline auto margin would silently
 * override the alignment the author set on the PARENT section. Leaving margins
 * to CSS also means WordPress's own spacing support, which serializes
 * `style.spacing.margin` inline, wins over the default placement without the
 * two ever competing for the same declaration.
 *
 * Both are inline rather than in the partial because they have to beat
 * `[data-block] { width: auto }` in the editor and core's constrained-layout
 * measure on the front end.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} Style object; empty when no box width is set.
 */
export function getBoxWidthStyle(attributes = {}) {
	const boxWidth = attributes?.boxWidth;

	if (!boxWidth) {
		return {};
	}

	return {
		width: '100%',
		maxWidth: boxWidth,
	};
}

/**
 * Whether the wrapper should carry {@link BOX_WIDTH_CLASS}.
 *
 * @param {Object} attributes Block attributes.
 * @return {boolean} True when a box width is set.
 */
export function hasBoxWidth(attributes = {}) {
	return !!attributes?.boxWidth;
}
