/**
 * Row Block - Transforms
 *
 * Allows transforming to/from Section, Grid, and legacy Stack/Flex blocks.
 *
 * @since 1.0.0
 */

import { createBlock } from '@wordpress/blocks';
import { transformLayout } from '../../utils/transform-layout';

const transforms = {
	from: [
		{
			type: 'block',
			blocks: ['designsetgo/flex'],
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/row',
					{
						// Transfer all attributes from legacy Flex block
						...attributes,
						// Orientation belongs to the target block; see transformLayout().
						layout: transformLayout(
							attributes.layout,
							'horizontal'
						),
					},
					innerBlocks
				);
			},
		},
		{
			type: 'block',
			blocks: ['designsetgo/stack', 'designsetgo/section'],
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/row',
					{
						// Preserve all attributes
						...attributes,
						// Orientation belongs to the target block; see transformLayout().
						layout: transformLayout(
							attributes.layout,
							'horizontal'
						),
						// mobileStack doesn't exist in Stack/Section, default to false
						mobileStack: false,
					},
					innerBlocks
				);
			},
		},
		{
			type: 'block',
			blocks: ['designsetgo/grid'],
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/row',
					{
						// Preserve all attributes
						...attributes,
						// Orientation belongs to the target block; see transformLayout().
						layout: transformLayout(
							attributes.layout,
							'horizontal'
						),
						// Remove Grid-specific attributes
						desktopColumns: undefined,
						tabletColumns: undefined,
						mobileColumns: undefined,
						rowGap: undefined,
						columnGap: undefined,
						alignItems: undefined,
						textAlign: undefined,
						// mobileStack defaults to false
						mobileStack: false,
					},
					innerBlocks
				);
			},
		},
	],
	to: [
		{
			type: 'block',
			blocks: ['designsetgo/section'],
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/section',
					{
						// Preserve all attributes
						...attributes,
						// Orientation belongs to the target block; see transformLayout().
						layout: transformLayout(attributes.layout, 'vertical'),
						// Remove mobileStack (Row-specific)
						mobileStack: undefined,
					},
					innerBlocks
				);
			},
		},
		{
			type: 'block',
			blocks: ['designsetgo/grid'],
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/grid',
					{
						// Preserve all attributes
						...attributes,
						// Orientation belongs to the target block; see transformLayout().
						layout: transformLayout(attributes.layout, null),
						// Remove mobileStack (Row-specific)
						mobileStack: undefined,
						// Set Grid-specific defaults
						desktopColumns: 3,
						tabletColumns: 2,
						mobileColumns: 1,
						rowGap: '',
						columnGap: '',
						alignItems: 'start',
					},
					innerBlocks
				);
			},
		},
		{
			type: 'block',
			blocks: ['core/group'],
			// Prevent transform when overlay is active, since it has
			// no core equivalent and would silently break the design.
			isMatch: (attributes) => {
				return !attributes.overlayColor;
			},
			transform: (attributes, innerBlocks) => {
				const {
					align,
					tagName,
					style,
					layout: dsgLayout,
					anchor,
					backgroundColor,
					textColor,
					fontSize,
				} = attributes;

				// Map DSG row layout to core/group flex layout
				// Note: layout is stored in block attributes via WP layout support
				const layout = {
					type: 'flex',
					flexWrap: dsgLayout?.flexWrap || 'wrap',
					...(dsgLayout?.justifyContent && {
						justifyContent: dsgLayout.justifyContent,
					}),
					...(dsgLayout?.verticalAlignment && {
						verticalAlignment: dsgLayout.verticalAlignment,
					}),
				};

				// Note: DSG-specific features not available in core/group:
				// - mobileStack (responsive stacking on mobile)
				// - constrainWidth/contentWidth (inner width constraints)
				// - hoverBackgroundColor, hoverTextColor (hover effects)
				// - hoverIconBackgroundColor, hoverButtonBackgroundColor (child context)

				return createBlock(
					'core/group',
					{
						align,
						tagName: tagName || 'div',
						layout,
						style,
						...(anchor && { anchor }),
						...(backgroundColor && { backgroundColor }),
						...(textColor && { textColor }),
						...(fontSize && { fontSize }),
					},
					innerBlocks
				);
			},
		},
	],
};

export default transforms;
