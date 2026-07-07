/**
 * Row Block - Save Component
 *
 * Saves the block content with minimal custom styles.
 * WordPress's layout system handles flex layout through CSS classes.
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from '../../utils/style-variation-classes';

/**
 * Convert WordPress vertical alignment value to CSS align-items value
 * WordPress stores: stretch, center, top, bottom, space-between
 * CSS align-items needs: stretch, center, flex-start, flex-end, space-between
 *
 * @param {string} value The WordPress vertical alignment value
 * @return {string} CSS align-items value
 */
function getAlignItemsValue(value) {
	if (!value) {
		return undefined;
	}

	const alignMap = {
		stretch: 'stretch',
		center: 'center',
		top: 'flex-start',
		bottom: 'flex-end',
		'space-between': 'space-between',
	};

	return alignMap[value];
}

/**
 * Row Container Save Component
 *
 * @param {Object} props            Component props
 * @param {Object} props.attributes Block attributes
 * @return {JSX.Element} Save component
 */
export default function RowSave({ attributes }) {
	const {
		tagName = 'div',
		constrainWidth,
		contentWidth,
		overlayColor,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		mobileStack,
		layout,
	} = attributes;

	// Overlay is enabled by an explicit overlayColor OR by a style-kit overlay
	// variation (is-style-overlay-*) applied via className. In the variation
	// case the color is supplied by the variation's stylesheet, so no inline
	// --dsgo-overlay-color is emitted below.
	const hasOverlay =
		!!overlayColor || hasOverlayStyleClass(attributes.className);

	// Build className with conditional classes. Hover activation classes are
	// emitted for hover style variations so their class-gated CSS can activate
	// (the inline-`style` gate can't see a variation stylesheet's vars).
	const className = [
		'dsgo-flex',
		mobileStack && 'dsgo-flex--mobile-stack',
		!constrainWidth && 'dsgo-no-width-constraint',
		hasOverlay && 'dsgo-flex--has-overlay',
		...hoverVariationClasses(attributes.className, 'dsgo-flex'),
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

	// Extract gap AFTER creating blockProps, so we can move it to inner div instead
	// WordPress layout support stores gap in attributes.style.spacing.blockGap
	// Convert from WordPress preset format (var:preset|spacing|md) to CSS var (var(--wp--preset--spacing--md))
	const rawGapValue = attributes.style?.spacing?.blockGap;
	const gapValue = convertPresetToCSSVar(rawGapValue);

	// Remove gap from outer div's inline styles - it should only be on inner div
	// This prevents WordPress from applying gap to the wrong element
	if (blockProps.style?.gap) {
		delete blockProps.style.gap;
	}

	// Inner container props with flex layout and width constraints
	// CRITICAL: Apply display: flex here, not via WordPress layout support on outer div
	// This ensures flex layout is applied to the element that contains the flex children
	const alignItems = getAlignItemsValue(layout?.verticalAlignment);
	const innerStyle = {
		display: 'flex',
		// Apply layout justifyContent to inner div where flex children are
		justifyContent: layout?.justifyContent || 'left',
		// Apply vertical alignment (align-items) from layout support
		...(alignItems && { alignItems }),
		// Apply flex-wrap from layout support
		// Fallback must match block.json supports.layout.default.flexWrap ("nowrap").
		// Using "wrap" here caused block-level children (which default to 100% width)
		// to wrap onto their own lines and appear stacked on fresh rows where
		// attributes.layout is not yet written.
		flexWrap: layout?.flexWrap || 'nowrap',
		// Apply gap from blockProps or attributes
		...(gapValue && { gap: gapValue }),
	};

	// Apply width constraints if enabled
	// Use custom contentWidth if set, otherwise fallback to theme's contentSize via CSS variable
	if (constrainWidth) {
		innerStyle.maxWidth =
			contentWidth || 'var(--wp--style--global--content-size, 1140px)';
		innerStyle.marginLeft = 'auto';
		innerStyle.marginRight = 'auto';
	}

	// Merge inner blocks props
	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-flex__inner',
		style: innerStyle,
	});

	return (
		<TagName {...blockProps}>
			<div {...innerBlocksProps} />
		</TagName>
	);
}
