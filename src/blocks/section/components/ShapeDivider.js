/**
 * Shape Divider Component
 *
 * Renders an SVG shape divider for section blocks.
 * Used in both edit.js and save.js for consistent rendering.
 *
 * @since 1.4.2
 */

import { getShapeDivider } from '../utils/shape-dividers';
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
 * @param {Object}  props                 Component props
 * @param {string}  props.shape           Shape name from SHAPE_DIVIDERS
 * @param {string}  props.color           Fill color for the shape
 * @param {string}  props.backgroundColor Background color behind the shape
 * @param {number}  props.height          Height in pixels
 * @param {number}  props.width           Width percentage (100-300)
 * @param {boolean} props.flipX           Flip horizontally
 * @param {boolean} props.flipY           Flip vertically
 * @param {boolean} props.front           Bring to front (above content)
 * @param {string}  props.position        'top' or 'bottom'
 * @return {JSX.Element|null} Shape divider element or null
 */
export default function ShapeDivider({
	shape,
	color,
	backgroundColor,
	height = 100,
	width = 100,
	flipX = false,
	flipY = false,
	front = false,
	position = 'top',
}) {
	// Don't render if no shape selected
	if (!shape) {
		return null;
	}

	// Get the shape SVG element
	const shapeElement = getShapeDivider(shape);
	if (!shapeElement) {
		return null;
	}

	// Validate and clamp numeric values
	const safeHeight = clamp(Number(height) || 100, 10, 500);
	const safeWidth = clamp(Number(width) || 100, 100, 300);

	// Sanitize color values
	const safeColor = sanitizeColor(color);
	const safeBackgroundColor = sanitizeColor(backgroundColor);

	// Build transform for flipping
	// Use scaleY for consistent 2D flipping behavior
	const transforms = [];

	if (flipX) {
		transforms.push('scaleX(-1)');
	}

	// For bottom position, default is flipped (pointing into section)
	// flipY inverts this behavior
	if (position === 'bottom' && !flipY) {
		transforms.push('scaleY(-1)');
	} else if (position !== 'bottom' && flipY) {
		// For top position, flipY flips it
		transforms.push('scaleY(-1)');
	}

	// Calculate width offset for stretched shapes (centering)
	// Use Math.max to guard against edge cases
	const widthOffset = Math.max(0, (safeWidth - 100) / 2);

	// Build className
	const className = [
		'dsgo-shape-divider',
		`dsgo-shape-divider--${position}`,
		front && 'dsgo-shape-divider--front',
	]
		.filter(Boolean)
		.join(' ');

	// Determine gradient direction for the anti-aliasing color strip.
	// The gradient covers the SVG's flat edge (where anti-aliasing gaps appear).
	// When flipY changes which edge faces the section, the gradient must flip too.
	//
	// | position | flipY | flat edge | gradient dir |
	// |----------|-------|-----------|--------------|
	// | top      | false | bottom    | to top       |
	// | top      | true  | top       | to bottom    |
	// | bottom   | false | top       | to bottom    |
	// | bottom   | true  | bottom    | to top       |
	const gradientDir = (position === 'top') !== flipY ? 'to top' : 'to bottom';

	// Build inline styles with validated values
	const style = {
		'--dsgo-shape-height': `${safeHeight}px`,
		'--dsgo-shape-width': `${safeWidth}%`,
		'--dsgo-shape-offset': `-${widthOffset}%`,
		'--dsgo-shape-color': safeColor || 'transparent',
		'--dsgo-shape-gradient-dir': gradientDir,
		...(safeBackgroundColor && {
			'--dsgo-shape-background': safeBackgroundColor,
		}),
	};

	return (
		<div className={className} style={style} aria-hidden="true">
			<svg
				viewBox="0 0 1200 120"
				preserveAspectRatio="none"
				style={{
					transform:
						transforms.length > 0
							? transforms.join(' ')
							: undefined,
				}}
			>
				{shapeElement}
			</svg>
		</div>
	);
}
