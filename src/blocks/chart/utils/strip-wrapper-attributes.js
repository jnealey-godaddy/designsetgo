/**
 * Chart Block - ServerSideRender payload trimming
 *
 * @package
 */

/**
 * Strip the attributes block supports already applied to the editor wrapper.
 *
 * The preview's own `<figure>` carries `get_block_wrapper_attributes()`, so
 * leaving these on the payload paints padding, colour, and the anchor id on
 * both the editor wrapper and the response inside it — the editor would show
 * double the spacing the frontend does, and a duplicate `id` in the DOM.
 *
 * Omitting them is safe: `__experimentalSanitizeBlockAttributes` fills in
 * *missing* attributes from their defaults, and none of these declare one.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} Attributes safe to send to the render endpoint.
 */
export function stripWrapperAttributes(attributes) {
	const {
		style,
		className,
		anchor,
		backgroundColor,
		textColor,
		gradient,
		fontSize,
		fontFamily,
		...rest
	} = attributes;

	return rest;
}
