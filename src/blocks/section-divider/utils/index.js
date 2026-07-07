/**
 * Section Divider — shared markup helpers
 *
 * Re-exports the section block's shape option helpers so the divider's
 * inspector picker stays in sync with the single source of truth, and
 * owns the inline-style / shape-class builders shared by edit.js and
 * save.js so the two paths can never drift out of markup parity.
 *
 * @since 2.7.0
 */
import { convertColorToCSSVar } from '../../../utils/convert-preset-to-css-var';

export {
	getShapeDividerOptions,
	getShapeDivider,
} from '../../section/utils/shape-dividers';

/**
 * Build the inline CSS custom-property style object for a divider.
 *
 * Each var is emitted only when the attribute differs from its
 * CSS-inherited default, so a fully-default divider yields an empty object
 * (bare `is-shape-inherit` markup, no inline style). Shared by edit.js and
 * save.js so their output is structurally identical.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} Inline style object (may be empty).
 */
export function getDividerStyle(attributes) {
	const { height, width, flipX, flipY, fillColor } = attributes;
	const style = {};

	if (fillColor) {
		style['--dsgo-section-divider-fill'] = convertColorToCSSVar(fillColor);
	}

	if (typeof height === 'number') {
		style['--dsgo-shape-height'] = `${height}px`;
	}

	if (width !== 100) {
		style['--dsgo-shape-width'] = `${width}%`;
	}

	if (flipX) {
		style['--dsgo-shape-flip-x'] = -1;
	}

	if (flipY) {
		style['--dsgo-shape-flip-y'] = -1;
	}

	return style;
}

/**
 * Resolve the `is-shape-*` marker class for a shape slug.
 *
 * @param {string} shape Shape slug or 'inherit'.
 * @return {string} Marker class.
 */
export function getDividerShapeClass(shape) {
	return shape === 'inherit' ? 'is-shape-inherit' : `is-shape-${shape}`;
}

/**
 * Build the inline CSS custom-property style object for the block wrapper
 * (the area around/behind the masked shape). Emitted only when set, so an
 * unset background serializes with no inline style and falls through to
 * `transparent` in CSS. Shared by edit.js and save.js.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} Inline style object (may be empty).
 */
export function getDividerWrapperStyle(attributes) {
	const { backgroundColor } = attributes;
	const style = {};

	if (backgroundColor) {
		style['--dsgo-section-divider-bg'] =
			convertColorToCSSVar(backgroundColor);
	}

	return style;
}
