import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalLinkControl as LinkControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	ToggleControl,
	Button,
	Popover,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { useInstanceId } from '@wordpress/compose';
import classnames from 'classnames';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { DsgoInspectorPanel } from '../../components/shared';

// Marker shape SVGs
const MarkerShapes = {
	circle: ({ size, fillColor, borderColor }) => (
		<svg
			width={size}
			height={size}
			viewBox="0 0 24 24"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
		>
			<circle
				cx="12"
				cy="12"
				r="10"
				fill={fillColor}
				stroke={borderColor}
				strokeWidth="2"
			/>
		</svg>
	),
	square: ({ size, fillColor, borderColor }) => (
		<svg
			width={size}
			height={size}
			viewBox="0 0 24 24"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
		>
			<rect
				x="2"
				y="2"
				width="20"
				height="20"
				rx="2"
				fill={fillColor}
				stroke={borderColor}
				strokeWidth="2"
			/>
		</svg>
	),
	diamond: ({ size, fillColor, borderColor }) => (
		<svg
			width={size}
			height={size}
			viewBox="0 0 24 24"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
		>
			<rect
				x="12"
				y="2"
				width="14"
				height="14"
				rx="2"
				transform="rotate(45 12 2)"
				fill={fillColor}
				stroke={borderColor}
				strokeWidth="2"
			/>
		</svg>
	),
};

