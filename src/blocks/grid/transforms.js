/**
 * DSG Grid Block - Transforms
 *
 * Allows transforming to/from DSG Section, DSG Row, core Columns, and legacy
 * Container blocks.
 *
 * @since 1.0.0
 */

import { createBlock } from '@wordpress/blocks';

// Column styling that has to survive the trip into a grid cell.
const COLUMN_STYLE_KEYS = [
	'style',
	'backgroundColor',
	'textColor',
	'gradient',
	'className',
];

/**
 * Turns a core/column into one grid cell. A plain column holding a single
 * block becomes that block; anything else is wrapped in a Group so the
 * column's blocks stay together and keep its colors and spacing.
 *
 * @param {Object} column core/column block.
 * @return {Object} Block to place in the grid.
 */
const columnToCell = (column) => {
	const kept = Object.fromEntries(
		COLUMN_STYLE_KEYS.filter((key) => column.attributes[key]).map((key) => [
			key,
			column.attributes[key],
		])
	);

	if (column.innerBlocks.length === 1 && !Object.keys(kept).length) {
		return column.innerBlocks[0];
	}

	return createBlock('core/group', kept, column.innerBlocks);
};

const transforms = {
	from: [
		{
			type: 'block',
			blocks: ['designsetgo/section'],
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/grid',
					{
						// Preserve all attributes including layout
						...attributes,
						// Set Grid-specific defaults
						rowGap: '',
						columnGap: '',
						desktopColumns: 3,
						tabletColumns: 2,
						mobileColumns: 1,
						alignItems: 'start',
					},
					innerBlocks
				);
			},
		},
		{
			type: 'block',
			blocks: ['designsetgo/row'],
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/grid',
					{
						// Preserve all attributes including layout
						...attributes,
						// Remove Row-specific attributes
						mobileStack: undefined,
						// Set Grid-specific defaults
						rowGap: '',
						columnGap: '',
						desktopColumns: 3,
						tabletColumns: 2,
						mobileColumns: 1,
						alignItems: 'start',
					},
					innerBlocks
				);
			},
		},
		{
			type: 'block',
			blocks: ['designsetgo/container'],
			isMatch: (attributes) => {
				// Only allow transforming grid layout type
				return attributes.layoutType === 'grid';
			},
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/grid',
					{
						rowGap: '',
						columnGap: '',
						constrainWidth: attributes.constrainWidth,
						contentWidth: attributes.contentWidth,
						desktopColumns: 3,
						tabletColumns: 2,
						mobileColumns: 1,
						alignItems: 'start',
						// Note: gap is handled by WordPress blockGap (in style.spacing.blockGap)
					},
					innerBlocks
				);
			},
		},
		{
			type: 'block',
			blocks: ['core/columns'],
			transform: ({ align, anchor }, innerBlocks) => {
				const count = Math.min(Math.max(innerBlocks.length, 1), 12);
				return createBlock(
					'designsetgo/grid',
					{
						// Undefined keeps Grid's own full-width default.
						...(align && { align }),
						...(anchor && { anchor }),
						desktopColumns: count,
						tabletColumns: Math.min(count, 2),
						mobileColumns: 1,
					},
					innerBlocks.map(columnToCell)
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
		{
			type: 'block',
			blocks: ['designsetgo/row'],
			transform: (attributes, innerBlocks) => {
				return createBlock(
					'designsetgo/row',
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
						// Set Row-specific defaults
						mobileStack: false,
					},
					innerBlocks
				);
			},
		},
		{
			type: 'block',
			blocks: ['core/group'],
			transform: (attributes, innerBlocks) => {
				const {
					align,
					tagName,
					desktopColumns,
					style,
					anchor,
					backgroundColor,
					textColor,
					fontSize,
				} = attributes;

				// Map DSG grid to core/group grid layout
				// Requires WordPress 6.5+ for grid layout support.
				// On older versions, core/group falls back to flow layout.
				const layout = {
					type: 'grid',
					columnCount: desktopColumns || 3,
				};

				// Note: DSG-specific features not available in core/group:
				// - tabletColumns, mobileColumns (responsive column counts)
				// - rowGap, columnGap (custom gaps; blockGap transfers via style)
				// - alignItems, textAlign (grid item alignment)
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
		{
			type: 'block',
			blocks: ['core/columns'],
			// Every cell becomes a column in one row, so the responsive
			// column counts are dropped; core Columns stack on mobile.
			transform: ({ align, anchor }, innerBlocks) =>
				createBlock(
					'core/columns',
					{
						...(align && { align }),
						...(anchor && { anchor }),
					},
					innerBlocks.map((cell) =>
						createBlock('core/column', {}, [cell])
					)
				),
		},
	],
};

export default transforms;
