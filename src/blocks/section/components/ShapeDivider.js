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
 * @param {string}  props.fillColor Shape fill color. Falls back to `currentColor` in CSS when omitted.
 * @param {string}  props.bandColor Color of the band behind the shape. Falls back to the theme
 *                                  base color in CSS when omitted.
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
	fillColor,
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
	const safeFillColor = sanitizeColor(fillColor);
	const safeBandColor = sanitizeColor(bandColor);

	// Build className
	const className = [
		'dsgo-shape-divider',
		`dsgo-shape-divider--${position}`,
		`is-shape-${shape}`,
		flipX && 'is-flip-x',
		flipY && 'is-flip-y',
		front && 'is-front',
	]
		.filter(Boolean)
		.join(' ');

	// Build inline styles with validated values. Fill/band are omitted when
	// unset so the CSS `var(..., <fallback>)` defaults apply.
	const style = {
		'--dsgo-shape-height': `${safeHeight}px`,
		'--dsgo-shape-width': `${safeWidth}%`,
		...(safeFillColor && { '--dsgo-shape-fill': safeFillColor }),
		...(safeBandColor && { '--dsgo-shape-band': safeBandColor }),
	};

	return <div className={className} style={style} aria-hidden="true" />;
}
