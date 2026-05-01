/**
 * Section Block - Save Component
 *
 * Saves the block content with minimal custom styles.
 * WordPress's layout system handles flex layout through CSS classes.
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertColorToCSSVar,
	convertPresetToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import ShapeDivider from './components/ShapeDivider';

/**
 * Section Container Save Component
 *
 * @param {Object} props            Component props
 * @param {Object} props.attributes Block attributes
 * @return {JSX.Element} Save component
 */
export default function SectionSave({ attributes }) {
	const {
		tagName = 'div',
		backgroundColor,
		textColor,
		constrainWidth,
		contentWidth,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		overlayColor,
		overlayGradient,
		// Shape divider attributes
		shapeDividerTop,
		shapeDividerTopColor,
		shapeDividerTopBackgroundColor,
		shapeDividerTopHeight,
		shapeDividerTopWidth,
		shapeDividerTopFlipX,
		shapeDividerTopFlipY,
		shapeDividerTopFront,
		shapeDividerBottom,
		shapeDividerBottomColor,
		shapeDividerBottomBackgroundColor,
		shapeDividerBottomHeight,
		shapeDividerBottomWidth,
		shapeDividerBottomFlipX,
		shapeDividerBottomFlipY,
		shapeDividerBottomFront,
	} = attributes;

	// Get section's effective background color for shape divider default
	// Prefer inline style (custom color) over preset slug
	const sectionBackgroundColor =
		attributes.style?.color?.background ||
		(backgroundColor ? `var(--wp--preset--color--${backgroundColor})` : '');

	// Get section's effective text color for shape divider background default
	const sectionTextColor =
		attributes.style?.color?.text ||
		(textColor ? `var(--wp--preset--color--${textColor})` : '');

	// Build className with conditional no-width-constraint and overlay classes
	const hasOverlay = !!(overlayColor || overlayGradient);
	const className = [
		'dsgo-stack',
		!constrainWidth && 'dsgo-no-width-constraint',
		hasOverlay && 'dsgo-stack--has-overlay',
		(shapeDividerTop || shapeDividerBottom) &&
			'dsgo-stack--has-shape-divider',
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
			...(overlayColor && {
				'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
			}),
			...(overlayGradient && {
				'--dsgo-overlay-gradient': convertPresetToCSSVar(
					overlayGradient,
					'gradient'
				),
			}),
			...(hasOverlay && {
				'--dsgo-overlay-opacity': '0.8',
			}),
		},
	});

	// Inner container props with width constraints
	// Use custom contentWidth if set, otherwise fallback to theme's contentSize via CSS variable
	const innerStyle = {};
	if (constrainWidth) {
		innerStyle.maxWidth =
			contentWidth || 'var(--wp--style--global--content-size, 1140px)';
		innerStyle.marginLeft = 'auto';
		innerStyle.marginRight = 'auto';
	}

	// Add padding to clear shape dividers (must match edit.js EXACTLY)
	if (shapeDividerTop) {
		innerStyle.paddingTop = `${shapeDividerTopHeight || 100}px`;
	}
	if (shapeDividerBottom) {
		innerStyle.paddingBottom = `${shapeDividerBottomHeight || 100}px`;
	}

	// Merge inner blocks props without the outer block props
	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-stack__inner',
		style: innerStyle,
	});

	return (
		<TagName {...blockProps}>
			<ShapeDivider
				shape={shapeDividerTop}
				color={
					convertColorToCSSVar(shapeDividerTopColor) ||
					sectionBackgroundColor
				}
				backgroundColor={
					convertColorToCSSVar(shapeDividerTopBackgroundColor) ||
					sectionTextColor
				}
				height={shapeDividerTopHeight}
				width={shapeDividerTopWidth}
				flipX={shapeDividerTopFlipX}
				flipY={shapeDividerTopFlipY}
				front={shapeDividerTopFront}
				position="top"
			/>
			<div {...innerBlocksProps} />
			<ShapeDivider
				shape={shapeDividerBottom}
				color={
					convertColorToCSSVar(shapeDividerBottomColor) ||
					sectionBackgroundColor
				}
				backgroundColor={
					convertColorToCSSVar(shapeDividerBottomBackgroundColor) ||
					sectionTextColor
				}
				height={shapeDividerBottomHeight}
				width={shapeDividerBottomWidth}
				flipX={shapeDividerBottomFlipX}
				flipY={shapeDividerBottomFlipY}
				front={shapeDividerBottomFront}
				position="bottom"
			/>
		</TagName>
	);
}
