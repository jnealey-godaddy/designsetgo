/**
 * Grid Span Extension
 *
 * Adds column and row span controls to blocks when they're inside a Grid
 * container. Allows blocks to span multiple columns and/or rows in the grid.
 *
 * @since 1.0.0
 */

import './editor.scss';
import './style.scss';

import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { shouldExtendBlock } from '../../utils/should-extend-block';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';

// Upper bound for row span. Rows are implicit in CSS Grid so any value works;
// we cap at the same max as columns for UI parity and to discourage extreme values.
const MAX_ROW_SPAN = 12;

/**
 * Add columnSpan and rowSpan attributes to all blocks
 * @param {Object} settings Block settings
 * @param {string} name     Block name
 * @return {Object} Modified block settings
 */
function addSpanAttributes(settings, name) {
	// Check user exclusion list first
	if (!shouldExtendBlock(name)) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			dsgoColumnSpan: {
				type: 'number',
				default: 1,
			},
			dsgoRowSpan: {
				type: 'number',
				default: 1,
			},
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/add-grid-span-attributes',
	addSpanAttributes
);

/**
 * Add Column and Row Span controls to block inspector when inside Grid
 */
const withGridSpanControls = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { attributes, setAttributes, clientId } = props;
		const { dsgoColumnSpan, dsgoRowSpan } = attributes;

		// Check if this block is inside a Grid container
		const parentBlock = useSelect(
			(select) => {
				const { getBlockParents, getBlock } =
					select('core/block-editor');
				const parents = getBlockParents(clientId);

				// Check each parent to see if it's a Grid
				for (const parentId of parents) {
					const parent = getBlock(parentId);
					if (parent && parent.name === 'designsetgo/grid') {
						return parent;
					}
				}
				return null;
			},
			[clientId]
		);

		// Get max columns from parent Grid
		const maxColumns = parentBlock?.attributes?.desktopColumns || 12;

		return (
			<>
				<BlockEdit {...props} />
				{parentBlock && (
					<InspectorControls>
						<PanelBody
							title={__('Grid Settings', 'designsetgo')}
							initialOpen={false}
						>
							<RangeControl
								label={__('Column Span', 'designsetgo')}
								value={dsgoColumnSpan}
								onChange={(value) =>
									setAttributes({ dsgoColumnSpan: value })
								}
								min={1}
								max={maxColumns}
								help={__(
									'Number of columns this block spans in the grid',
									'designsetgo'
								)}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<RangeControl
								label={__('Row Span', 'designsetgo')}
								value={dsgoRowSpan}
								onChange={(value) =>
									setAttributes({ dsgoRowSpan: value })
								}
								min={1}
								max={MAX_ROW_SPAN}
								help={__(
									'Number of rows this block spans in the grid',
									'designsetgo'
								)}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</PanelBody>
					</InspectorControls>
				)}
			</>
		);
	};
}, 'withGridSpanControls');

addFilter(
	'editor.BlockEdit',
	'designsetgo/add-grid-span-controls',
	withGridSpanControls,
	20
);

/**
 * Apply column and row span styles in editor via wrapperProps
 *
 * Uses inline styles on the block wrapper instead of dynamic <style> injection.
 * This ensures grid spans work in both the editor canvas AND pattern
 * previews (which render in separate iframes without name="editor-canvas").
 */
const withGridSpanStyles = createHigherOrderComponent((BlockListBlock) => {
	return (props) => {
		const { attributes, clientId } = props;
		const { dsgoColumnSpan, dsgoRowSpan } = attributes;

		// Check if this block is inside a Grid container
		const isInGrid = useSelect(
			(select) => {
				const { getBlockParents, getBlock } =
					select('core/block-editor');
				const parents = getBlockParents(clientId);

				for (const parentId of parents) {
					const parent = getBlock(parentId);
					if (parent && parent.name === 'designsetgo/grid') {
						return true;
					}
				}
				return false;
			},
			[clientId]
		);

		const hasColumnSpan = isInGrid && dsgoColumnSpan && dsgoColumnSpan > 1;
		const hasRowSpan = isInGrid && dsgoRowSpan && dsgoRowSpan > 1;

		if (hasColumnSpan || hasRowSpan) {
			const wrapperProps = {
				...props.wrapperProps,
				style: {
					...props.wrapperProps?.style,
					...(hasColumnSpan && {
						gridColumn: `span ${dsgoColumnSpan}`,
					}),
					...(hasRowSpan && {
						gridRow: `span ${dsgoRowSpan}`,
					}),
				},
			};

			return <BlockListBlock {...props} wrapperProps={wrapperProps} />;
		}

		return <BlockListBlock {...props} />;
	};
}, 'withGridSpanStyles');

addFilter(
	'editor.BlockListBlock',
	'designsetgo/add-grid-span-styles-editor',
	withGridSpanStyles,
	20
);

/**
 * Apply column and row span styles on frontend
 * @param {Object} props      Block props
 * @param {Object} blockType  Block type
 * @param {Object} attributes Block attributes
 * @return {Object} Modified props
 */
function applyGridSpanStyles(props, blockType, attributes) {
	const { dsgoColumnSpan, dsgoRowSpan } = attributes;

	const applyColumn = dsgoColumnSpan && dsgoColumnSpan > 1;
	const applyRow = dsgoRowSpan && dsgoRowSpan > 1;

	if (!applyColumn && !applyRow) {
		return props;
	}

	return {
		...props,
		style: {
			...props.style,
			...(applyColumn && { gridColumn: `span ${dsgoColumnSpan}` }),
			...(applyRow && { gridRow: `span ${dsgoRowSpan}` }),
		},
	};
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/apply-grid-span-styles',
	applyGridSpanStyles
);
