/**
 * DSG Grid Block - Transforms
 *
 * Allows transforming to/from DSG Section, DSG Row, core Columns, and legacy
 * Container blocks.
 *
 * @since 1.0.0
 */

import { createBlock } from '@wordpress/blocks';
import { transformLayout } from '../../utils/transform-layout';
import { pickColors } from '../../utils/pick-attributes';

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

// Grid and Columns both support colour, border, padding and margin.
const GRID_STYLE = { border: true, padding: true, margin: true };

/**
 * A core/columns block's colours and spacing, in Grid's shape.
 *
 * Columns stores its gap per axis (`{ top, left }`); Grid takes one value,
 * so the column gap is kept.
 *
 * @param {Object} attributes core/columns attributes.
 * @return {Object} Attributes to spread into the Grid.
 */
const columnsStyleToGrid = (attributes) => {
	const picked = pickColors(attributes, GRID_STYLE);
	const gap = attributes.style?.spacing?.blockGap;
	const blockGap =
		gap && typeof gap === 'object' ? (gap.left ?? gap.top) : gap;
	if (blockGap) {
		picked.style = {
			...picked.style,
			spacing: { ...picked.style?.spacing, blockGap },
		};
	}
	return picked;
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
						// Preserve all attributes
						...attributes,
						// Orientation belongs to the target block; see transformLayout().
						layout: transformLayout(attributes.layout, null),
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
						// Preserve all attributes
						...attributes,
						// Orientation belongs to the target block; see transformLayout().
						layout: transformLayout(attributes.layout, null),
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
			transform: (attributes, innerBlocks) => {
				const { align, anchor } = attributes;
				const count = Math.min(Math.max(innerBlocks.length, 1), 12);
				return createBlock(
					'designsetgo/grid',
					{
						// The Columns' own colours, border and spacing. Grid has
						// a default style; only replace it when Columns had one.
						...columnsStyleToGrid(attributes),
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
						// Preserve all attributes
						...attributes,
						// Orientation belongs to the target block; see transformLayout().
						layout: transformLayout(attributes.layout, 'vertical'),
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
			transform: (attributes, innerBlocks) =>
				createBlock(
					'core/columns',
					{
						...pickColors(attributes, GRID_STYLE),
						...(attributes.align && { align: attributes.align }),
						...(attributes.anchor && { anchor: attributes.anchor }),
					},
					innerBlocks.map((cell) =>
						createBlock('core/column', {}, [cell])
					)
				),
		},
	],
};

export default transforms;
