/**
 * WordPress dependencies
 */
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { getOverlayOpacity } from '../../utils/overlay-opacity';

/**
 * Save component for the Scroll Accordion Item block.
 * Simple container that outputs inner blocks.
 *
 * @param {Object} props            - Component props.
 * @param {Object} props.attributes - Block attributes.
 * @return {Element} Element to render.
 */
export default function Save({ attributes }) {
	const { overlayColor } = attributes;

	// Apply overlay styles when color is set
	const overlayStyles = overlayColor
		? {
				'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
				'--dsgo-overlay-opacity': getOverlayOpacity(overlayColor),
			}
		: {};

	const blockProps = useBlockProps.save({
		className: classnames('dsgo-scroll-accordion-item', {
			'dsgo-scroll-accordion-item--has-overlay': !!overlayColor,
		}),
		style: overlayColor ? overlayStyles : undefined,
	});

	const innerBlocksProps = useInnerBlocksProps.save(blockProps);

	return <div {...innerBlocksProps} />;
}
