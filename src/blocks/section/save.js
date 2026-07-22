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
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from './utils/has-overlay-style';
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
		constrainWidth,
		contentWidth,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		overlayColor,
		// Shape divider attributes
		shapeDividerTop,
		shapeDividerTopBackgroundColor,
		shapeDividerTopHeight,
		shapeDividerTopWidth,
		shapeDividerTopFlipX,
		shapeDividerTopFlipY,
		shapeDividerTopFront,
		shapeDividerTopSpacing,
		shapeDividerBottom,
		shapeDividerBottomBackgroundColor,
		shapeDividerBottomHeight,
		shapeDividerBottomWidth,
		shapeDividerBottomFlipX,
		shapeDividerBottomFlipY,
		shapeDividerBottomFront,
		shapeDividerBottomSpacing,
	} = attributes;

	// Shape divider band: explicit color only. Omit when unset so the
	// stylesheet's `--wp--preset--color--base` fallback applies. The shape
	// region itself has no fill — it is transparent and reveals the section's
	// own background (solid, gradient, or image) through the mask knockout.
	const shapeDividerTopBandColor = convertColorToCSSVar(
		shapeDividerTopBackgroundColor
	);
	const shapeDividerBottomBandColor = convertColorToCSSVar(
		shapeDividerBottomBackgroundColor
	);

	// Overlay is enabled by an explicit overlayColor OR by a style-kit overlay
	// variation (is-style-overlay-*) applied via className. In the variation
	// case the color is supplied by the variation's stylesheet, so no inline
	// --dsgo-overlay-color is emitted below.
	const hasOverlay =
		!!overlayColor || hasOverlayStyleClass(attributes.className);

	// Build className with conditional no-width-constraint and overlay classes.
	// Hover activation classes are emitted for hover style variations so their
	// class-gated CSS can activate (the inline-`style` gate can't see a variation
	// stylesheet's vars). The inline-attribute hover path keeps its own gate.
	const className = [
		'dsgo-stack',
		!constrainWidth && 'dsgo-no-width-constraint',
		hasOverlay && 'dsgo-stack--has-overlay',
		(shapeDividerTop || shapeDividerBottom) &&
			'dsgo-stack--has-shape-divider',
		...hoverVariationClasses(attributes.className),
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
			// Default content clearance: expose the divider height on the
			// wrapper so the stylesheet fallback (see _shape-divider.scss)
			// reserves inner padding that MATCHES the divider height instead of
			// a flat default. Omitted at the default 100px (the stylesheet's own
			// fallback covers it) and when an explicit "Content Clearance"
			// spacing is set (its inline padding on the inner wins). Must match
			// edit.js EXACTLY.
			...(shapeDividerTop &&
				!shapeDividerTopSpacing &&
				(shapeDividerTopHeight || 100) !== 100 && {
					'--dsgo-shape-clearance-top': `${shapeDividerTopHeight}px`,
				}),
			...(shapeDividerBottom &&
				!shapeDividerBottomSpacing &&
				(shapeDividerBottomHeight || 100) !== 100 && {
					'--dsgo-shape-clearance-bottom': `${shapeDividerBottomHeight}px`,
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

	// Inner content clearance for shape dividers. The value is a block-user
	// defined WordPress spacing token (var:preset|spacing|NN) or a raw CSS
	// length; serialize exactly what was set and emit nothing when unset.
	// Must match edit.js EXACTLY.
	if (shapeDividerTop && shapeDividerTopSpacing) {
		innerStyle.paddingTop = convertPresetToCSSVar(shapeDividerTopSpacing);
	}
	if (shapeDividerBottom && shapeDividerBottomSpacing) {
		innerStyle.paddingBottom = convertPresetToCSSVar(
			shapeDividerBottomSpacing
		);
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
				bandColor={shapeDividerBottomBandColor}
			/>
		</TagName>
	);
}
