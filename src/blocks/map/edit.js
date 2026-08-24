/**
 * Map Block - Editor Component
 *
 * Renders the map block in the WordPress editor with inspector controls.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import classnames from 'classnames';

import { DsgoInspectorPanel } from '../../components/shared';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
// Inspector Panel
import MapSettingsPanel from './components/inspector/MapSettingsPanel';
import { buildEmbedUrl } from './utils/embed-url';

/**
 * Edit component for Map block.
 *
 * @param {Object}   props               - Component props.
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Function to update attributes.
 * @param {string}   props.clientId      - Block client ID.
 * @return {JSX.Element} Editor component.
 */
export default function Edit({ attributes, setAttributes, clientId }) {
	const {
		dsgoProvider,
		dsgoLatitude,
		dsgoLongitude,
		dsgoZoom,
		dsgoAddress,
		dsgoMarkerColor,
		dsgoAspectRatio,
		dsgoHeight,
		dsgoPrivacyMode,
		dsgoPrivacyNotice,
	} = attributes;

	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Google draws its own pin in embed mode, so the marker colour control has
	// nothing to act on. Mirrors the same gate in MapSettingsPanel.
	const isEmbedProvider = dsgoProvider === 'googlemaps-embed';

	// Compute block classes
	const blockClasses = classnames('dsgo-map', {
		'dsgo-map--privacy-mode': dsgoPrivacyMode,
		[`dsgo-map--aspect-${dsgoAspectRatio.replace(':', '-')}`]:
			dsgoAspectRatio !== 'custom',
	});

	// Custom styles for the map container
	const mapStyles = {};

	// Apply aspect ratio or custom height
	if (dsgoAspectRatio !== 'custom') {
		// Aspect ratio will be handled by CSS
	} else {
		mapStyles.height = dsgoHeight;
	}

	const blockProps = useBlockProps({
		className: blockClasses,
		style: mapStyles,
	});

	// Embed mode is a real Google iframe, so the editor can show the actual
	// map. The other providers are initialized by view.js on the front end
	// only, which is why they get a placeholder card here instead.
	const renderPreview = () => {
		if (dsgoPrivacyMode) {
			return (
				<div className="dsgo-map__privacy-overlay">
					<div className="dsgo-map__privacy-content">
						<svg
							className="dsgo-map__privacy-icon"
							xmlns="http://www.w3.org/2000/svg"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="2"
							strokeLinecap="round"
							strokeLinejoin="round"
						>
							<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
							<circle cx="12" cy="10" r="3" />
						</svg>
						<p className="dsgo-map__privacy-text">
							{dsgoPrivacyNotice ||
								__('Click to load map', 'designsetgo')}
						</p>
						<button
							className="dsgo-map__load-button"
							type="button"
							onClick={(e) => e.preventDefault()}
						>
							{__('Load Map', 'designsetgo')}
						</button>
						<p className="dsgo-map__preview-note">
							{__(
								'Preview: Privacy mode is enabled',
								'designsetgo'
							)}
						</p>
					</div>
				</div>
			);
		}

		if (isEmbedProvider) {
			return (
				<iframe
					className="dsgo-map__iframe"
					src={buildEmbedUrl(
						dsgoAddress,
						dsgoLatitude,
						dsgoLongitude,
						dsgoZoom
					)}
					title={__('Map preview', 'designsetgo')}
					loading="lazy"
					referrerPolicy="no-referrer-when-downgrade"
				/>
			);
		}

		return (
			<div className="dsgo-map__preview-placeholder">
				<div className="dsgo-map__preview-info">
					<svg
						className="dsgo-map__preview-icon"
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
						strokeWidth="2"
						strokeLinecap="round"
						strokeLinejoin="round"
					>
						<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
						<circle cx="12" cy="10" r="3" />
					</svg>
					<div className="dsgo-map__preview-details">
						<strong>{__('Map Preview', 'designsetgo')}</strong>
						<div className="dsgo-map__preview-coords">
							{dsgoLatitude === 0 &&
							dsgoLongitude === 0 &&
							dsgoAddress ? (
								<span>{dsgoAddress}</span>
							) : (
								<code>
									{dsgoLatitude.toFixed(6)},{' '}
									{dsgoLongitude.toFixed(6)}
								</code>
							)}
						</div>
						<div className="dsgo-map__preview-meta">
							{dsgoProvider === 'openstreetmap'
								? __('OpenStreetMap', 'designsetgo')
								: __('Google Maps', 'designsetgo')}{' '}
							• {__('Zoom:', 'designsetgo')} {dsgoZoom}
						</div>
					</div>
				</div>
				<div className="dsgo-map__preview-note">
					{__(
						'Interactive map will display on the frontend',
						'designsetgo'
					)}
				</div>
			</div>
		);
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
							dsgoProvider: 'openstreetmap',
							dsgoLatitude: 40.7128,
							dsgoLongitude: -74.006,
							dsgoZoom: 13,
							dsgoAddress: '',
							dsgoMarkerIcon: '📍',
							dsgoHeight: '400px',
							dsgoAspectRatio: 'custom',
							dsgoMapStyle: 'standard',
							dsgoPrivacyMode: false,
							dsgoPrivacyNotice:
								'This map will load content from external services. Click to load and view the map.',
						})
					}
				>
					<MapSettingsPanel
						attributes={attributes}
						setAttributes={setAttributes}
					/>
				</DsgoInspectorPanel>
			</InspectorControls>

			{!isEmbedProvider && (
				<InspectorControls group="color">
					<ColorGradientSettingsDropdown
						panelId={clientId}
						title={__('Google Maps Marker Color', 'designsetgo')}
						settings={[
							{
								// Only the Google Maps provider draws a recolorable
								// pin (PinElement); the OpenStreetMap marker is the
								// Marker Icon emoji as-is, which CSS color can't
								// change. Label makes that scope clear in the UI.
								label: __(
									'Google Maps Marker Color',
									'designsetgo'
								),
								colorValue: decodeColorValue(
									dsgoMarkerColor,
									colorGradientSettings
								),
								onColorChange: (color) =>
									setAttributes({
										// Store the preset reference for palette
										// picks; on clear, store '' so render.php's
										// attribute → kit setting → default fallback
										// chain drives the color instead of baking a
										// hex into the block.
										dsgoMarkerColor: color
											? encodeColorValue(
													color,
													colorGradientSettings
												)
											: '',
									}),
								enableAlpha: true,
								clearable: true,
							},
						]}
						{...colorGradientSettings}
					/>
				</InspectorControls>
			)}

			<div {...blockProps}>
				<div className="dsgo-map__editor-preview">
					{renderPreview()}
				</div>
			</div>
		</>
	);
}
