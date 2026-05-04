/**
 * Overlay Header Panel
 *
 * Adds a sidebar panel to posts/pages for enabling overlay header
 * and choosing the overlay text color.
 *
 * @package
 * @since 2.1.0
 */

import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import {
	ToggleControl,
	Notice,
	ColorPalette,
	BaseControl,
} from '@wordpress/components';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	resolvePresetColorBySlug,
	resolvePresetColorByHex,
} from '../utils/resolve-palette-color';

/**
 * Overlay Header Panel Component
 */
const OverlayHeaderPanel = () => {
	const postType = useSelect(
		(select) => select('core/editor').getCurrentPostType(),
		[]
	);

	const colorGradientSettings = useMultipleOriginColorsAndGradients();
	const originColors = colorGradientSettings?.colors || [];

	const [meta, setMeta] = useEntityProp('postType', postType, 'meta');

	const overlayEnabled = meta?.dsgo_overlay_header || false;
	const textColorSlug = meta?.dsgo_overlay_header_text_color || '';
	const skipTopBarEnabled = meta?.dsgo_overlay_skip_top_bar || false;

	const textColorHex = textColorSlug
		? resolvePresetColorBySlug(originColors, textColorSlug)?.color || ''
		: '';

	const updateOverlay = (value) => {
		const updates = { ...meta, dsgo_overlay_header: value };
		// Clear text color when disabling overlay.
		if (!value) {
			updates.dsgo_overlay_header_text_color = '';
		}
		setMeta(updates);
	};

	const updateTextColor = (hex) => {
		if (!hex) {
			setMeta({ ...meta, dsgo_overlay_header_text_color: '' });
			return;
		}
		const colorObj = resolvePresetColorByHex(originColors, hex);
		setMeta({
			...meta,
			dsgo_overlay_header_text_color: colorObj?.slug || '',
		});
	};

	const updateSkipTopBar = (value) => {
		setMeta({ ...meta, dsgo_overlay_skip_top_bar: value });
	};

	// Only show for content post types.
	if (!postType || postType === 'attachment') {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="dsgo-overlay-header"
			title={__('Header Display', 'designsetgo')}
			className="dsgo-overlay-header-panel"
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={__('Overlay Header', 'designsetgo')}
				help={__(
					'Makes the header transparent and positions it over the page content. Best used with hero sections that have a background image or color.',
					'designsetgo'
				)}
				checked={overlayEnabled}
				onChange={updateOverlay}
			/>
			{overlayEnabled && (
				<>
					<ToggleControl
						__nextHasNoMarginBottom
						label={__('Skip Top Bar', 'designsetgo')}
						help={__(
							'Only overlay the main header row. If your header has a top bar, the hero content will begin below it.',
							'designsetgo'
						)}
						checked={skipTopBarEnabled}
						onChange={updateSkipTopBar}
					/>
					{/* eslint-disable-next-line @wordpress/no-base-control-with-label-without-id -- ColorPalette has no single input to associate */}
					<BaseControl
						__nextHasNoMarginBottom
						label={__('Overlay Text Color', 'designsetgo')}
						help={__(
							'Sets the header text color while the header is transparent. The scroll text color is controlled in the Sticky Header settings.',
							'designsetgo'
						)}
					>
						<ColorPalette
							colors={originColors}
							value={textColorHex}
							onChange={updateTextColor}
							clearable
						/>
					</BaseControl>
					<Notice status="info" isDismissible={false}>
						{__(
							'Preview this page on the frontend to see the overlay effect.',
							'designsetgo'
						)}
					</Notice>
				</>
			)}
		</PluginDocumentSettingPanel>
	);
};

registerPlugin('dsgo-overlay-header', {
	render: OverlayHeaderPanel,
});
