/**
 * Chart Block - Series colour controls
 *
 * @package
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export; the canonical colour control across this plugin.
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export.
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../../utils/encode-color-value';

/**
 * Replace one slot of the palette without disturbing the others.
 *
 * The palette is positional — index N colours series N — so setting a later
 * series must pad the gaps rather than shift earlier colours along.
 *
 * @param {Array}  palette Current palette.
 * @param {number} index   Slot to write.
 * @param {string} value   Encoded colour, or '' to clear.
 * @param {number} length  Number of series.
 * @return {Array} The next palette.
 */
export function setPaletteSlot(palette, index, value, length) {
	const next = Array.from({ length }, (_, i) =>
		Array.isArray(palette) && palette[i] ? palette[i] : ''
	);

	next[index] = value || '';

	// Trailing empties carry no meaning; drop them so the attribute stays at
	// its default (an empty array) when every colour is cleared.
	while (next.length && '' === next[next.length - 1]) {
		next.pop();
	}

	return next;
}

/**
 * One colour control per data row, in the standard Color inspector group.
 *
 * @param {Object}   props          Component props.
 * @param {Array}    props.rows     Chart rows.
 * @param {Array}    props.palette  Current palette.
 * @param {Function} props.onChange Receives the next palette.
 * @param {string}   props.clientId Block client id.
 * @return {Element|null} The controls, or null when there is no data yet.
 */
export function SeriesColors({ rows, palette, onChange, clientId }) {
	const colorGradientSettings = useMultipleOriginColorsAndGradients();
	const series = Array.isArray(rows) ? rows : [];

	if (!series.length) {
		return null;
	}

	const settings = series.map((row, index) => ({
		label:
			row?.label ||
			// translators: %d: series number.
			sprintf(__('Series %d', 'designsetgo'), index + 1),
		colorValue: decodeColorValue(
			Array.isArray(palette) ? palette[index] : undefined,
			colorGradientSettings
		),
		onColorChange: (color) =>
			onChange(
				setPaletteSlot(
					palette,
					index,
					encodeColorValue(color, colorGradientSettings) || '',
					series.length
				)
			),
		enableAlpha: true,
		clearable: true,
	}));

	return (
		<InspectorControls group="color">
			<ColorGradientSettingsDropdown
				panelId={clientId}
				title={__('Series', 'designsetgo')}
				settings={settings}
				{...colorGradientSettings}
			/>
		</InspectorControls>
	);
}
