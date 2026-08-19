/**
 * Text Reveal Extension - Settings Panel
 *
 * Panel component for configuring text reveal effect
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	RangeControl,
} from '@wordpress/components';
import {
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../../utils/encode-color-value';
/**
 * Text Reveal Settings Panel
 *
 * @param {Object}   props               Component props
 * @param {Object}   props.attributes    Block attributes
 * @param {Function} props.setAttributes Function to update attributes
 * @param {string}   props.clientId      Block client ID for color controls
 * @return {JSX.Element} Text reveal panel component
 */
export default function TextRevealPanel({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		dsgoTextRevealEnabled,
		dsgoTextRevealColor,
		dsgoTextRevealSplitMode,
		dsgoTextRevealTransition,
		dsgoTextRevealEffect,
	} = attributes;

	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Define options inline for proper i18n (can't use __() at module level)
	const splitModeOptions = [
		{ label: __('Word', 'designsetgo'), value: 'word' },
		{ label: __('Character', 'designsetgo'), value: 'character' },
	];

	const effectOptions = [
		{ label: __('Color Sweep', 'designsetgo'), value: 'color' },
		{ label: __('Fade & Rise', 'designsetgo'), value: 'rise' },
	];

	const transitionDurationOptions = [
		{ label: __('Fast (100ms)', 'designsetgo'), value: 100 },
		{ label: __('Normal (150ms)', 'designsetgo'), value: 150 },
		{ label: __('Slow (250ms)', 'designsetgo'), value: 250 },
		{ label: __('Very Slow (400ms)', 'designsetgo'), value: 400 },
	];

	return (
		<>
			{/* Main settings panel */}
			<InspectorControls>
				<PanelBody
					title={__('Text Reveal', 'designsetgo')}
					initialOpen={false}
					icon="visibility"
				>
					<ToggleControl
						label={__('Enable Text Reveal', 'designsetgo')}
						checked={dsgoTextRevealEnabled}
						onChange={(value) =>
							setAttributes({ dsgoTextRevealEnabled: value })
						}
						help={__(
							'Reveal text word by word as users scroll',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>

					{dsgoTextRevealEnabled && (
						<>
							<SelectControl
								label={__('Effect', 'designsetgo')}
								value={dsgoTextRevealEffect}
								options={effectOptions}
								onChange={(value) =>
									setAttributes({
										dsgoTextRevealEffect: value,
									})
								}
								help={__(
									'Recolour each unit, or fade and lift it into place',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<SelectControl
								label={__('Split Mode', 'designsetgo')}
								value={dsgoTextRevealSplitMode}
								options={splitModeOptions}
								onChange={(value) =>
									setAttributes({
										dsgoTextRevealSplitMode: value,
									})
								}
								help={__(
									'Reveal by word or character',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<SelectControl
								label={__('Transition Speed', 'designsetgo')}
								value={dsgoTextRevealTransition}
								options={transitionDurationOptions}
								onChange={(value) =>
									setAttributes({
										dsgoTextRevealTransition: parseInt(
											value,
											10
										),
									})
								}
								help={__(
									'How fast each word/character transitions',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<RangeControl
								label={__(
									'Custom Transition (ms)',
									'designsetgo'
								)}
								value={dsgoTextRevealTransition}
								onChange={(value) =>
									setAttributes({
										dsgoTextRevealTransition: value,
									})
								}
								min={50}
								max={500}
								step={10}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</>
					)}
				</PanelBody>
			</InspectorControls>

			{/* Color controls in the Colors group. The rise effect keeps the
			    block's own text colour, so a reveal colour would do nothing. */}
			{dsgoTextRevealEnabled && dsgoTextRevealEffect !== 'rise' && (
				<InspectorControls group="color">
					<ColorGradientSettingsDropdown
						panelId={clientId}
						title={__('Text Reveal', 'designsetgo')}
						settings={[
							{
								label: __('Reveal Color', 'designsetgo'),
								colorValue: decodeColorValue(
									dsgoTextRevealColor,
									colorGradientSettings
								),
								onColorChange: (color) =>
									setAttributes({
										dsgoTextRevealColor:
											encodeColorValue(
												color,
												colorGradientSettings
											) || '',
									}),
								clearable: true,
								enableAlpha: true,
							},
						]}
						{...colorGradientSettings}
					/>
				</InspectorControls>
			)}
		</>
	);
}
