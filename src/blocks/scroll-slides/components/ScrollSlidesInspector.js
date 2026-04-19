/**
 * Scroll Slides Inspector Controls
 *
 * Settings panel and color controls for the Scroll Slides block.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { DsgoInspectorPanel } from '../../../components/shared';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../../utils/encode-color-value';

export default function ScrollSlidesInspector({
	attributes,
	setAttributes,
	clientId,
	themeContentSize,
}) {
	const {
		minHeight,
		maxHeight,
		constrainWidth,
		contentWidth,
		overlayColor,
		navColor,
		navActiveColor,
	} = attributes;

	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							minHeight: '100vh',
							maxHeight: '900px',
							constrainWidth: true,
							contentWidth: '',
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Minimum Height', 'designsetgo')}
						hasValue={() => minHeight !== '100vh'}
						onDeselect={() => setAttributes({ minHeight: '100vh' })}
						isShownByDefault
					>
						<UnitControl
							label={__('Minimum Height', 'designsetgo')}
							value={minHeight}
							onChange={(value) =>
								setAttributes({ minHeight: value })
							}
							units={[
								{ value: 'vh', label: 'vh' },
								{ value: 'px', label: 'px' },
								{ value: 'rem', label: 'rem' },
								{ value: '%', label: '%' },
							]}
							__next40pxDefaultSize
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Maximum Height', 'designsetgo')}
						hasValue={() => maxHeight !== '900px'}
						onDeselect={() => setAttributes({ maxHeight: '900px' })}
						isShownByDefault
					>
						<UnitControl
							label={__('Maximum Height', 'designsetgo')}
							value={maxHeight}
							onChange={(value) =>
								setAttributes({ maxHeight: value })
							}
							help={__(
								'Caps the section height on tall monitors',
								'designsetgo'
							)}
							units={[
								{ value: 'px', label: 'px' },
								{ value: 'vh', label: 'vh' },
								{ value: 'rem', label: 'rem' },
							]}
							__next40pxDefaultSize
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Constrain Content Width', 'designsetgo')}
						hasValue={() => constrainWidth !== true}
						onDeselect={() =>
							setAttributes({
								constrainWidth: true,
								contentWidth: '',
							})
						}
						isShownByDefault
					>
						<ToggleControl
							label={__('Constrain Content Width', 'designsetgo')}
							checked={constrainWidth}
							onChange={(value) =>
								setAttributes({ constrainWidth: value })
							}
							help={
								constrainWidth
									? __(
											'Content respects theme content width',
											'designsetgo'
										)
									: __(
											'Content fills full width',
											'designsetgo'
										)
							}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{constrainWidth && (
						<DsgoInspectorPanel.Item
							label={__('Content Width', 'designsetgo')}
							hasValue={() => contentWidth !== ''}
							onDeselect={() =>
								setAttributes({ contentWidth: '' })
							}
							isShownByDefault
						>
							<UnitControl
								label={__('Content Width', 'designsetgo')}
								value={contentWidth}
								onChange={(value) =>
									setAttributes({ contentWidth: value })
								}
								placeholder={
									themeContentSize ||
									__('Theme default', 'designsetgo')
								}
								help={
									!contentWidth && themeContentSize
										? sprintf(
												/* translators: %s: theme content size */
												__(
													'Using theme default: %s',
													'designsetgo'
												),
												themeContentSize
											)
										: undefined
								}
								units={[
									{ value: 'px', label: 'px' },
									{ value: 'rem', label: 'rem' },
									{ value: '%', label: '%' },
									{ value: 'vw', label: 'vw' },
								]}
								__next40pxDefaultSize
							/>
						</DsgoInspectorPanel.Item>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Navigation', 'designsetgo')}
					settings={[
						{
							label: __('Navigation Title Color', 'designsetgo'),
							colorValue: decodeColorValue(
								navColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									navColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Active Title Color', 'designsetgo'),
							colorValue: decodeColorValue(
								navActiveColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									navActiveColor:
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
					__experimentalIsRenderedInSidebar
				/>
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Overlay', 'designsetgo')}
					settings={[
						{
							label: __('Overlay Color', 'designsetgo'),
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
					__experimentalIsRenderedInSidebar
				/>
			</InspectorControls>
		</>
	);
}
