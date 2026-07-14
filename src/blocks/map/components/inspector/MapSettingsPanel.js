/**
 * Map Settings Panel Component
 *
 * Renders DsgoInspectorPanel.Item entries for all map settings.
 * Meant to be composed inside the Settings DsgoInspectorPanel in
 * map/edit.js.
 */

import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	TextControl,
	RangeControl,
	Button,
	Notice,
	ToggleControl,
	TextareaControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useState, useCallback } from '@wordpress/element';
import { DsgoInspectorPanel } from '../../../../components/shared';
import { geocodeAddress } from '../../utils/geocoding';

const DEFAULT_PRIVACY_NOTICE =
	'This map will load content from external services. Click to load and view the map.';

export default function MapSettingsPanel({ attributes, setAttributes }) {
	const {
		dsgoProvider,
		dsgoLatitude,
		dsgoLongitude,
		dsgoZoom,
		dsgoAddress,
		dsgoMarkerIcon,
		dsgoHeight,
		dsgoAspectRatio,
		dsgoMapStyle,
		dsgoPrivacyMode,
		dsgoPrivacyNotice,
	} = attributes;

	const [isSearching, setIsSearching] = useState(false);
	const [searchError, setSearchError] = useState('');

	const handleAddressSearch = useCallback(async () => {
		if (!dsgoAddress || dsgoAddress.trim() === '') {
			setSearchError(
				__('Please enter an address to search.', 'designsetgo')
			);
			return;
		}

		setIsSearching(true);
		setSearchError('');

		try {
			const result = await geocodeAddress(dsgoAddress);

			if (result) {
				setAttributes({
					dsgoLatitude: result.lat,
					dsgoLongitude: result.lng,
					dsgoAddress: result.display_name,
				});
			} else {
				setSearchError(
					__(
						'Address not found. Please try a different search.',
						'designsetgo'
					)
				);
			}
		} catch (error) {
			setSearchError(
				__('Failed to search address. Please try again.', 'designsetgo')
			);
		} finally {
			setIsSearching(false);
		}
	}, [dsgoAddress, setAttributes]);

	const handleAddressKeyPress = (event) => {
		if (event.key === 'Enter') {
			event.preventDefault();
			handleAddressSearch();
		}
	};

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Map Provider', 'designsetgo')}
				hasValue={() => dsgoProvider !== 'openstreetmap'}
				onDeselect={() =>
					setAttributes({ dsgoProvider: 'openstreetmap' })
				}
				isShownByDefault
			>
				<SelectControl
					label={__('Map Provider', 'designsetgo')}
					value={dsgoProvider}
					options={[
						{
							label: __(
								'OpenStreetMap (No API key required)',
								'designsetgo'
							),
							value: 'openstreetmap',
						},
						{
							label: __(
								'Google Maps (Requires API key)',
								'designsetgo'
							),
							value: 'googlemaps',
						},
					]}
					onChange={(value) => setAttributes({ dsgoProvider: value })}
					help={
						dsgoProvider === 'openstreetmap'
							? __(
									'Privacy-friendly and free to use.',
									'designsetgo'
								)
							: __(
									'Requires a Google Maps API key.',
									'designsetgo'
								)
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				{dsgoProvider === 'googlemaps' &&
					// eslint-disable-next-line camelcase
					(window.dsgo_integrations?.googleMapsApiKey ? (
						<Notice
							status="success"
							isDismissible={false}
							style={{ marginTop: '12px' }}
						>
							{__(
								'✓ Google Maps API key configured in',
								'designsetgo'
							)}
							<a
								href="/wp-admin/admin.php?page=designsetgo-settings"
								target="_blank"
								rel="noopener noreferrer"
							>
								{__('Settings', 'designsetgo')}
							</a>
							.
						</Notice>
					) : (
						<Notice
							status="warning"
							isDismissible={false}
							style={{ marginTop: '12px' }}
						>
							<strong>
								{__('⚠ No API key configured.', 'designsetgo')}
							</strong>{' '}
							{__('Add a Google Maps API key in', 'designsetgo')}
							<a
								href="/wp-admin/admin.php?page=designsetgo-settings"
								target="_blank"
								rel="noopener noreferrer"
							>
								{__('Settings', 'designsetgo')}
							</a>{' '}
							{__('to use Google Maps.', 'designsetgo')}
						</Notice>
					))}
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Address', 'designsetgo')}
				hasValue={() => dsgoAddress !== ''}
				onDeselect={() => setAttributes({ dsgoAddress: '' })}
				isShownByDefault
			>
				<TextControl
					label={__('Search Address', 'designsetgo')}
					value={dsgoAddress}
					onChange={(value) => {
						setAttributes({ dsgoAddress: value });
						setSearchError('');
					}}
					onKeyPress={handleAddressKeyPress}
					placeholder={__(
						'Enter an address or location',
						'designsetgo'
					)}
					help={__(
						'Search for a location to automatically set coordinates.',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>

				<Button
					variant="secondary"
					onClick={handleAddressSearch}
					isBusy={isSearching}
					disabled={!dsgoAddress || isSearching}
					style={{ marginTop: '8px' }}
				>
					{isSearching
						? __('Searching…', 'designsetgo')
						: __('Search Address', 'designsetgo')}
				</Button>

				{searchError && (
					<Notice
						status="error"
						isDismissible={false}
						style={{ marginTop: '12px' }}
					>
						{searchError}
					</Notice>
				)}
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Latitude', 'designsetgo')}
				hasValue={() => dsgoLatitude !== 40.7128}
				onDeselect={() => setAttributes({ dsgoLatitude: 40.7128 })}
				isShownByDefault
			>
				<TextControl
					label={__('Latitude', 'designsetgo')}
					type="number"
					value={dsgoLatitude}
					onChange={(value) => {
						const num = parseFloat(value);
						const clamped = Number.isFinite(num)
							? Math.max(-90, Math.min(90, num))
							: 0;
						setAttributes({ dsgoLatitude: clamped });
					}}
					step="0.000001"
					min="-90"
					max="90"
					help={__(
						'Manual coordinate entry (between -90 and 90).',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Longitude', 'designsetgo')}
				hasValue={() => dsgoLongitude !== -74.006}
				onDeselect={() => setAttributes({ dsgoLongitude: -74.006 })}
				isShownByDefault
			>
				<TextControl
					label={__('Longitude', 'designsetgo')}
					type="number"
					value={dsgoLongitude}
					onChange={(value) => {
						const num = parseFloat(value);
						const clamped = Number.isFinite(num)
							? Math.max(-180, Math.min(180, num))
							: 0;
						setAttributes({ dsgoLongitude: clamped });
					}}
					step="0.000001"
					min="-180"
					max="180"
					help={__(
						'Manual coordinate entry (between -180 and 180).',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Zoom Level', 'designsetgo')}
				hasValue={() => dsgoZoom !== 13}
				onDeselect={() => setAttributes({ dsgoZoom: 13 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Zoom Level', 'designsetgo')}
					value={dsgoZoom}
					onChange={(value) => setAttributes({ dsgoZoom: value })}
					min={1}
					max={20}
					step={1}
					help={__(
						'1 = world view, 20 = street level.',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Marker Icon', 'designsetgo')}
				hasValue={() => dsgoMarkerIcon !== '📍'}
				onDeselect={() => setAttributes({ dsgoMarkerIcon: '📍' })}
				isShownByDefault
			>
				<TextControl
					label={__('Marker Icon', 'designsetgo')}
					value={dsgoMarkerIcon}
					onChange={(value) =>
						setAttributes({ dsgoMarkerIcon: value || '📍' })
					}
					help={__(
						'Enter an emoji or icon character.',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Aspect Ratio', 'designsetgo')}
				hasValue={() => dsgoAspectRatio !== 'custom'}
				onDeselect={() => setAttributes({ dsgoAspectRatio: 'custom' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Aspect Ratio', 'designsetgo')}
					value={dsgoAspectRatio}
					options={[
						{
							label: __('16:9 (Widescreen)', 'designsetgo'),
							value: '16:9',
						},
						{
							label: __('4:3 (Standard)', 'designsetgo'),
							value: '4:3',
						},
						{
							label: __('1:1 (Square)', 'designsetgo'),
							value: '1:1',
						},
						{
							label: __('Custom Height', 'designsetgo'),
							value: 'custom',
						},
					]}
					onChange={(value) =>
						setAttributes({ dsgoAspectRatio: value })
					}
					help={
						dsgoAspectRatio === 'custom'
							? __('Set a custom height below.', 'designsetgo')
							: __(
									'Maintains aspect ratio across screen sizes.',
									'designsetgo'
								)
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{dsgoAspectRatio === 'custom' && (
				<DsgoInspectorPanel.Item
					label={__('Map Height', 'designsetgo')}
					hasValue={() => dsgoHeight !== '400px'}
					onDeselect={() => setAttributes({ dsgoHeight: '400px' })}
					isShownByDefault
				>
					<UnitControl
						label={__('Map Height', 'designsetgo')}
						value={dsgoHeight}
						onChange={(value) =>
							setAttributes({ dsgoHeight: value || '400px' })
						}
						units={[
							{ value: 'px', label: 'px' },
							{ value: '%', label: '%' },
							{ value: 'vh', label: 'vh' },
						]}
						help={__(
							'Set a custom height for the map.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{dsgoProvider === 'googlemaps' && (
				<DsgoInspectorPanel.Item
					label={__('Map Style', 'designsetgo')}
					hasValue={() => dsgoMapStyle !== 'standard'}
					onDeselect={() =>
						setAttributes({ dsgoMapStyle: 'standard' })
					}
					isShownByDefault
				>
					<SelectControl
						label={__('Map Style', 'designsetgo')}
						value={dsgoMapStyle}
						options={[
							{
								label: __('Standard', 'designsetgo'),
								value: 'standard',
							},
							{
								label: __('Silver (Minimalist)', 'designsetgo'),
								value: 'silver',
							},
							{
								label: __('Dark Mode', 'designsetgo'),
								value: 'dark',
							},
						]}
						onChange={(value) =>
							setAttributes({ dsgoMapStyle: value })
						}
						help={__(
							'Choose a visual style for Google Maps.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			<DsgoInspectorPanel.Item
				label={__('Enable Privacy Mode', 'designsetgo')}
				hasValue={() => dsgoPrivacyMode !== false}
				onDeselect={() => setAttributes({ dsgoPrivacyMode: false })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Enable Privacy Mode', 'designsetgo')}
					checked={dsgoPrivacyMode}
					onChange={(value) =>
						setAttributes({ dsgoPrivacyMode: value })
					}
					help={
						dsgoPrivacyMode
							? __(
									'Map will not load until user clicks to consent.',
									'designsetgo'
								)
							: __(
									'Map will load automatically when page loads.',
									'designsetgo'
								)
					}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{dsgoPrivacyMode && (
				<DsgoInspectorPanel.Item
					label={__('Privacy Notice', 'designsetgo')}
					hasValue={() =>
						dsgoPrivacyNotice !== DEFAULT_PRIVACY_NOTICE
					}
					onDeselect={() =>
						setAttributes({
							dsgoPrivacyNotice: DEFAULT_PRIVACY_NOTICE,
						})
					}
					isShownByDefault
				>
					<TextareaControl
						label={__('Privacy Notice', 'designsetgo')}
						value={dsgoPrivacyNotice}
						onChange={(value) =>
							setAttributes({ dsgoPrivacyNotice: value })
						}
						rows={4}
						help={__(
							'Message shown to users before loading the map.',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}
		</>
	);
}
