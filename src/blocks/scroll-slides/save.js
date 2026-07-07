/**
 * WordPress dependencies
 */
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { overlayOpacityFraction } from '../../utils/overlay-opacity';

export default function Save({ attributes }) {
	const {
		minHeight,
		maxHeight,
		constrainWidth,
		contentWidth,
		overlayColor,
		overlayOpacity,
		navColor,
		navActiveColor,
	} = attributes;

	const className = [
		'dsgo-scroll-slides',
		overlayColor && 'dsgo-scroll-slides--has-overlay',
		!constrainWidth && 'dsgo-scroll-slides--no-width-constraint',
		(navColor || navActiveColor) && 'dsgo-scroll-slides--has-nav-color',
	]
		.filter(Boolean)
		.join(' ');

	const blockProps = useBlockProps.save({
		className,
		'data-dsgo-min-height': minHeight || '100vh',
		...(maxHeight && { 'data-dsgo-max-height': maxHeight }),
		style: {
			...(overlayColor && {
				'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
				'--dsgo-overlay-opacity': String(
					overlayOpacityFraction(overlayOpacity)
				),
			}),
			...(navColor && {
				'--dsgo-nav-color': convertColorToCSSVar(navColor),
			}),
			...(navActiveColor && {
				'--dsgo-nav-active-color': convertColorToCSSVar(navActiveColor),
			}),
		},
	});

	const innerStyle = {};
	if (constrainWidth) {
		innerStyle.maxWidth =
			contentWidth || 'var(--wp--style--global--content-size, 1140px)';
		innerStyle.marginLeft = 'auto';
		innerStyle.marginRight = 'auto';
	}

	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-scroll-slides__panels',
	});

	return (
		<div {...blockProps}>
			<div className="dsgo-scroll-slides__inner" style={innerStyle}>
				<div {...innerBlocksProps} />
			</div>
		</div>
	);
}
