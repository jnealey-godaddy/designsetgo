/**
 * Layout for a container transform (Section / Row / Grid).
 *
 * Transforms spread the source block's attributes, and `layout` is one of
 * them. Once an author has touched any layout control, WordPress stores the
 * whole layout, orientation included — so a Section's
 * `{ type: 'flex', orientation: 'vertical' }` would turn the new Row into a
 * vertical stack. Neither block exposes an orientation toggle to undo that.
 *
 * @param {Object|undefined} layout      Source block's layout attribute.
 * @param {string|null}      orientation 'horizontal' or 'vertical' for a flex
 *                                       target; null for a target with its
 *                                       own layout (Grid).
 * @return {Object|undefined} Layout for the target, or undefined to use the
 *                            target block's own default.
 */
export function transformLayout(layout, orientation) {
	if (!orientation || !layout || (layout.type && layout.type !== 'flex')) {
		return undefined;
	}
	return { ...layout, type: 'flex', orientation };
}
