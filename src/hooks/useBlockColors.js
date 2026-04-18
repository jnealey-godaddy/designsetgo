/**
 * useBlockColors
 *
 * Wraps the encode/decode boilerplate that surrounds every
 * ColorGradientSettingsDropdown in the plugin (~26 blocks today).
 * Returns a `settings` array shaped for direct passing to
 * ColorGradientSettingsDropdown's `settings` prop, plus the spreadable
 * colorGradientSettings object.
 *
 * Usage:
 *
 * const { settings, colorGradientSettings } = useBlockColors({
 *   attributes,
 *   setAttributes,
 *   entries: [
 *     { label: __('Background', 'designsetgo'), attribute: 'backgroundColor' },
 *     { label: __('Text', 'designsetgo'), attribute: 'textColor' },
 *   ],
 * });
 *
 * <ColorGradientSettingsDropdown
 *   panelId={clientId}
 *   title={__('Colors', 'designsetgo')}
 *   settings={settings}
 *   {...colorGradientSettings}
 * />
 *
 * @param {Object}   params
 * @param {Object}   params.attributes    Block attributes.
 * @param {Function} params.setAttributes Block setAttributes.
 * @param {Array}    params.entries       Color entries: { label, attribute, enableAlpha?, clearable? }.
 * @return {Object} { settings, colorGradientSettings }
 */
import { useMultipleOriginColorsAndGradients } from '@wordpress/block-editor';
import {
	encodeColorValue,
	decodeColorValue,
} from '../utils/encode-color-value';

export function useBlockColors({ attributes, setAttributes, entries }) {
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	const settings = entries.map(
		({ label, attribute, enableAlpha = true, clearable = true }) => ({
			label,
			colorValue: decodeColorValue(
				attributes[attribute],
				colorGradientSettings
			),
			onColorChange: (color) =>
				setAttributes({
					[attribute]:
						encodeColorValue(color, colorGradientSettings) || '',
				}),
			enableAlpha,
			clearable,
		})
	);

	return { settings, colorGradientSettings };
}
