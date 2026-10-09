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
import { getOverlayOpacity } from '../../utils/overlay-opacity';
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from '../../utils/style-variation-classes';
import { getGridTemplateColumns } from './grid-columns';
import metadata from './block.json';

/**
 * Grid Container Save Component
 *
 * @param {Object} props            Component props
 * @param {Object} props.attributes Block attributes
 * @return {JSX.Element} Save component
 */
function saveWithoutResponsiveTemplates({ attributes }) {
	const {
		tagName = 'div',
		constrainWidth,
		contentWidth,
		columnMinWidth,
		columnTemplate,
		desktopColumns,
		tabletColumns,
		mobileColumns,
		rowGap,
		columnGap,
		alignItems,
		matchRowHeights,
		overlayColor,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		style,
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
		'dsgo-grid',
		`dsgo-grid-cols-${desktopColumns}`,
		`dsgo-grid-cols-tablet-${tabletColumns}`,
		`dsgo-grid-cols-mobile-${mobileColumns}`,
		!constrainWidth && 'dsgo-no-width-constraint',
		matchRowHeights && 'dsgo-grid--match-rows',
		hasOverlay && 'dsgo-grid--has-overlay',
		...hoverVariationClasses(attributes.className, 'dsgo-grid'),
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
				'--dsgo-overlay-opacity': getOverlayOpacity(
					overlayColor,
					attributes.overlayOpacity
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
	const resolvedColumnGap = blockGapColumn || columnGap || defaultGap;

	const innerStyles = {
		display: 'grid',
		gridTemplateColumns: getGridTemplateColumns(
			columnMinWidth,
			desktopColumns,
			resolvedColumnGap,
			columnTemplate
		),
		alignItems: alignItems || 'stretch',
		rowGap: blockGapRow || rowGap || defaultGap,
		columnGap: resolvedColumnGap,
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

const { tabletColumnTemplate, mobileColumnTemplate, ...legacyAttributes } =
	metadata.attributes;

/**
 * Optional templates do not change old markup. Their absence cannot distinguish
 * old content from a modern block using defaults, so never eagerly migrate a
 * valid block; WordPress may still use this snapshot when validation fails.
 */
export const responsiveTemplateCompatibility = {
	apiVersion: 3,
	attributes: legacyAttributes,
	supports: metadata.supports,
	isEligible() {
		return false;
	},
	save: saveWithoutResponsiveTemplates,
	migrate(attributes) {
		return attributes;
	},
};

/**
 * Every historical migration must reach the current optional attribute schema.
 * Preserve named entry identities because existing ordering tests use them.
 *
 * @param {Object[]} entries Deprecation snapshots.
 * @return {Object[]} The same snapshots with current defaults in migrate().
 */
export function withResponsiveTemplateDefaults(entries) {
	entries.forEach((entry) => {
		const migrate = entry.migrate;
		entry.migrate = (attributes, innerBlocks) => {
			const result = migrate
				? migrate(attributes, innerBlocks)
				: attributes;
			const tuple = Array.isArray(result);
			const migrated = tuple ? result[0] : result;
			const current = {
				tabletColumnTemplate: '',
				mobileColumnTemplate: '',
				...migrated,
			};
			return tuple ? [current, result[1]] : current;
		};
	});
	return entries;
}
