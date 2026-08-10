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

/**
 * Overlay Header Panel Component
 */
const OverlayHeaderPanel = () => {
	const postType = useSelect(
		(select) => select('core/editor').getCurrentPostType(),
		[]
	);

	const colors = useSelect((select) => {
		const settings = select('core/block-editor').getSettings();
		return settings.colors || [];
	}, []);

	const [meta, setMeta] = useEntityProp('postType', postType, 'meta');

	const overlayEnabled = meta?.dsgo_overlay_header || false;
	const textColorSlug = meta?.dsgo_overlay_header_text_color || '';
	const skipTopBarEnabled = meta?.dsgo_overlay_skip_top_bar || false;

	// Convert slug to hex for ColorPalette display.
	const textColorHex = textColorSlug
		? colors.find((c) => c.slug === textColorSlug)?.color || ''
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
		const colorObj = colors.find((c) => c.color === hex);
		setMeta({
			...meta,
			dsgo_overlay_header_text_color: colorObj?.slug || '',
		});
	};

	const updateSkipTopBar = (value) => {
		setMeta({ ...meta, dsgo_overlay_skip_top_bar: value });
	};

	// Only show for pages — template parts and other post types don't support meta.
	if (postType !== 'page') {
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
							colors={colors}
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
