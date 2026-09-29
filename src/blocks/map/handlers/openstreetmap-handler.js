/**
 * OpenStreetMap Handler
 *
 * Handles OpenStreetMap (Leaflet.js) initialization.
 */

import { __ } from '@wordpress/i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Initialize OpenStreetMap with Leaflet for a DSGMap instance.
 *
 * @param {Object} dsgMap - DSGMap instance (this context).
 * @return {Promise} Resolves when map is initialized.
 */
export async function initOpenStreetMap(dsgMap) {
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

	// Initialize Leaflet map
	dsgMap.mapInstance = L.map(container, {
		center: [dsgMap.config.lat, dsgMap.config.lng],
		zoom: dsgMap.config.zoom,
	});

	// Add tile layer
	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		attribution:
			'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		maxZoom: 19,
	}).addTo(dsgMap.mapInstance);

	// Create custom marker (XSS-safe)
	const iconContainer = document.createElement('div');
	iconContainer.textContent = dsgMap.config.markerIcon; // Treats as text, prevents XSS
	iconContainer.style.cssText = `
		font-size: 32px;
		line-height: 1;
		text-align: center;
		filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
	`;

	const customIcon = L.divIcon({
		html: iconContainer.outerHTML,
		className: 'dsgo-map__custom-marker',
		iconSize: [32, 32],
		iconAnchor: [16, 32],
	});

	// Add marker (no popup - removed per user request)
	L.marker([dsgMap.config.lat, dsgMap.config.lng], {
		icon: customIcon,
	}).addTo(dsgMap.mapInstance);
}