export default function TimelineItemEdit({
	attributes,
	setAttributes,
	context,
	clientId,
}) {
	const {
		date,
		title,
		imageId,
		imageUrl,
		imageAlt,
		isActive,
		linkUrl,
		linkTarget,
		customMarkerColor,
		uniqueId,
	} = attributes;

	// Get context from parent timeline
	const markerStyle = context['designsetgo/timeline/markerStyle'] || 'circle';
	const markerSize = context['designsetgo/timeline/markerSize'] || 16;
	const markerColor = context['designsetgo/timeline/markerColor'] || '';
	const markerBorderColor =
		context['designsetgo/timeline/markerBorderColor'] || '';

	// State for link popover
	const [isLinkPopoverOpen, setIsLinkPopoverOpen] = useState(false);

	// Color settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Generate stable unique ID for accessibility using useInstanceId
	const instanceId = useInstanceId(TimelineItemEdit, 'timeline-item');

	// Persist unique ID to attributes if not already set
	useEffect(() => {
		if (!uniqueId) {
			setAttributes({
				uniqueId: `timeline-item-${instanceId}`,
			});
		}
	}, [uniqueId, instanceId, setAttributes]);

	// Determine marker colors (custom overrides parent)
	const effectiveMarkerColor =
		customMarkerColor ||
		markerColor ||
		'var(--wp--preset--color--primary, #2563eb)';
	const effectiveBorderColor = markerBorderColor || effectiveMarkerColor;

	// Get the marker shape component
	const MarkerShape = MarkerShapes[markerStyle] || MarkerShapes.circle;

	// Build class names
	const itemClasses = classnames('dsgo-timeline-item', {
		'dsgo-timeline-item--active': isActive,
		'dsgo-timeline-item--has-image': imageUrl,
		'dsgo-timeline-item--has-link': linkUrl,
	});

	// Custom styles for marker
	const customStyles = customMarkerColor
		? {
				'--dsgo-timeline-item-marker-color':
					convertColorToCSSVar(customMarkerColor),
			}
		: {};

	const blockProps = useBlockProps({
		className: itemClasses,
		style: customStyles,
	});

	// Inner blocks for content
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-timeline-item__content',
		},
		{
			template: [
				[
					'core/paragraph',
					{
						placeholder: __('Add timeline content…', 'designsetgo'),
					},
				],
			],
		}
	);

	// Handle image selection
	const onSelectImage = (media) => {
		setAttributes({
			imageId: media.id,
			imageUrl: media.url,
			imageAlt: media.alt || '',
		});
	};

	const onRemoveImage = () => {
		setAttributes({
			imageId: 0,
			imageUrl: '',
			imageAlt: '',
		});
	};

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							isActive: false,
							linkUrl: '',
							linkTarget: '_self',
							imageId: 0,
							imageUrl: '',
							imageAlt: '',
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Active State', 'designsetgo')}
						hasValue={() => isActive !== false}
						onDeselect={() => setAttributes({ isActive: false })}
						isShownByDefault
					>
						<ToggleControl
							label={__('Active State', 'designsetgo')}
							help={
								isActive
									? __(
											'This milestone is highlighted as active/current',
											'designsetgo'
										)
									: __(
											'This is a regular timeline item',
											'designsetgo'
										)
							}
							checked={isActive}
							onChange={(value) =>
								setAttributes({ isActive: value })
							}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Link URL', 'designsetgo')}
						hasValue={() => linkUrl !== ''}
						onDeselect={() =>
							setAttributes({
								linkUrl: '',
								linkTarget: '_self',
							})
						}
						isShownByDefault
					>
						<TextControl
							label={__('Link URL', 'designsetgo')}
							value={linkUrl}
							onChange={(value) =>
								setAttributes({ linkUrl: value })
							}
							placeholder="https://..."
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{linkUrl && (
						<DsgoInspectorPanel.Item
							label={__('Open in New Tab', 'designsetgo')}
							hasValue={() => linkTarget !== '_self'}
							onDeselect={() =>
								setAttributes({ linkTarget: '_self' })
							}
							isShownByDefault
						>
							<ToggleControl
								label={__('Open in New Tab', 'designsetgo')}
								checked={linkTarget === '_blank'}
								onChange={(value) =>
									setAttributes({
										linkTarget: value ? '_blank' : '_self',
									})
								}
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					<DsgoInspectorPanel.Item
						label={__('Marker Image', 'designsetgo')}
						hasValue={() => imageUrl !== ''}
						onDeselect={() =>
							setAttributes({
								imageId: 0,
								imageUrl: '',
								imageAlt: '',
							})
						}
						isShownByDefault
					>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={onSelectImage}
								allowedTypes={['image']}
								value={imageId}
								render={({ open }) => (
									<div className="dsgo-timeline-item__image-control">
										{imageUrl ? (
											<>
												<img
													src={imageUrl}
													alt={imageAlt}
													style={{
														maxWidth: '100%',
														height: 'auto',
														marginBottom: '8px',
														borderRadius: '4px',
													}}
												/>
												<div
													style={{
														display: 'flex',
														gap: '8px',
													}}
												>
													<Button
														variant="secondary"
														onClick={open}
													>
														{__(
															'Replace',
															'designsetgo'
														)}
													</Button>
													<Button
														variant="secondary"
														isDestructive
														onClick={onRemoveImage}
													>
														{__(
															'Remove',
															'designsetgo'
														)}
													</Button>
												</div>
											</>
										) : (
											<Button
												variant="secondary"
												onClick={open}
											>
												{__(
													'Add Marker Image',
													'designsetgo'
												)}
											</Button>
										)}
									</div>
								)}
							/>
						</MediaUploadCheck>
						<p
							className="components-base-control__help"
							style={{ marginTop: '8px' }}
						>
							{__(
								'Optional: Replace the marker dot with an image or avatar.',
								'designsetgo'
							)}
						</p>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					settings={[
						{
							label: __('Custom Marker Color', 'designsetgo'),
							colorValue: decodeColorValue(
								customMarkerColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									customMarkerColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
					]}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<div {...blockProps}>
				{/* Marker */}
				<div className="dsgo-timeline-item__marker" aria-hidden="true">
					{imageUrl ? (
						<img
							src={imageUrl}
							alt=""
							className="dsgo-timeline-item__marker-image"
							style={{
								width: markerSize,
								height: markerSize,
								borderRadius:
									markerStyle === 'circle' ? '50%' : '4px',
								objectFit: 'cover',
							}}
						/>
					) : (
						<MarkerShape
							size={markerSize}
							fillColor={effectiveMarkerColor}
							borderColor={effectiveBorderColor}
						/>
					)}
				</div>

				{/* Content wrapper */}
				<div className="dsgo-timeline-item__wrapper">
					{/* Date/Label */}
					<RichText
						tagName="span"
						className="dsgo-timeline-item__date"
						value={date}
						onChange={(value) => setAttributes({ date: value })}
						placeholder={__('Date or label…', 'designsetgo')}
						allowedFormats={['core/bold', 'core/italic']}
					/>

					{/* Title */}
					<RichText
						tagName="h3"
						className="dsgo-timeline-item__title"
						value={title}
						onChange={(value) => setAttributes({ title: value })}
						placeholder={__('Event Title', 'designsetgo')}
						allowedFormats={[
							'core/bold',
							'core/italic',
							'core/link',
						]}
					/>

					{/* Inner blocks content */}
					<div {...innerBlocksProps} />

					{/* Link indicator */}
					{linkUrl && (
						<div className="dsgo-timeline-item__link-indicator">
							<Button
								variant="link"
								onClick={() =>
									setIsLinkPopoverOpen(!isLinkPopoverOpen)
								}
							>
								{__('Edit Link', 'designsetgo')}
							</Button>
							{isLinkPopoverOpen && (
								<Popover
									position="bottom center"
									onClose={() => setIsLinkPopoverOpen(false)}
								>
									<LinkControl
										value={{
											url: linkUrl,
											opensInNewTab:
												linkTarget === '_blank',
										}}
										onChange={({ url, opensInNewTab }) => {
											setAttributes({
												linkUrl: url || '',
												linkTarget: opensInNewTab
													? '_blank'
													: '_self',
											});
										}}
									/>
								</Popover>
							)}
						</div>
					)}
				</div>
			</div>
		</>
	);
}
