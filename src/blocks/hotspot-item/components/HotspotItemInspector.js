import { __ } from '@wordpress/i18n';
import {
	RangeControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared/DsgoInspectorPanel';
import { getSafeHotspotUrl } from '../utils';

const INHERIT_OPTIONS = [
	{ label: __('Inherit from Hotspot', 'designsetgo'), value: 'inherit' },
];

export default function HotspotItemInspector({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		label,
		icon,
		url,
		tooltipPosition,
		tooltipWidth,
		trigger,
		animation,
		sequenceOrder,
		originX,
		originY,
	} = attributes;
	return (
		<>
			<DsgoInspectorPanel
				title={__('Settings', 'designsetgo')}
				panelName="settings"
				panelId={clientId}
				resetAll={() =>
					setAttributes({
						label: '+',
						icon: '',
						url: '',
						tooltipPosition: 'inherit',
						tooltipWidth: undefined,
						trigger: 'inherit',
					})
				}
			>
				<DsgoInspectorPanel.Item
					label={__('Label', 'designsetgo')}
					hasValue={() => label !== '+'}
					onDeselect={() => setAttributes({ label: '+' })}
					isShownByDefault
				>
					<TextControl
						label={__('Label', 'designsetgo')}
						value={label}
						onChange={(value) => setAttributes({ label: value })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Icon text', 'designsetgo')}
					hasValue={() => !!icon}
					onDeselect={() => setAttributes({ icon: '' })}
					isShownByDefault
				>
					<TextControl
						label={__('Icon text', 'designsetgo')}
						value={icon}
						onChange={(value) => setAttributes({ icon: value })}
						help={__(
							'Optional symbol shown instead of the label.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Link', 'designsetgo')}
					hasValue={() => !!url}
					onDeselect={() => setAttributes({ url: '' })}
					isShownByDefault
				>
					<TextControl
						type="url"
						label={__('Link', 'designsetgo')}
						value={url}
						// Store the raw value. Sanitizing per keystroke wipes
						// the field at the intermediate `http:` and `http://`
						// states, which makes an http link untypable by hand.
						// `save()` applies getSafeHotspotUrl at render time, so
						// an unusable value is never emitted as markup.
						onChange={(value) => setAttributes({ url: value })}
						help={
							url && !getSafeHotspotUrl(url)
								? __(
										'Only https, http, mailto, and tel links are used. This link will be ignored.',
										'designsetgo'
									)
								: undefined
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Trigger override', 'designsetgo')}
					hasValue={() => trigger !== 'inherit'}
					onDeselect={() => setAttributes({ trigger: 'inherit' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Trigger override', 'designsetgo')}
						value={trigger}
						options={[
							...INHERIT_OPTIONS,
							{
								label: __('Click', 'designsetgo'),
								value: 'click',
							},
							{
								label: __('Hover', 'designsetgo'),
								value: 'hover',
							},
						]}
						onChange={(value) => setAttributes({ trigger: value })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Tooltip position', 'designsetgo')}
					hasValue={() => tooltipPosition !== 'inherit'}
					onDeselect={() =>
						setAttributes({ tooltipPosition: 'inherit' })
					}
					isShownByDefault
				>
					<SelectControl
						label={__('Tooltip position', 'designsetgo')}
						value={tooltipPosition}
						options={[
							...INHERIT_OPTIONS,
							...['top', 'right', 'bottom', 'left'].map(
								(value) => ({
									label:
										value[0].toUpperCase() + value.slice(1),
									value,
								})
							),
						]}
						onChange={(value) =>
							setAttributes({ tooltipPosition: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Tooltip width', 'designsetgo')}
					hasValue={() => typeof tooltipWidth === 'number'}
					onDeselect={() =>
						setAttributes({ tooltipWidth: undefined })
					}
					isShownByDefault
				>
					<RangeControl
						label={__('Tooltip width', 'designsetgo')}
						value={tooltipWidth}
						onChange={(value) =>
							setAttributes({ tooltipWidth: value })
						}
						min={120}
						max={600}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			</DsgoInspectorPanel>
			<DsgoInspectorPanel
				title={__('Style', 'designsetgo')}
				panelName="style"
				panelId={clientId}
				resetAll={() =>
					setAttributes({
						animation: 'inherit',
						sequenceOrder: 0,
						originX: 'center',
						originY: 'center',
					})
				}
			>
				<DsgoInspectorPanel.Item
					label={__('Marker animation', 'designsetgo')}
					hasValue={() => animation !== 'inherit'}
					onDeselect={() => setAttributes({ animation: 'inherit' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Marker animation', 'designsetgo')}
						value={animation}
						options={[
							...INHERIT_OPTIONS,
							{
								label: __('Pulse', 'designsetgo'),
								value: 'pulse',
							},
							{
								label: __('Scale', 'designsetgo'),
								value: 'scale',
							},
							{
								label: __('Fade', 'designsetgo'),
								value: 'fade',
							},
							{ label: __('None', 'designsetgo'), value: 'none' },
						]}
						onChange={(value) =>
							setAttributes({ animation: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Sequence order', 'designsetgo')}
					hasValue={() => sequenceOrder !== 0}
					onDeselect={() => setAttributes({ sequenceOrder: 0 })}
					isShownByDefault
				>
					<RangeControl
						label={__('Sequence order', 'designsetgo')}
						value={sequenceOrder}
						onChange={(value) =>
							setAttributes({ sequenceOrder: value })
						}
						min={0}
						max={20}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Horizontal origin', 'designsetgo')}
					hasValue={() => originX !== 'center'}
					onDeselect={() => setAttributes({ originX: 'center' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Horizontal origin', 'designsetgo')}
						value={originX}
						options={['left', 'center', 'right'].map((value) => ({
							label: value[0].toUpperCase() + value.slice(1),
							value,
						}))}
						onChange={(value) => setAttributes({ originX: value })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Vertical origin', 'designsetgo')}
					hasValue={() => originY !== 'center'}
					onDeselect={() => setAttributes({ originY: 'center' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Vertical origin', 'designsetgo')}
						value={originY}
						options={['top', 'center', 'bottom'].map((value) => ({
							label: value[0].toUpperCase() + value.slice(1),
							value,
						}))}
						onChange={(value) => setAttributes({ originY: value })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			</DsgoInspectorPanel>
			<DsgoInspectorPanel
				title={__('Advanced', 'designsetgo')}
				panelName="advanced"
				panelId={clientId}
				resetAll={() => {}}
			/>
		</>
	);
}
