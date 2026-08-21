import { __ } from '@wordpress/i18n';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import {
	Button,
	RangeControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared/DsgoInspectorPanel';

export default function HotspotInspector({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		imageId,
		imageUrl,
		imageAlt,
		trigger,
		tooltipPosition,
		tooltipWidth,
		animation,
		sequenceDuration,
	} = attributes;
	const selectImage = (media) =>
		setAttributes({
			imageId: media.id,
			imageUrl: media.url,
			imageAlt: media.alt || '',
		});

	return (
		<>
			<DsgoInspectorPanel
				title={__('Settings', 'designsetgo')}
				panelName="settings"
				panelId={clientId}
				resetAll={() =>
					setAttributes({
						imageId: undefined,
						imageUrl: '',
						imageAlt: '',
						trigger: 'click',
						tooltipPosition: 'top',
						tooltipWidth: 240,
						animation: 'pulse',
						sequenceDuration: 0,
					})
				}
			>
				<DsgoInspectorPanel.Item
					label={__('Image', 'designsetgo')}
					hasValue={() => !!imageUrl}
					onDeselect={() =>
						setAttributes({
							imageId: undefined,
							imageUrl: '',
							imageAlt: '',
						})
					}
					isShownByDefault
				>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={selectImage}
							allowedTypes={['image']}
							value={imageId}
							render={({ open }) => (
								<Button variant="secondary" onClick={open}>
									{imageUrl
										? __('Replace image', 'designsetgo')
										: __('Select image', 'designsetgo')}
								</Button>
							)}
						/>
					</MediaUploadCheck>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Alternative text', 'designsetgo')}
					hasValue={() => !!imageAlt}
					onDeselect={() => setAttributes({ imageAlt: '' })}
					isShownByDefault
				>
					<TextControl
						label={__('Alternative text', 'designsetgo')}
						value={imageAlt}
						onChange={(value) => setAttributes({ imageAlt: value })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Trigger', 'designsetgo')}
					hasValue={() => trigger !== 'click'}
					onDeselect={() => setAttributes({ trigger: 'click' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Trigger', 'designsetgo')}
						value={trigger}
						options={[
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
					hasValue={() => tooltipPosition !== 'top'}
					onDeselect={() => setAttributes({ tooltipPosition: 'top' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Tooltip position', 'designsetgo')}
						value={tooltipPosition}
						options={['top', 'right', 'bottom', 'left'].map(
							(value) => ({
								label: value[0].toUpperCase() + value.slice(1),
								value,
							})
						)}
						onChange={(value) =>
							setAttributes({ tooltipPosition: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Tooltip width', 'designsetgo')}
					hasValue={() => tooltipWidth !== 240}
					onDeselect={() => setAttributes({ tooltipWidth: 240 })}
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
						animation: 'pulse',
						sequenceDuration: 0,
					})
				}
			>
				<DsgoInspectorPanel.Item
					label={__('Marker animation', 'designsetgo')}
					hasValue={() => animation !== 'pulse'}
					onDeselect={() => setAttributes({ animation: 'pulse' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Marker animation', 'designsetgo')}
						value={animation}
						options={[
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
					label={__('Sequence duration', 'designsetgo')}
					hasValue={() => sequenceDuration !== 0}
					onDeselect={() => setAttributes({ sequenceDuration: 0 })}
					isShownByDefault
				>
					<RangeControl
						label={__('Sequence duration', 'designsetgo')}
						value={sequenceDuration}
						onChange={(value) =>
							setAttributes({ sequenceDuration: value })
						}
						min={0}
						max={3000}
						step={100}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			</DsgoInspectorPanel>
		</>
	);
}
