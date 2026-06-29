/**
 * Icon List Block - Save Component
 *
 * Renders the frontend output for the icon list.
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

/**
 * Icon List Save Component
 *
 * @param {Object} props            - Component props
 * @param {Object} props.attributes - Block attributes
 * @return {JSX.Element} Icon List save component
 */
export default function IconListSave({ attributes }) {
	const { layout, gap, columns, columnMinWidth, alignment } = attributes;

	// Calculate alignment value to avoid nested ternary (must match edit.js)
	let alignItemsValue;
	let justifyContentValue;

	if (layout === 'vertical') {
		// For vertical layout, alignItems controls horizontal alignment
		if (alignment === 'center') {
			alignItemsValue = 'center';
		} else if (alignment === 'right') {
			alignItemsValue = 'flex-end';
		} else {
			alignItemsValue = 'flex-start';
		}
	} else if (layout === 'horizontal') {
		// For horizontal layout, justifyContent controls horizontal distribution
		if (alignment === 'center') {
			justifyContentValue = 'center';
		} else if (alignment === 'right') {
			justifyContentValue = 'flex-end';
		} else {
			justifyContentValue = 'flex-start';
		}
	}

	// Determine flex direction based on layout (must match edit.js)
	let flexDirection;
	if (layout === 'vertical') {
		flexDirection = 'column';
	} else if (layout === 'horizontal') {
		flexDirection = 'row';
	}

	// Grid columns: when a min width is set, auto-fit responsively (wraps,
	// never overflows thanks to the min(100%, …) clamp); otherwise a fixed
	// column count. Computed ahead of the object to avoid a nested ternary.
	let gridTemplateColumns;
	if (layout === 'grid') {
		gridTemplateColumns = columnMinWidth
			? `repeat(auto-fit, minmax(min(100%, ${columnMinWidth}), 1fr))`
			: `repeat(${columns}, 1fr)`;
	}

	// Calculate container styles (must match edit.js)
	const containerStyles = {
		display: layout === 'grid' ? 'grid' : 'flex',
		flexDirection,
		gridTemplateColumns,
		gap,
		alignItems: alignItemsValue,
		justifyContent: justifyContentValue,
		width: '100%', // Ensure container fills available space
	};

	// Get block wrapper props
	const blockProps = useBlockProps.save({
		className: `dsgo-icon-list dsgo-icon-list--${layout}`,
		style: { width: '100%' }, // Ensure block fills parent width
	});

	// Get inner blocks props
	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-icon-list__items',
		style: containerStyles,
	});

	return (
		<div {...blockProps}>
			<div {...innerBlocksProps} />
		</div>
	);
}
