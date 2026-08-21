import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import { useUniqueBlockId } from '../../hooks';
import HotspotItemInspector from './components/HotspotItemInspector';

const clampCoordinate = (value) =>
	Math.max(0, Math.min(100, Number(value) || 0));

export default function HotspotItemEdit({
	attributes,
	setAttributes,
	clientId,
	context,
}) {
	const [isTooltipOpen, setTooltipOpen] = useState(false);
	const {
		uniqueId,
		x,
		y,
		label,
		icon,
		tooltip,
		tooltipPosition,
		tooltipWidth,
		trigger,
		animation,
		sequenceOrder,
		originX,
		originY,
	} = attributes;
	useUniqueBlockId({
		clientId,
		attributeName: 'uniqueId',
		value: uniqueId,
		setAttributes,
		prefix: 'hotspot-',
	});
	const effectivePosition =
		tooltipPosition === 'inherit'
			? context['designsetgo/hotspot/tooltipPosition'] || 'top'
			: tooltipPosition;
	const effectiveWidth =
		typeof tooltipWidth === 'number'
			? tooltipWidth
			: context['designsetgo/hotspot/tooltipWidth'] || 240;
	const effectiveAnimation =
		animation === 'inherit'
			? context['designsetgo/hotspot/animation'] || 'none'
			: animation;
	const effectiveTrigger =
		trigger === 'inherit'
			? context['designsetgo/hotspot/trigger'] || 'click'
			: trigger;
	const blockProps = useBlockProps({
		className: `dsgo-hotspot-item dsgo-hotspot-item--position-${effectivePosition} dsgo-hotspot-item--animation-${effectiveAnimation} dsgo-hotspot-item--origin-x-${originX} dsgo-hotspot-item--origin-y-${originY}`,
		style: {
			'--dsgo-hotspot-x': `${clampCoordinate(x)}%`,
			'--dsgo-hotspot-y': `${clampCoordinate(y)}%`,
			'--dsgo-hotspot-tooltip-width': `${effectiveWidth}px`,
			'--dsgo-hotspot-sequence-order': String(sequenceOrder),
			'--dsgo-hotspot-origin-x': originX,
			'--dsgo-hotspot-origin-y': originY,
		},
		'data-dsgo-hotspot-item-editor': clientId,
		'data-dsgo-hotspot-trigger': effectiveTrigger,
	});

	return (
		<>
			<InspectorControls>
				<HotspotItemInspector
					attributes={attributes}
					setAttributes={setAttributes}
					clientId={clientId}
				/>
			</InspectorControls>
			<div {...blockProps}>
				<button
					className="dsgo-hotspot-item__marker"
					type="button"
					aria-label={label || __('Hotspot', 'designsetgo')}
					aria-expanded={isTooltipOpen}
					onClick={(event) => {
						event.preventDefault();
						setTooltipOpen((isOpen) => !isOpen);
					}}
				>
					{icon || label || '+'}
				</button>
				<RichText
					tagName="span"
					className={`dsgo-hotspot-item__tooltip${
						isTooltipOpen ? ' is-open' : ''
					}`}
					value={tooltip}
					onChange={(value) => setAttributes({ tooltip: value })}
					placeholder={__('Describe this hotspot…', 'designsetgo')}
				/>
			</div>
		</>
	);
}
