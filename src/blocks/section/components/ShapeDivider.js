/**
 * Shape Divider Component
 *
 * Renders a class-based shape divider `<div>` for section blocks. The shape
 * itself is painted by CSS via `mask-image` (see
 * `src/blocks/section/styles/_shape-divider.scss` and the shared
 * `src/styles/shared/_shape-masks.scss` / `_shape-mask-classes.scss` /
 * `_shape-size.scss` partials) — this component only emits the marker classes
 * and CSS custom properties the stylesheet reads. No inline `<svg>` is
 * rendered, and a size property is emitted only for an explicit author value
 * so the theme.json token cascade in `_shape-size.scss` can fill in the rest.
 *
 * Used in both edit.js and save.js for consistent rendering.
 *
 * @since 1.4.2
 */

import { sanitizeColor } from '../utils/sanitize-color';

/**
 * Clamp a value between min and max
 *
 * @param {number} value - Value to clamp
 * @param {number} min   - Minimum value
 * @param {number} max   - Maximum value
 * @return {number} Clamped value
 */
function clamp(value, min, max) {
	return Math.min(Math.max(value, min), max);
}

/**
 * Normalize a raw numeric shape-divider attribute into an explicit author
 * value or `null` for "not set".
 *
 * Height and width are nullable by design: an unset value means "inherit the
 * theme.json token" (`--wp--custom--designsetgo--shape-divider--height` /
 * `--width`, resolved in `_shape-divider.scss`), so anything that is not a
 * usable positive number must collapse to `null` rather than to a hard-coded
 * default. Non-positive values are treated as unset for the same reason: they
 * cannot mean "paint nothing", so inheriting is the only sane reading. A zero
 * height is genuinely reachable — the Abilities API's `configure-shape-divider`
 * schema sets `minimum => 0` for height — while a non-positive width is not
 * (that schema's width minimum is 50), so for width this is purely defensive
 * against hand-edited markup or a REST write to post content.
 *
 * @param {number|null|undefined} value Raw attribute value.
 * @param {number}                min   Lower clamp bound.
 * @param {number}                max   Upper clamp bound.
 * @return {number|null} Clamped number, or null when the author set nothing.
 */
function normalizeShapeSize(value, min, max) {
	return typeof value === 'number' && Number.isFinite(value) && value > 0
		? clamp(value, min, max)
		: null;
}

/**
 * Resolve the height a shape divider actually paints from its raw height
 * attribute, applying the same clamp the component renders with (10–500px).
 * Exported so save()/edit() can size the inner content clearance to the
 * divider's real rendered height instead of the raw, possibly out-of-range,
 * attribute value.
 *
 * Returns `null` when no explicit height is set — the divider then inherits
 * the theme token, and the clearance must inherit it too (the stylesheet
 * fallback on `--dsgo-shape-clearance-*` reads the same token), so save()
 * emits no wrapper variable at all in that case.
 *
 * @param {number|null|undefined} height Raw height attribute (px).
 * @return {number|null} Rendered height in px (10–500), or null when unset.
 */
export function getRenderedShapeHeight(height) {
	return normalizeShapeSize(height, 10, 500);
}

/**
 * Shape Divider Component
 *
 * @param {Object}  props           Component props
 * @param {string}  props.shape     Shape slug (from getShapeDividerNames()), 'inherit' for the
 *                                  theme default, or falsy to render nothing.
 * @param {string}  props.position  'top' or 'bottom'
 * @param {number}  props.height    Height in pixels (10-500), or null to inherit the theme token
 * @param {number}  props.width     Width percentage (100-300), or null to inherit the theme token
 * @param {boolean} props.flipX     Flip horizontally
 * @param {boolean} props.flipY     Flip vertically
 * @param {boolean} props.front     Bring to front (above content)
 * @param {string}  props.bandColor Color of the band beside the shape (the part adjoining the
 *                                  neighbouring section). Falls back to the theme base color in
 *                                  CSS when omitted. The shape region itself is transparent and
 *                                  shows the section's own background through it.
 * @return {JSX.Element|null} Shape divider element or null
 */
export default function ShapeDivider({
	shape,
	position = 'top',
	height = null,
	width = null,
	flipX = false,
	flipY = false,
	front = false,
	bandColor,
}) {
	// Don't render if no shape selected
	if (!shape) {
		return null;
	}

	// Validate and clamp numeric values. Both collapse to null when the author
	// set nothing, so the CSS custom property is omitted and the stylesheet's
	// theme.json → hard-coded fallback chain resolves the size instead.
	const safeHeight = getRenderedShapeHeight(height);
	const safeWidth = normalizeShapeSize(width, 100, 300);

	// Sanitize color values
	const safeBandColor = sanitizeColor(bandColor);

	// Bottom dividers flip vertically by default: the shapes are authored with
	// their solid edge at the bottom of the viewBox (i.e. facing the section
	// for a TOP divider), so a bottom divider must flip to face its section.
	// `flipY` inverts the per-position default. This mirrors the legacy
	// renderer and the inspector preview (ShapeDividerControls) exactly, so
	// existing content and the editor preview stay visually consistent.
	const flipYActive = position === 'bottom' ? !flipY : flipY;

	// Build className
	const className = [
		'dsgo-shape-divider',
		`dsgo-shape-divider--${position}`,
		`is-shape-${shape}`,
		flipX && 'is-flip-x',
		flipYActive && 'is-flip-y',
		front && 'is-front',
	]
		.filter(Boolean)
		.join(' ');

	// Build inline styles, emitting a custom property ONLY when the author set
	// an explicit value, so an untouched divider serializes with no inline
	// style at all and inherits the theme. The stylesheet supplies the
	// fallback chains — `--dsgo-shape-height` →
	// `--wp--custom--designsetgo--shape-divider--height` → 100px, and the same
	// shape for width and the band color. An explicit value is always emitted,
	// including one that equals the plugin default, because that is how an
	// author pins a divider against a theme token that says otherwise.
	const style = {
		...(safeHeight !== null && {
			'--dsgo-shape-height': `${safeHeight}px`,
		}),
		...(safeWidth !== null && { '--dsgo-shape-width': `${safeWidth}%` }),
		...(safeBandColor && { '--dsgo-shape-band': safeBandColor }),
	};

	// Only attach the style prop when there's something to set, so a
	// default divider serializes as a bare <div> with no empty style="".
	const styleProps = Object.keys(style).length > 0 ? { style } : {};

	return <div className={className} {...styleProps} aria-hidden="true" />;
}
