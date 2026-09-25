/**
 * Slider — inspector controls.
 *
 * Composes the Settings and Style DsgoInspectorPanel surfaces plus the
 * color group (Arrow Colors, Dot Color). Anchor/class/HTML-element live in
 * `group="advanced"` via block supports and are not duplicated here.
 */

import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
} from '@wordpress/block-editor';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../../../utils/encode-color-value';
import { useBlockColors } from '../../../../hooks';
import SettingsPanel from './SettingsPanel';
import StylePanel from './StylePanel';

/**
 * @param {Object}   props                           Component props.
 * @param {Object}   props.attributes                Block attributes.
 * @param {Function} props.setAttributes             Attribute setter.
 * @param {string}   props.clientId                  Block client id.
 * @param {Function} props.onEffectChange            Transition Effect
 *                                                   change handler
 *                                                   (also clamps
 *                                                   slides-per-view).
 * @param {boolean}  props.requiresSingleSlideEffect Whether the current
 *                                                   effect forces one
 *                                                   slide per view.
 * @param {string}   props.singleSlideNotice         Notice text shown
 *                                                   when locked to a
 *                                                   single slide.
 * @param {Element}  [props.notices]                 Notices shown at the
 *                                                   top of Settings
 *                                                   (Dynamic Query mode).
 * @return {JSX.Element} Inspector controls.
 */
export default function SliderInspector({
	attributes,
	setAttributes,
	clientId,
	onEffectChange,
	requiresSingleSlideEffect,
	singleSlideNotice,
	notices,
}) {
	const { showArrows, showDots, dotColor } = attributes;

	// Arrow Colors panel; colorGradientSettings (same shape as
	// useMultipleOriginColorsAndGradients) is reused below for Dot Color,
	// which isn't a fixed useBlockColors entry because it renders in its
	// own dropdown only while dots are shown.
	const { settings: arrowColorSettings, colorGradientSettings } =
		useBlockColors({
			attributes,
			setAttributes,
			entries: [
				{
					label: __('Arrow Icon Color', 'designsetgo'),
					attribute: 'arrowColor',
				},
				{
					label: __('Arrow Background', 'designsetgo'),
					attribute: 'arrowBackgroundColor',
				},
			],
		});

	return (
		<>
			<SettingsPanel
				attributes={attributes}
				setAttributes={setAttributes}
				clientId={clientId}
				onEffectChange={onEffectChange}
				requiresSingleSlideEffect={requiresSingleSlideEffect}
				singleSlideNotice={singleSlideNotice}
				notices={notices}
			/>

			<StylePanel
				attributes={attributes}
				setAttributes={setAttributes}
				clientId={clientId}
			/>

			{showArrows && (
				<InspectorControls group="color">
					<ColorGradientSettingsDropdown
						panelId={clientId}
						title={__('Arrow Colors', 'designsetgo')}
						settings={arrowColorSettings}
						{...colorGradientSettings}
					/>
				</InspectorControls>
			)}

			{showDots && (
				<InspectorControls group="color">
					<ColorGradientSettingsDropdown
						panelId={clientId}
						title={__('Dot Color', 'designsetgo')}
						settings={[
							{
								label: __('Dot Color', 'designsetgo'),
								colorValue: decodeColorValue(
									dotColor,
									colorGradientSettings
								),
								onColorChange: (color) =>
									setAttributes({
										dotColor:
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
			)}
		</>
	);
}
