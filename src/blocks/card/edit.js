/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	TextControl,
	ToggleControl,
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalDivider as Divider,
} from '@wordpress/components';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { useBlockColors } from '../../hooks';

/**
 * Edit component for Card block
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to set attributes
 * @param {string}   props.clientId      - Block client ID
 * @return {Element} Edit component
 */
export default function CardEdit({ attributes, setAttributes, clientId }) {
	const {
		layoutPreset,
		imageUrl,
		imageAlt,
		imageAspectRatio,
		imageCustomAspectRatio,
		imageObjectFit,
		imageFocalPoint,
		badgeText,
		badgeStyle,
		badgeFloatingPosition,
		badgeInlinePosition,
		badgeBackgroundColor,
		badgeTextColor,
		title,
		subtitle,
		bodyText,
		overlayOpacity,
		overlayColor,
		contentAlignment,
		visualStyle,
		borderColor,
		showImage,
		showTitle,
		showSubtitle,
		showBody,
		showBadge,
		showCta,
	} = attributes;

	// Border panel — migrated to useBlockColors hook.
	// colorGradientSettings is returned by the hook (same shape as
	// useMultipleOriginColorsAndGradients) and used by the inline panels below.
	const { settings: borderColorSettings, colorGradientSettings } = useBlockColors({
		attributes,
		setAttributes,
		entries: [
			{
				label: __('Border Color', 'designsetgo'),
				attribute: 'borderColor',
			},
		],
	});

	// Build block props with border color
	const blockStyles = {};
	// Only apply custom border color on styles that have borders (not minimal)
	if (borderColor && visualStyle !== 'minimal') {
		blockStyles.borderColor = borderColor;
		// Ensure border exists
		blockStyles.borderWidth = visualStyle === 'outlined' ? '2px' : '1px';
		blockStyles.borderStyle = 'solid';
	}

	const blockProps = useBlockProps({
		className: `dsgo-card dsgo-card--${layoutPreset} dsgo-card--style-${visualStyle}`,
		style: blockStyles,
	});

	// Inner blocks props for CTA area
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-card__cta',
		},
		{
			template: [
				[
					'designsetgo/icon-button',
					{ text: __('Learn More', 'designsetgo') },
				],
			],
			templateLock: false,
			allowedBlocks: ['designsetgo/icon-button'],
		}
	);

	// Calculate image styles
	const imageStyles = {};
	if (imageAspectRatio !== 'original') {
		if (imageAspectRatio === 'custom' && imageCustomAspectRatio) {
			imageStyles.aspectRatio = imageCustomAspectRatio;
		} else if (imageAspectRatio === '16-9') {
			imageStyles.aspectRatio = '16 / 9';
		} else if (imageAspectRatio === '4-3') {
			imageStyles.aspectRatio = '4 / 3';
		} else if (imageAspectRatio === '1-1') {
			imageStyles.aspectRatio = '1 / 1';
		}
	}
	if (imageObjectFit) {
		imageStyles.objectFit = imageObjectFit;
	}
	if (imageObjectFit === 'cover' && imageFocalPoint) {
		imageStyles.objectPosition = `${Number(imageFocalPoint.x) * 100}% ${Number(imageFocalPoint.y) * 100}%`;
	}

	// Calculate badge styles
	const badgeStyles = {};
	if (badgeBackgroundColor) {
		badgeStyles.backgroundColor = badgeBackgroundColor;
	}
	if (badgeTextColor) {
		badgeStyles.color = badgeTextColor;
	}

	// Calculate overlay styles for background layout
	const overlayStyles = {};
	if (layoutPreset === 'background') {
		if (overlayColor) {
			overlayStyles.backgroundColor = overlayColor;
			overlayStyles.opacity = overlayOpacity / 100;
		} else {
			// Use theme contrast color at full opacity, let overlayOpacity control transparency
			overlayStyles.backgroundColor =
				'var(--wp--preset--color--contrast, #000)';
			overlayStyles.opacity = overlayOpacity / 100;
		}
	}

	// Content alignment class
	const contentAlignmentClass = `dsgo-card__content--${contentAlignment}`;

	// Options for select controls
	const layoutOptions = [
		{ label: __('Standard (Image Top)', 'designsetgo'), value: 'standard' },
		{
			label: __('Horizontal (Image Left)', 'designsetgo'),
			value: 'horizontal-left',
		},
		{
			label: __('Horizontal (Image Right)', 'designsetgo'),
			value: 'horizontal-right',
		},
		{
			label: __('Background (Image Behind)', 'designsetgo'),
			value: 'background',
		},
		{ label: __('Minimal (No Image)', 'designsetgo'), value: 'minimal' },
		{
			label: __('Featured (Large Image)', 'designsetgo'),
			value: 'featured',
		},
	];

	const visualStyleOptions = [
		{ label: __('Default', 'designsetgo'), value: 'default' },
		{ label: __('Outlined', 'designsetgo'), value: 'outlined' },
		{ label: __('Filled', 'designsetgo'), value: 'filled' },
		{ label: __('Shadow', 'designsetgo'), value: 'shadow' },
		{ label: __('Minimal', 'designsetgo'), value: 'minimal' },
	];

	const alignmentOptions = [
		{ label: __('Left', 'designsetgo'), value: 'left' },
		{ label: __('Center', 'designsetgo'), value: 'center' },
		{ label: __('Right', 'designsetgo'), value: 'right' },
	];

	const badgeStyleOptions = [
		{ label: __('Floating (Over Card)', 'designsetgo'), value: 'floating' },
		{ label: __('Inline (In Content)', 'designsetgo'), value: 'inline' },
	];

	const badgeFloatingPositionOptions = [
		{ label: __('Top Left', 'designsetgo'), value: 'top-left' },
		{ label: __('Top Right', 'designsetgo'), value: 'top-right' },
		{ label: __('Bottom Left', 'designsetgo'), value: 'bottom-left' },
		{ label: __('Bottom Right', 'designsetgo'), value: 'bottom-right' },
	];

	const badgeInlinePositionOptions = [
		{ label: __('Above Title', 'designsetgo'), value: 'above-title' },
		{ label: __('Below Title', 'designsetgo'), value: 'below-title' },
	];

	// Render badge
	const renderBadge = () => {
		if (!showBadge || !badgeText) {
			return null;
		}

		const badgeClass =
			badgeStyle === 'floating'
				? `dsgo-card__badge dsgo-card__badge--floating dsgo-card__badge--${badgeFloatingPosition}`
				: `dsgo-card__badge dsgo-card__badge--inline dsgo-card__badge--${badgeInlinePosition}`;

		return (
			<span className={badgeClass} style={badgeStyles}>
				{badgeText}
			</span>
		);
	};

	// Render image
	const renderImage = () => {
		if (!showImage || layoutPreset === 'minimal') {
			return null;
		}

		// Placeholder for background layout
		if (layoutPreset === 'background') {
			if (!imageUrl) {
				return (
					<div className="dsgo-card__background dsgo-card__background--placeholder">
						<div className="dsgo-card__placeholder-content">
							<span className="dashicons dashicons-format-image"></span>
							<span>
								{__('Select background image', 'designsetgo')}
							</span>
						</div>
					</div>
				);
			}
			return (
				<div
					className="dsgo-card__background"
					style={{ backgroundImage: `url(${imageUrl})` }}
				>
					<div className="dsgo-card__overlay" style={overlayStyles} />
				</div>
			);
		}

		// Placeholder for standard layouts
		if (!imageUrl) {
			return (
				<div className="dsgo-card__image-wrapper dsgo-card__image-wrapper--placeholder">
					<div className="dsgo-card__placeholder-content">
						<span className="dashicons dashicons-format-image"></span>
						<span>{__('Select image', 'designsetgo')}</span>
					</div>
				</div>
			);
		}

		return (
			<div className="dsgo-card__image-wrapper">
				<img
					src={imageUrl}
					alt={imageAlt}
					className="dsgo-card__image"
					style={imageStyles}
				/>
			</div>
		);
	};

	// Render content
	const renderContent = () => (
		<div
			className={`dsgo-card__content ${layoutPreset === 'background' ? contentAlignmentClass : ''}`}
		>
			{badgeStyle === 'inline' &&
				badgeInlinePosition === 'above-title' &&
				renderBadge()}

			{showTitle && (
				<RichText
					tagName="h3"
					className="dsgo-card__title"
					value={title}
					onChange={(value) => setAttributes({ title: value })}
					placeholder={__('Card Title…', 'designsetgo')}
				/>
			)}

			{badgeStyle === 'inline' &&
				badgeInlinePosition === 'below-title' &&
				renderBadge()}

			{showSubtitle && (
				<RichText
					tagName="p"
					className="dsgo-card__subtitle"
					value={subtitle}
					onChange={(value) => setAttributes({ subtitle: value })}
					placeholder={__('Card Subtitle…', 'designsetgo')}
				/>
			)}

			{showBody && (
				<RichText
					tagName="p"
					className="dsgo-card__body"
					value={bodyText}
					onChange={(value) => setAttributes({ bodyText: value })}
					placeholder={__(
						'Card description goes here…',
						'designsetgo'
					)}
				/>
			)}

			{showCta && <div {...innerBlocksProps} />}
		</div>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Card Settings', 'designsetgo')}
					initialOpen={true}
				>
					{/* Layout Settings */}
					<SelectControl
						label={__('Layout Preset', 'designsetgo')}
						value={layoutPreset}
						options={layoutOptions}
						onChange={(value) =>
							setAttributes({ layoutPreset: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Visual Style', 'designsetgo')}
						value={visualStyle}
						options={visualStyleOptions}
						onChange={(value) =>
							setAttributes({ visualStyle: value })
						}
						help={__(
							'Choose a visual style for the card appearance.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					{/* Image Upload */}
					{layoutPreset !== 'minimal' && showImage && (
						<>
							<Divider />
							<p style={{ marginBottom: '8px', fontWeight: 600 }}>
								{__('Image', 'designsetgo')}
							</p>
							<MediaUploadCheck>
								<MediaUpload
									onSelect={(media) => {
										setAttributes({
											imageId: media.id,
											imageUrl: media.url,
											imageAlt: media.alt || '',
										});
									}}
									allowedTypes={['image']}
									value={imageUrl}
									render={({ open }) => (
										<>
											{imageUrl ? (
												<>
													<img
														src={imageUrl}
														alt={imageAlt}
														style={{
															width: '100%',
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
															onClick={open}
															variant="secondary"
															style={{ flex: 1 }}
														>
															{__(
																'Replace Image',
																'designsetgo'
															)}
														</Button>
														<Button
															onClick={() =>
																setAttributes({
																	imageId: 0,
																	imageUrl:
																		'',
																	imageAlt:
																		'',
																})
															}
															variant="secondary"
															isDestructive
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
													onClick={open}
													variant="secondary"
													style={{
														width: '100%',
														justifyContent:
															'center',
													}}
												>
													{__(
														'Select Image',
														'designsetgo'
													)}
												</Button>
											)}
										</>
									)}
								/>
							</MediaUploadCheck>

							{imageUrl && (
								<>
									<TextControl
										label={__('Alt Text', 'designsetgo')}
										value={imageAlt}
										onChange={(value) =>
											setAttributes({ imageAlt: value })
										}
										help={__(
											'Describe the image for accessibility.',
											'designsetgo'
										)}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										style={{ marginTop: '12px' }}
									/>

									<SelectControl
										label={__(
											'Aspect Ratio',
											'designsetgo'
										)}
										value={imageAspectRatio}
										options={[
											{
												label: __(
													'16:9',
													'designsetgo'
												),
												value: '16-9',
											},
											{
												label: __('4:3', 'designsetgo'),
												value: '4-3',
											},
											{
												label: __('1:1', 'designsetgo'),
												value: '1-1',
											},
											{
												label: __(
													'Original',
													'designsetgo'
												),
												value: 'original',
											},
											{
												label: __(
													'Custom',
													'designsetgo'
												),
												value: 'custom',
											},
										]}
										onChange={(value) =>
											setAttributes({
												imageAspectRatio: value,
											})
										}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>

									{imageAspectRatio === 'custom' && (
										<TextControl
											label={__(
												'Custom Aspect Ratio',
												'designsetgo'
											)}
											value={imageCustomAspectRatio}
											onChange={(value) =>
												setAttributes({
													imageCustomAspectRatio:
														value,
												})
											}
											placeholder="16 / 9"
											help={__(
												'E.g., "16 / 9" or "2 / 1"',
												'designsetgo'
											)}
											__next40pxDefaultSize
											__nextHasNoMarginBottom
										/>
									)}

									<SelectControl
										label={__('Object Fit', 'designsetgo')}
										value={imageObjectFit}
										options={[
											{
												label: __(
													'Cover',
													'designsetgo'
												),
												value: 'cover',
											},
											{
												label: __(
													'Contain',
													'designsetgo'
												),
												value: 'contain',
											},
											{
												label: __(
													'Fill',
													'designsetgo'
												),
												value: 'fill',
											},
											{
												label: __(
													'Scale Down',
													'designsetgo'
												),
												value: 'scale-down',
											},
										]}
										onChange={(value) =>
											setAttributes({
												imageObjectFit: value,
											})
										}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
								</>
							)}
						</>
					)}

					{layoutPreset === 'background' && (
						<>
							<RangeControl
								label={__('Overlay Opacity', 'designsetgo')}
								value={overlayOpacity}
								onChange={(value) =>
									setAttributes({ overlayOpacity: value })
								}
								min={0}
								max={100}
								step={5}
								help={__(
									'Darkens the background image to improve text readability.',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<SelectControl
								label={__('Content Alignment', 'designsetgo')}
								value={contentAlignment}
								options={alignmentOptions}
								onChange={(value) =>
									setAttributes({ contentAlignment: value })
								}
								help={__(
									'Horizontal alignment for content over background image.',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</>
					)}

					<Divider />

					{/* Badge Settings */}
					<TextControl
						label={__('Badge Text', 'designsetgo')}
						value={badgeText}
						onChange={(value) =>
							setAttributes({ badgeText: value })
						}
						placeholder={__('NEW', 'designsetgo')}
						help={__(
							'Leave empty to hide the badge.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					{badgeText && (
						<>
							<SelectControl
								label={__('Badge Style', 'designsetgo')}
								value={badgeStyle}
								options={badgeStyleOptions}
								onChange={(value) =>
									setAttributes({ badgeStyle: value })
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							{badgeStyle === 'floating' && (
								<SelectControl
									label={__(
										'Floating Position',
										'designsetgo'
									)}
									value={badgeFloatingPosition}
									options={badgeFloatingPositionOptions}
									onChange={(value) =>
										setAttributes({
											badgeFloatingPosition: value,
										})
									}
									help={__(
										'Position the badge over the card.',
										'designsetgo'
									)}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							)}

							{badgeStyle === 'inline' && (
								<SelectControl
									label={__('Inline Position', 'designsetgo')}
									value={badgeInlinePosition}
									options={badgeInlinePositionOptions}
									onChange={(value) =>
										setAttributes({
											badgeInlinePosition: value,
										})
									}
									help={__(
										'Position the badge in the content flow.',
										'designsetgo'
									)}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							)}
						</>
					)}

					<Divider />

					{/* Content Elements */}
					<p style={{ marginBottom: '8px', fontWeight: 600 }}>
						{__('Content Elements', 'designsetgo')}
					</p>

					{layoutPreset !== 'minimal' && (
						<ToggleControl
							label={__('Show Image', 'designsetgo')}
							checked={showImage}
							onChange={(value) =>
								setAttributes({ showImage: value })
							}
							help={__('Display the card image.', 'designsetgo')}
							__nextHasNoMarginBottom
						/>
					)}

					<ToggleControl
						label={__('Show Title', 'designsetgo')}
						checked={showTitle}
						onChange={(value) =>
							setAttributes({ showTitle: value })
						}
						help={__('Display the card title.', 'designsetgo')}
						__nextHasNoMarginBottom
					/>

					<ToggleControl
						label={__('Show Subtitle', 'designsetgo')}
						checked={showSubtitle}
						onChange={(value) =>
							setAttributes({ showSubtitle: value })
						}
						help={__('Display the card subtitle.', 'designsetgo')}
						__nextHasNoMarginBottom
					/>

					<ToggleControl
						label={__('Show Body Text', 'designsetgo')}
						checked={showBody}
						onChange={(value) => setAttributes({ showBody: value })}
						help={__('Display the card body text.', 'designsetgo')}
						__nextHasNoMarginBottom
					/>

					<ToggleControl
						label={__('Show Badge', 'designsetgo')}
						checked={showBadge}
						onChange={(value) =>
							setAttributes({ showBadge: value })
						}
						help={__('Display the badge element.', 'designsetgo')}
						__nextHasNoMarginBottom
					/>

					<ToggleControl
						label={__('Show CTA Button', 'designsetgo')}
						checked={showCta}
						onChange={(value) => setAttributes({ showCta: value })}
						help={__(
							'Display the call-to-action button.',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Border', 'designsetgo')}
					settings={borderColorSettings}
					{...colorGradientSettings}
				/>

				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Badge Colors', 'designsetgo')}
					settings={[
						{
							label: __('Badge Background', 'designsetgo'),
							colorValue: decodeColorValue(
								badgeBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									badgeBackgroundColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Badge Text', 'designsetgo'),
							colorValue: decodeColorValue(
								badgeTextColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									badgeTextColor:
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

				{layoutPreset === 'background' && (
					<ColorGradientSettingsDropdown
						panelId={clientId}
						title={__('Overlay Color', 'designsetgo')}
						settings={[
							{
								label: __('Overlay', 'designsetgo'),
								colorValue: decodeColorValue(
									overlayColor,
									colorGradientSettings
								),
								onColorChange: (color) =>
									setAttributes({
										overlayColor:
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
				)}
			</InspectorControls>

			<div {...blockProps}>
				{badgeStyle === 'floating' && renderBadge()}
				{layoutPreset === 'background' && renderImage()}

				<div className="dsgo-card__inner">
					{layoutPreset !== 'background' && renderImage()}
					{renderContent()}
				</div>
			</div>
		</>
	);
}
