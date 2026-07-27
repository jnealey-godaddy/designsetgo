/**
 * Shape divider size helpers — shared by the Section block's top/bottom
 * dividers and the standalone Section Divider block.
 *
 * Both blocks treat height/width as NULLABLE: an unset value means "inherit the
 * theme.json token" (`--wp--custom--designsetgo--shape-divider--{height,width}`,
 * resolved by `src/styles/shared/_shape-size.scss`). That makes "is this a real
 * authored size?" a shared decision, so it lives here rather than being
 * reimplemented per block.
 */

/**
 * Whether a raw size attribute represents an explicit author choice.
 *
 * `typeof value === 'number'` is NOT sufficient on its own: it is also true for
 * `0`, negatives, and `NaN`, each of which would serialize a meaningless
 * declaration (`--dsgo-shape-height:NaNpx`). None of them can mean "paint
 * nothing", so all collapse to "unset" and inherit instead.
 *
 * A zero height is genuinely reachable — the Abilities API's
 * `configure-shape-divider` schema sets `minimum => 0` for height — while a
 * non-positive width is not (that schema's width minimum is 50), so for width
 * this is defensive against hand-edited markup or a REST write to post content.
 *
 * @param {number|null|undefined} value Raw size attribute.
 * @return {boolean} True when the value is a usable, explicitly-authored size.
 */
export function isExplicitShapeSize(value) {
	return typeof value === 'number' && Number.isFinite(value) && value > 0;
}

/**
 * Clamp a value between min and max.
 *
 * @param {number} value Value to clamp.
 * @param {number} min   Minimum value.
 * @param {number} max   Maximum value.
 * @return {number} Clamped value.
 */
export function clamp(value, min, max) {
	return Math.min(Math.max(value, min), max);
}

/**
 * Normalize a raw size attribute into an explicit, clamped author value or
 * `null` for "not set".
 *
 * The clamp is deliberately NOT applied by every caller. It suits the Section
 * block, whose dividers are absolutely positioned inside the section, so an
 * out-of-range height would overrun the layout. The standalone Section Divider
 * block is its own box — an oversized value there is unusual but harmless — so
 * it uses `isExplicitShapeSize` alone and passes hand-authored sizes through
 * untouched rather than silently rewriting stored content.
 *
 * @param {number|null|undefined} value Raw size attribute.
 * @param {number}                min   Lower clamp bound.
 * @param {number}                max   Upper clamp bound.
 * @return {number|null} Clamped number, or null when the author set nothing.
 */
export function normalizeShapeSize(value, min, max) {
	return isExplicitShapeSize(value) ? clamp(value, min, max) : null;
}
