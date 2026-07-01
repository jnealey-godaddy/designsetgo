/**
 * Section Block - Save Component
 *
 * Saves the block content with minimal custom styles.
 * WordPress's layout system handles flex layout through CSS classes.
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
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
		constrainWidth,
		contentWidth,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		overlayColor,
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

	// Get section's effective background color for shape divider fill default.
	// Prefer inline style (custom color) over preset slug.
	const sectionBackgroundColor =
		attributes.style?.color?.background ||
		(backgroundColor ? `var(--wp--preset--color--${backgroundColor})` : '');

	// Shape divider fill: explicit color wins, otherwise falls back to the
	// section's own background color. If neither is set, omit the CSS var
	// entirely so the stylesheet's `currentColor` fallback applies.
	const shapeDividerTopFillColor =
		convertColorToCSSVar(shapeDividerTopColor) || sectionBackgroundColor;
	const shapeDividerBottomFillColor =
		convertColorToCSSVar(shapeDividerBottomColor) || sectionBackgroundColor;

	// Shape divider band: explicit color only. Omit when unset so the
	// stylesheet's `--wp--preset--color--base` fallback applies.
	const shapeDividerTopBandColor = convertColorToCSSVar(
		shapeDividerTopBackgroundColor
	);
	const shapeDividerBottomBandColor = convertColorToCSSVar(
		shapeDividerBottomBackgroundColor
	);

	// Build className with conditional no-width-constraint and overlay classes
	const className = [
		'dsgo-stack',
		!constrainWidth && 'dsgo-no-width-constraint',
		overlayColor && 'dsgo-stack--has-overlay',
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
				position="top"
				height={shapeDividerTopHeight}
				width={shapeDividerTopWidth}
				flipX={shapeDividerTopFlipX}
				flipY={shapeDividerTopFlipY}
				front={shapeDividerTopFront}
				fillColor={shapeDividerTopFillColor}
				bandColor={shapeDividerTopBandColor}
			/>
			<div {...innerBlocksProps} />
			<ShapeDivider
				shape={shapeDividerBottom}
				position="bottom"
				height={shapeDividerBottomHeight}
				width={shapeDividerBottomWidth}
				flipX={shapeDividerBottomFlipX}
				flipY={shapeDividerBottomFlipY}
				front={shapeDividerBottomFront}
				fillColor={shapeDividerBottomFillColor}
				bandColor={shapeDividerBottomBandColor}
			/>
		</TagName>
	);
}
