import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import { getDeprecatedBlockHTML } from '../../utils/deprecated-block-html';
import { getSafeHotspotUrl } from './utils';

const clampCoordinate = (value) =>
	Math.max(
		0,
		Math.min(100, Number.isFinite(Number(value)) ? Number(value) : 50)
	);

const v1 = {
	apiVersion: 3,
	attributes: metadata.attributes,
	supports: metadata.supports,
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		const needsAccessibleLabel =
			attributes.icon || !attributes.label || attributes.label === '+';

		return (
			needsAccessibleLabel && !innerHTML.includes('aria-label="Hotspot"')
		);
	},
	save({ attributes }) {
		const {
			uniqueId,
			x,
			y,
			originX,
			originY,
			label,
			icon,
			url,
			tooltip,
			tooltipPosition,
			tooltipWidth,
			trigger,
			animation,
			sequenceOrder,
		} = attributes;
		const markerId = `dsgo-hotspot-marker-${uniqueId || 'item'}`;
		const tooltipId = `dsgo-hotspot-tooltip-${uniqueId || 'item'}`;
		const safeUrl = getSafeHotspotUrl(url);
		const markerAccessibleLabel =
			icon && !label ? __('Hotspot', 'designsetgo') : undefined;
		const isLinkedMarker = !!safeUrl;
		const markerProps = {
			className: 'dsgo-hotspot-item__marker',
			id: markerId,
			...(!isLinkedMarker &&
				trigger === 'click' && {
					'aria-expanded': 'false',
					'aria-controls': tooltipId,
				}),
			...(isLinkedMarker || trigger === 'hover'
				? { 'aria-describedby': tooltipId }
				: {}),
			'aria-label': markerAccessibleLabel,
			'data-dsgo-hotspot-marker': 'true',
		};
		const blockProps = useBlockProps.save({
			className: `dsgo-hotspot-item dsgo-hotspot-item--position-${tooltipPosition} dsgo-hotspot-item--animation-${animation} dsgo-hotspot-item--origin-x-${originX} dsgo-hotspot-item--origin-y-${originY}`,
			style: {
				'--dsgo-hotspot-x': `${clampCoordinate(x)}%`,
				'--dsgo-hotspot-y': `${clampCoordinate(y)}%`,
				...(typeof tooltipWidth === 'number' && {
					'--dsgo-hotspot-tooltip-width': `${tooltipWidth}px`,
				}),
				'--dsgo-hotspot-sequence-order': String(sequenceOrder),
				'--dsgo-hotspot-origin-x': originX,
				'--dsgo-hotspot-origin-y': originY,
			},
			'data-dsgo-hotspot-item': 'true',
			'data-dsgo-hotspot-trigger':
				trigger === 'inherit' ? undefined : trigger,
		});
		const markerContent = icon || label || '+';

		return (
			<div {...blockProps}>
				{safeUrl ? (
					<a {...markerProps} href={safeUrl}>
						{markerContent}
					</a>
				) : (
					<button {...markerProps} type="button">
						{markerContent}
					</button>
				)}
				<div
					className="dsgo-hotspot-item__tooltip"
					id={tooltipId}
					role="tooltip"
					data-dsgo-hotspot-tooltip="true"
					hidden
					aria-hidden="true"
				>
					<RichText.Content tagName="span" value={tooltip} />
				</div>
			</div>
		);
	},
	migrate(attributes) {
		return attributes;
	},
};

export { v1 };
export default [v1];
