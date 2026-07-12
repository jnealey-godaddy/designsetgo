/**
 * WordPress dependencies
 */
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

/**
 * Save component for the Scroll Accordion block.
 * Simple container that outputs inner blocks.
 *
 * @param {Object} props            Component props
 * @param {Object} props.attributes Block attributes
 * @return {Element} Element to render.
 */
export default function Save({ attributes }) {
	const { alignItems } = attributes;

	// Only the author-controlled cross-axis alignment is serialized. The
	// constant layout (`width:100%; align-self:stretch` on the root,
	// `display:flex; flex-direction:column` on the items) is declared verbatim
	// in style.scss — baking it into every saved block added nothing and made it
	// unthemeable, since an inline style beats any stylesheet rule.
	const innerStyles = {
		alignItems: alignItems || 'flex-start',
	};

	const blockProps = useBlockProps.save({
		className: 'dsgo-scroll-accordion',
	});

	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-scroll-accordion__items',
		style: innerStyles,
	});

	return (
		<div {...blockProps}>
			<div {...innerBlocksProps} />
		</div>
	);
}
