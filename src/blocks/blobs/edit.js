/**
 * Blobs Block - Editor Component
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	SelectControl,
	RangeControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import classnames from 'classnames';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

export default function BlobsEdit({ attributes, setAttributes, clientId }) {
	const wrapperRef = useRef(null);
	const {
		blobShape,
		blobAnimation,
		animationDuration,
		animationEasing,
		size,
		height,
		enableOverlay,
		overlayColor,
		overlayOpacity,
	} = attributes;

	// Derive unit-specific min/max for the blob size control
	const sizeUnit = size ? size.replace(/[\d.]+/, '') : 'px';
	const sizeConstraints = {
		px: { min: 50, max: 800 },
		'%': { min: 20, max: 200 },
		vw: { min: 10, max: 100 },
		vh: { min: 10, max: 100 },
	};
	const { min: sizeMin, max: sizeMax } =
		sizeConstraints[sizeUnit] || sizeConstraints.px;

	// Derive unit-specific min/max for the blob height control
	const heightUnit = height ? height.replace(/[\d.]+/, '') : 'px';
	const { min: heightMin, max: heightMax } =
		sizeConstraints[heightUnit] || sizeConstraints.px;

	// Get theme color palette and gradient settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Calculate classes
	const blobClasses = classnames('dsgo-blobs', {
		[`dsgo-blobs--${blobShape}`]: blobShape,
		[`dsgo-blobs--${blobAnimation}`]:
			blobAnimation && blobAnimation !== 'none',
	});

	// Apply animation settings as CSS custom properties
	const customStyles = {
		'--dsgo-blob-size': size,
		...(height ? { '--dsgo-blob-height': height } : {}),
		'--dsgo-blob-animation-duration': animationDuration,
		'--dsgo-blob-animation-easing': animationEasing,
	};

	// Get block props with our wrapper class
	const blockProps = useBlockProps({
		className: 'dsgo-blobs-wrapper',
		ref: wrapperRef,
	});

	// Transfer background styles from wrapper to blob in editor
	useEffect(() => {
		if (!wrapperRef.current) {
			return;
		}

		const wrapper = wrapperRef.current;
		const blob = wrapper.querySelector('.dsgo-blobs');
		if (!blob) {
			return;
		}

		// WordPress sets inline styles on the wrapper
		// We need to read inline styles directly because our CSS has `background: none !important;`
		const inlineStyle = wrapper.style;

		// Transfer background image
		if (
			inlineStyle.backgroundImage &&
			inlineStyle.backgroundImage !== 'none'
		) {
			blob.style.setProperty(
				'background-image',
				inlineStyle.backgroundImage
			);
		} else {
			blob.style.removeProperty('background-image');
		}

		// Transfer background size
		if (
			inlineStyle.backgroundSize &&
			inlineStyle.backgroundSize !== 'auto'
		) {
			blob.style.setProperty(
				'background-size',
				inlineStyle.backgroundSize
			);
		}

		// Transfer background position
		if (inlineStyle.backgroundPosition) {
			blob.style.setProperty(
				'background-position',
				inlineStyle.backgroundPosition
			);
		}

		// Transfer background repeat
		if (
			inlineStyle.backgroundRepeat &&
			inlineStyle.backgroundRepeat !== 'repeat'
		) {
			blob.style.setProperty(
				'background-repeat',
				inlineStyle.backgroundRepeat
			);
		}

		// Transfer background attachment
		if (
			inlineStyle.backgroundAttachment &&
			inlineStyle.backgroundAttachment !== 'scroll'
		) {
			blob.style.setProperty(
				'background-attachment',
				inlineStyle.backgroundAttachment
			);
		}

		// Transfer WordPress background color classes from wrapper to blob
		// so WordPress's own CSS applies the color to the blob shape directly
		const bgClasses = Array.from(wrapper.classList).filter(
			(c) =>
				c.match(/^has-.*-background-color$/) || c === 'has-background'
		);
		bgClasses.forEach((cls) => {
			wrapper.classList.remove(cls);
			blob.classList.add(cls);
		});

		// Transfer inline background color (custom non-preset colors)
		if (inlineStyle.backgroundColor) {
			blob.style.setProperty(
				'background-color',
				inlineStyle.backgroundColor
			);
		} else if (bgClasses.length === 0) {
			// Apply default color if no user color is set
			const defaultColor =
				window
					.getComputedStyle(document.documentElement)
					.getPropertyValue('--wp--preset--color--accent-2') ||
				'#2563eb';
			blob.style.setProperty('background-color', defaultColor.trim());
		}
	}); // Run on every render to catch style changes

	// Inner blocks for content inside blob
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-blobs__content',
		},
		{
			template: [
				[
					'core/heading',
					{
						level: 2,
						placeholder: __('Add title…', 'designsetgo'),
						textAlign: 'center',
					},
				],
				[
					'core/paragraph',
					{
						placeholder: __('Add description…', 'designsetgo'),
						align: 'center',
					},
				],
			],
		}
	);

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							blobShape: 'shape-1',
							blobAnimation: 'none',
							animationDuration: '8s',
							animationEasing: 'ease-in-out',
							size: '300px',
							height: '',
							overlayOpacity: 80,
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Blob Shape', 'designsetgo')}
						hasValue={() => blobShape !== 'shape-1'}
						onDeselect={() =>
							setAttributes({ blobShape: 'shape-1' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Blob Shape', 'designsetgo')}
							value={blobShape}
							options={[
								{
									label: __('Classic Blob', 'designsetgo'),
									value: 'shape-1',
								},
								{
									label: __('Amoeba', 'designsetgo'),
									value: 'shape-2',
								},
								{
									label: __('Pebble', 'designsetgo'),
									value: 'shape-3',
								},
								{
									label: __('Splash', 'designsetgo'),
									value: 'shape-4',
								},
								{
									label: __('Drop', 'designsetgo'),
									value: 'shape-5',
								},
								{
									label: __('Cloud', 'designsetgo'),
									value: 'shape-6',
								},
							]}
							onChange={(value) =>
								setAttributes({ blobShape: value })
							}
							help={__(
								'Choose the organic shape style',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Width', 'designsetgo')}
						hasValue={() => size !== '300px'}
						onDeselect={() => setAttributes({ size: '300px' })}
						isShownByDefault
					>
						<UnitControl
							label={__('Width', 'designsetgo')}
							value={size}
							onChange={(value) =>
								setAttributes({ size: value || '300px' })
							}
							units={[
								{ value: 'px', label: 'px', default: 300 },
								{ value: '%', label: '%', default: 100 },
								{ value: 'vw', label: 'vw', default: 30 },
								{ value: 'vh', label: 'vh', default: 30 },
							]}
							min={sizeMin}
							max={sizeMax}
							help={__('Width of the blob shape', 'designsetgo')}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Height', 'designsetgo')}
						hasValue={() => height !== ''}
						onDeselect={() => setAttributes({ height: '' })}
						isShownByDefault
					>
						<UnitControl
							label={__('Height', 'designsetgo')}
							value={height}
							onChange={(value) =>
								setAttributes({ height: value })
							}
							units={[
								{ value: 'px', label: 'px', default: 300 },
								{ value: '%', label: '%', default: 100 },
								{ value: 'vw', label: 'vw', default: 30 },
								{ value: 'vh', label: 'vh', default: 30 },
							]}
							min={heightMin}
							max={heightMax}
							placeholder={size}
							help={__(
								'Height of the blob shape. Defaults to width if empty.',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Animation Type', 'designsetgo')}
						hasValue={() => blobAnimation !== 'none'}
						onDeselect={() =>
							setAttributes({ blobAnimation: 'none' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Animation Type', 'designsetgo')}
							value={blobAnimation}
							options={[
								{
									label: __('None', 'designsetgo'),
									value: 'none',
								},
								{
									label: __('Float', 'designsetgo'),
									value: 'float',
								},
								{
									label: __('Pulse', 'designsetgo'),
									value: 'pulse',
								},
								{
									label: __('Spin', 'designsetgo'),
									value: 'spin',
								},
								{
									label: __('Morph Style 1', 'designsetgo'),
									value: 'morph-1',
								},
								{
									label: __('Morph Style 2', 'designsetgo'),
									value: 'morph-2',
								},
							]}
							onChange={(value) =>
								setAttributes({ blobAnimation: value })
							}
							help={__(
								'Float/Pulse/Spin keep your shape. Morph changes the shape itself.',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Animation Duration', 'designsetgo')}
						hasValue={() => animationDuration !== '8s'}
						onDeselect={() =>
							setAttributes({ animationDuration: '8s' })
						}
						isShownByDefault
					>
						<UnitControl
							label={__('Animation Duration', 'designsetgo')}
							value={animationDuration}
							onChange={(value) =>
								setAttributes({
									animationDuration: value || '8s',
								})
							}
							units={[
								{ value: 's', label: 's', default: 8 },
								{ value: 'ms', label: 'ms', default: 8000 },
							]}
							min={1}
							max={30}
							help={__(
								'How long one animation cycle takes',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Animation Easing', 'designsetgo')}
						hasValue={() => animationEasing !== 'ease-in-out'}
						onDeselect={() =>
							setAttributes({ animationEasing: 'ease-in-out' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Animation Easing', 'designsetgo')}
							value={animationEasing}
							options={[
								{
									label: __('Linear', 'designsetgo'),
									value: 'linear',
								},
								{
									label: __('Ease', 'designsetgo'),
									value: 'ease',
								},
								{
									label: __('Ease In', 'designsetgo'),
									value: 'ease-in',
								},
								{
									label: __('Ease Out', 'designsetgo'),
									value: 'ease-out',
								},
								{
									label: __('Ease In Out', 'designsetgo'),
									value: 'ease-in-out',
								},
							]}
							onChange={(value) =>
								setAttributes({ animationEasing: value })
							}
							help={__(
								'Animation timing function',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{enableOverlay && (
						<DsgoInspectorPanel.Item
							label={__('Overlay Opacity', 'designsetgo')}
							hasValue={() => overlayOpacity !== 80}
							onDeselect={() =>
								setAttributes({ overlayOpacity: 80 })
							}
							isShownByDefault
						>
							<RangeControl
								label={__('Overlay Opacity', 'designsetgo')}
								value={overlayOpacity}
								onChange={(value) =>
									setAttributes({ overlayOpacity: value })
								}
								min={0}
								max={100}
								help={__(
									'Overlay transparency (0 = transparent, 100 = opaque)',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Overlay Color', 'designsetgo')}
					settings={[
						{
							label: __('Overlay Color', 'designsetgo'),
							colorValue: decodeColorValue(
								overlayColor,
								colorGradientSettings
							),
							onColorChange: (color) => {
								// Auto-enable overlay when user sets a color
								if (color) {
									setAttributes({
										overlayColor: encodeColorValue(
											color,
											colorGradientSettings
										),
										enableOverlay: true,
									});
								} else {
									// Disable overlay when color is cleared
									setAttributes({
										overlayColor: '',
										enableOverlay: false,
									});
								}
							},
							enableAlpha: true,
							clearable: true,
						},
					]}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<div {...blockProps}>
				<div className={blobClasses} style={customStyles}>
					{enableOverlay && (
						<div
							className="dsgo-blobs__overlay"
							style={{
								backgroundColor:
									convertColorToCSSVar(overlayColor),
								opacity: overlayOpacity / 100,
							}}
						/>
					)}
					<div className="dsgo-blobs__shape">
						<div {...innerBlocksProps} />
					</div>
				</div>
			</div>
		</>
	);
}
