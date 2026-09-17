/**
 * Section Block - Transforms
 *
 * Allows transforming to/from Row, Grid, and legacy Stack/Flex blocks.
 *
 * @since 1.0.0
 */

import { createBlock } from '@wordpress/blocks';

const transforms = {
	from: [
		{
			type: 'block',
			blocks: ['designsetgo/stack'],
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/section',
					{
						// Transfer all attributes from legacy Stack block
						...attributes,
					},
					innerBlocks
				);
			},
		},
		{
			type: 'block',
			blocks: ['designsetgo/flex', 'designsetgo/row'],
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/section',
					{
						// Preserve all attributes including layout
						...attributes,
						// Remove mobileStack (Flex/Row-specific)
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
					'designsetgo/section',
					{
						// Preserve all attributes including layout
						...attributes,
						// Remove Grid-specific attributes
						desktopColumns: undefined,
						tabletColumns: undefined,
						mobileColumns: undefined,
						rowGap: undefined,
						columnGap: undefined,
						alignItems: undefined,
						textAlign: undefined,
					},
					innerBlocks
				);
			},
		},
	],
	to: [
		{
			type: 'block',
			blocks: ['designsetgo/row'],
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/row',
					{
						// Preserve all attributes including layout
						...attributes,
						// mobileStack defaults to false
						mobileStack: false,
						// Row has no outer box width of its own, and is in the
						// max-width extension's EXCLUDED_BLOCKS, so there is
						// nothing to map `boxWidth` onto. Dropped explicitly so
						// the loss is deliberate and greppable rather than an
						// unregistered attribute quietly disappearing.
						boxWidth: undefined,
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
						// Preserve all attributes including layout
						...attributes,
						// See the Row transform: Grid has no outer box width
						// and is excluded from the max-width extension too.
						boxWidth: undefined,
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
			// Prevent transform when shape dividers or overlay are active,
			// since these are major visual features with no core equivalent.
			// Users should remove these features first, then transform.
			isMatch: (attributes) => {
				return (
					!attributes.shapeDividerTop &&
					!attributes.shapeDividerBottom &&
					!attributes.overlayColor
				);
			},
			transform: (attributes, innerBlocks) => {
				const {
					align,
					tagName,
					constrainWidth,
					contentWidth,
					boxWidth,
					style,
					anchor,
					backgroundColor,
					textColor,
					fontSize,
				} = attributes;

				// Map constrainWidth to core/group layout type
				// constrained = content centered and limited to contentSize
				// default = standard flow layout (no width constraint)
				const layout =
					constrainWidth === true
						? {
								type: 'constrained',
								...(contentWidth && {
									contentSize: contentWidth,
								}),
							}
						: { type: 'default' };

				// Note: DSG-specific features not available in core/group:
				// - hoverBackgroundColor, hoverTextColor (hover effects)
				// - hoverIconBackgroundColor, hoverButtonBackgroundColor (child context)

				// `boxWidth` caps the OUTER box. core/group has no such
				// control, but unlike Section it is NOT in the max-width
				// extension's EXCLUDED_BLOCKS, so `dsgoMaxWidth` is the
				// faithful equivalent: same max-width plus auto margins on the
				// group's own wrapper. Without this the painted box silently
				// widens back to full on transform. If a site has excluded
				// core/group from DSG extensions the attribute is simply
				// dropped, which is what happens today either way.
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
						...(boxWidth && { dsgoMaxWidth: boxWidth }),
					},
					innerBlocks
				);
			},
		},
	],
};

export default transforms;
