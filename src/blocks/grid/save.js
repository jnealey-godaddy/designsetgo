/**
 * DSG Grid Block - Save Component
 *
 * Saves the block content with declarative styles.
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';

/**
 * Grid Container Save Component
 *
 * @param {Object} props            Component props
 * @param {Object} props.attributes Block attributes
 * @return {JSX.Element} Save component
 */
export default function GridSave({ attributes }) {
	const {
		tagName = 'div',
		constrainWidth,
		contentWidth,
		columnMinWidth,
		desktopColumns,
		tabletColumns,
		mobileColumns,
		rowGap,
		columnGap,
		alignItems,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		style,
	} = attributes;

	// Build className with conditional classes
	// `dsgo-grid--min-width` switches the grid to responsive auto-fit and
	// disables the fixed per-breakpoint column overrides (see style.scss).
	const className = [
		'dsgo-grid',
		`dsgo-grid-cols-${desktopColumns}`,
		`dsgo-grid-cols-tablet-${tabletColumns}`,
		`dsgo-grid-cols-mobile-${mobileColumns}`,
		!constrainWidth && 'dsgo-no-width-constraint',
		columnMinWidth && 'dsgo-grid--min-width',
	]
		.filter(Boolean)
		.join(' ');

	// Block wrapper props - outer div stays full width
	const TagName = tagName || 'div';
	const blockProps = useBlockProps.save({
		className,
		style: {
			...(hoverBackgroundColor && {
				'--dsgo-hover-bg-color':
					convertColorToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-hover-text-color': convertColorToCSSVar(hoverTextColor),
			}),
			...(hoverIconBackgroundColor && {
				'--dsgo-parent-hover-icon-bg': convertColorToCSSVar(
					hoverIconBackgroundColor
				),
			}),
			...(hoverButtonBackgroundColor && {
				'--dsgo-parent-hover-button-bg': convertColorToCSSVar(
					hoverButtonBackgroundColor
				),
			}),
		},
	});

	// Calculate inner styles declaratively (must match edit.js EXACTLY)
	// IMPORTANT: Always provide a default gap to prevent overlapping items
	// Priority: blockGap (WordPress spacing) → custom rowGap/columnGap → preset fallback
	// WordPress 6.1+ stores blockGap as object {top, left} for separate row/column gaps
	// Also need to convert preset format (var:preset|spacing|X) to CSS variable
	const blockGapValue = style?.spacing?.blockGap;
	const isBlockGapObject =
		typeof blockGapValue === 'object' && blockGapValue !== null;
	const blockGapRow = convertPresetToCSSVar(
		isBlockGapObject ? blockGapValue?.top : blockGapValue
	);
	const blockGapColumn = convertPresetToCSSVar(
		isBlockGapObject ? blockGapValue?.left : blockGapValue
	);
	const defaultGap = 'var(--wp--preset--spacing--50)';

	const innerStyles = {
		display: 'grid',
		gridTemplateColumns: columnMinWidth
			? `repeat(auto-fit, minmax(min(100%, ${columnMinWidth}), 1fr))`
			: `repeat(${desktopColumns || 3}, 1fr)`,
		alignItems: alignItems || 'stretch',
		rowGap: blockGapRow || rowGap || defaultGap,
		columnGap: blockGapColumn || columnGap || defaultGap,
	};

	// Apply width constraints to inner container
	// Use custom contentWidth if set, otherwise fallback to theme's contentSize via CSS variable
	if (constrainWidth) {
		innerStyles.maxWidth =
			contentWidth || 'var(--wp--style--global--content-size, 1140px)';
		innerStyles.marginLeft = 'auto';
		innerStyles.marginRight = 'auto';
	}

	// Merge inner blocks props
	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-grid__inner',
		style: innerStyles,
	});

	return (
		<TagName {...blockProps}>
			<div {...innerBlocksProps} />
		</TagName>
	);
}
