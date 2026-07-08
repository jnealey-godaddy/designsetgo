/**
 * Explicit-value predicates for kit-controllable block attributes.
 *
 * Several blocks (icon-button, icon-list-item, image-accordion, blobs) omit a
 * design value from their save() output unless the author set it explicitly, so
 * the stylesheet's themeable default can take over. These helpers centralise the
 * "is this explicitly set?" check so the save()/edit.js pairs — which MUST emit
 * byte-identical markup — cannot drift on the predicate itself.
 *
 * @since 2.4.0
 */

/**
 * Whether a string attribute holds an explicit (non-empty) value.
 *
 * Uses a `typeof` guard rather than truthiness so an explicit "0"/"0px" style
 * value is still treated as set.
 *
 * @param {*} value Attribute value.
 * @return {boolean} True when value is a non-blank string.
 */
export function hasExplicitString(value) {
	return typeof value === 'string' && value.trim() !== '';
}

/**
 * Whether a numeric attribute holds an explicit value.
 *
 * Uses a `typeof` guard rather than truthiness so an explicit `0` is still
 * treated as set.
 *
 * @param {*} value Attribute value.
 * @return {boolean} True when value is a number.
 */
export function hasExplicitNumber(value) {
	return typeof value === 'number';
}
