/**
 * Google Maps Handler
 *
 * Handles Google Maps initialization and styling.
 */

import { __ } from '@wordpress/i18n';
import { loadGoogleMaps } from '../utils/script-loader';

/**
 * Google Maps style configurations.
 * Complete styles that properly show roads, water, and terrain.
 */
export const GOOGLE_MAP_STYLES = {
	silver: [
		{ elementType: 'geometry', stylers: [{ color: '#f5f5f5' }] },
		{ elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
		{ elementType: 'labels.text.fill', stylers: [{ color: '#616161' }] },
		{ elementType: 'labels.text.stroke', stylers: [{ color: '#f5f5f5' }] },
		{
			featureType: 'road',
			elementType: 'geometry',
			stylers: [{ color: '#ffffff' }],
		},
		{
			featureType: 'road',
			elementType: 'geometry.stroke',
			stylers: [{ color: '#d6d6d6' }],
		},
		{
			featureType: 'road.highway',
			elementType: 'geometry',
			stylers: [{ color: '#dadada' }],
		},
		{
			featureType: 'road.highway',
			elementType: 'geometry.stroke',
			stylers: [{ color: '#b3b3b3' }],
		},
		{
			featureType: 'water',
			elementType: 'geometry',
			stylers: [{ color: '#c9c9c9' }],
		},
		{
			featureType: 'water',
			elementType: 'labels.text.fill',
			stylers: [{ color: '#9e9e9e' }],
		},
		{
			featureType: 'poi',
			elementType: 'geometry',
			stylers: [{ color: '#eeeeee' }],
		},
		{
			featureType: 'poi.park',
			elementType: 'geometry',
			stylers: [{ color: '#e5e5e5' }],
		},
	],
	dark: [
		{ elementType: 'geometry', stylers: [{ color: '#212121' }] },
		{ elementType: 'labels.text.fill', stylers: [{ color: '#757575' }] },
		{ elementType: 'labels.text.stroke', stylers: [{ color: '#212121' }] },
		{
			featureType: 'road',
			elementType: 'geometry',
			stylers: [{ color: '#2c2c2c' }],
		},
		{
			featureType: 'road',
			elementType: 'geometry.stroke',
			stylers: [{ color: '#212121' }],
		},
		{
			featureType: 'road',
			elementType: 'labels.text.fill',
			stylers: [{ color: '#8a8a8a' }],
		},
		{
			featureType: 'road.highway',
			elementType: 'geometry',
			stylers: [{ color: '#3c3c3c' }],
		},
		{
			featureType: 'road.highway',
			elementType: 'geometry.stroke',
			stylers: [{ color: '#1c1c1c' }],
		},
		{
			featureType: 'water',
			elementType: 'geometry',
			stylers: [{ color: '#000000' }],
		},
		{
			featureType: 'water',
			elementType: 'labels.text.fill',
			stylers: [{ color: '#3d3d3d' }],
		},
		{
			featureType: 'poi',
			elementType: 'geometry',
			stylers: [{ color: '#263238' }],
		},
		{
			featureType: 'poi.park',
			elementType: 'geometry',
			stylers: [{ color: '#181818' }],
		},
		{
			featureType: 'poi.park',
			elementType: 'labels.text.fill',
			stylers: [{ color: '#616161' }],
		},
	],
};

/**
 * Initialize Google Maps for a DSGMap instance.
 * Uses modern dynamic library import pattern.
 *
 * @param {Object} dsgMap - DSGMap instance (this context).
 * @return {Promise} Resolves when map is initialized.
 */
export async function initGoogleMap(dsgMap) {
	// Load Google Maps bootstrap
	await loadGoogleMaps(dsgMap.config.apiKey);

	// Dynamically import the Maps and Marker libraries (modern async pattern)
	const { Map } = await window.google.maps.importLibrary('maps');
	const { AdvancedMarkerElement, PinElement } =
		await window.google.maps.importLibrary('marker');

	// Get or create map container
	let container = dsgMap.element.querySelector('.dsgo-map__container');
	if (!container) {
		container = document.createElement('div');
		container.className = 'dsgo-map__container';
		container.setAttribute('role', 'region');
		container.setAttribute(
			'aria-label',
			dsgMap.config.label || __('Interactive map', 'designsetgo')
		);
		dsgMap.element.appendChild(container);
	}

	// Map options (need mapId for advanced markers)
	const mapOptions = {
		center: { lat: dsgMap.config.lat, lng: dsgMap.config.lng },
		zoom: dsgMap.config.zoom,
		mapId: 'dsgo-map-' + Math.random().toString(36).substr(2, 9), // Unique ID for advanced markers
	};

	// Apply style if not standard
	if (
		dsgMap.config.mapStyle !== 'standard' &&
		GOOGLE_MAP_STYLES[dsgMap.config.mapStyle]
	) {
		mapOptions.styles = GOOGLE_MAP_STYLES[dsgMap.config.mapStyle];
	}

	// Initialize Google Map
	dsgMap.mapInstance = new Map(container, mapOptions);

	// Create marker with standard Google Maps pin (customizable color only)
	const pinElement = new PinElement({
		background: dsgMap.config.markerColor || '#e74c3c',
		borderColor: '#ffffff',
		glyphColor: '#ffffff',
		scale: 1.2,
	});

	// Add marker using AdvancedMarkerElement (no popup - removed per user request)
	new AdvancedMarkerElement({
		map: dsgMap.mapInstance,
		position: { lat: dsgMap.config.lat, lng: dsgMap.config.lng },
		content: pinElement.element,
	});
}
