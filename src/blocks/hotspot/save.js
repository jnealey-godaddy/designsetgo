import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { getSafeHotspotColor } from '../hotspot-item/utils';

export default function HotspotSave({ attributes }) {
	const {
		imageUrl,
		imageAlt,
		trigger,
		tooltipPosition,
		tooltipWidth,
		animation,
		sequenceDuration,
		markerColor,
		markerBackgroundColor,
		tooltipBackgroundColor,
		tooltipTextColor,
	} = attributes;
	const safeMarkerColor = getSafeHotspotColor(markerColor);
	const safeMarkerBackgroundColor = getSafeHotspotColor(
		markerBackgroundColor
	);
	const safeTooltipBackgroundColor = getSafeHotspotColor(
		tooltipBackgroundColor
	);
	const safeTooltipTextColor = getSafeHotspotColor(tooltipTextColor);
	const blockProps = useBlockProps.save({
		className: `dsgo-hotspot dsgo-hotspot--position-${tooltipPosition} dsgo-hotspot--animation-${animation}`,
		style: {
			'--dsgo-hotspot-tooltip-width': `${tooltipWidth}px`,
			'--dsgo-hotspot-sequence-duration': `${sequenceDuration}ms`,
			...(safeMarkerColor && {
				'--dsgo-hotspot-marker-color':
					convertColorToCSSVar(safeMarkerColor),
			}),
			...(safeMarkerBackgroundColor && {
				'--dsgo-hotspot-marker-background': convertColorToCSSVar(
					safeMarkerBackgroundColor
				),
			}),
			...(safeTooltipBackgroundColor && {
				'--dsgo-hotspot-tooltip-background': convertColorToCSSVar(
					safeTooltipBackgroundColor
				),
			}),
			...(safeTooltipTextColor && {
				'--dsgo-hotspot-tooltip-color':
					convertColorToCSSVar(safeTooltipTextColor),
			}),
		},
		'data-dsgo-hotspot': 'true',
		'data-dsgo-hotspot-trigger': trigger,
		'data-dsgo-hotspot-position': tooltipPosition,
		'data-dsgo-hotspot-animation': animation,
	});
	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-hotspot__items',
	});

	return (
		<div {...blockProps}>
			<div className="dsgo-hotspot__image-wrap">
				{imageUrl ? (
					<img
						className="dsgo-hotspot__image"
						src={imageUrl}
						alt={imageAlt}
					/>
				) : (
					<div className="dsgo-hotspot__image dsgo-hotspot__image--empty" />
				)}
				<div {...innerBlocksProps} />
			</div>
		</div>
	);
}
