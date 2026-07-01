/**
 * Shape Divider Component
 *
 * Renders a class-based shape divider `<div>` for section blocks. The shape
 * itself is painted by CSS via `mask-image` (see
 * `src/blocks/section/styles/_shape-divider.scss` and `_shape-masks.scss`) —
 * this component only emits the marker classes and CSS custom properties the
 * stylesheet reads. No inline `<svg>` is rendered.
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
 * Shape Divider Component
 *
 * @param {Object}  props           Component props
 * @param {string}  props.shape     Shape slug (from getShapeDividerNames()), 'inherit' for the
 *                                  theme default, or falsy to render nothing.
 * @param {string}  props.position  'top' or 'bottom'
 * @param {number}  props.height    Height in pixels
 * @param {number}  props.width     Width percentage (100-300)
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
	height = 100,
	width = 100,
	flipX = false,
	flipY = false,
	front = false,
	bandColor,
}) {
	// Don't render if no shape selected
	if (!shape) {
		return null;
	}

	// Validate and clamp numeric values
	const safeHeight = clamp(Number(height) || 100, 10, 500);
	const safeWidth = clamp(Number(width) || 100, 100, 300);

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

	// Build inline styles with validated values. Band is omitted when unset so
	// the CSS `var(..., <fallback>)` base-color default applies. There is no
	// fill color: the shape region is transparent and reveals the section's
	// own background (color, gradient, or image).
	const style = {
		'--dsgo-shape-height': `${safeHeight}px`,
		'--dsgo-shape-width': `${safeWidth}%`,
		...(safeBandColor && { '--dsgo-shape-band': safeBandColor }),
	};

	return <div className={className} style={style} aria-hidden="true" />;
}
