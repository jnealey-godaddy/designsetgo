/**
 * DSGMap Class - Main map instance manager.
 *
 * Manages individual map instances, configuration parsing, privacy mode, and initialization.
 */

import { initOpenStreetMap } from './openstreetmap-handler';
import { initGoogleMap } from './googlemaps-handler';
import { __ } from '@wordpress/i18n';
import { geocodeAddress } from '../utils/geocoding';

/**
 * DSGMap Class - Manages individual map instances.
 */
export default class DSGMap {
	/**
	 * Constructor.
	 *
	 * @param {HTMLElement} element - Map block element.
	 */
	constructor(element) {
		this.element = element;
		this.config = this.parseConfig();
		this.mapInstance = null;
		this.isLoading = false;
		this.isLoaded = false;
		this.loadMapBound = this.loadMap.bind(this);

		if (this.config.privacyMode) {
			this.setupPrivacyMode();
		} else {
			this.loadMap();
		}
	}

	/**
	 * Parse configuration from data attributes with validation.
	 *
	 * @return {Object} Parsed and validated configuration.
	 */
	parseConfig() {
		const dataset = this.element.dataset;

		// Parse and validate coordinates
		const lat = parseFloat(dataset.dsgoLat);
		const lng = parseFloat(dataset.dsgoLng);
		const zoom = parseInt(dataset.dsgoZoom);

		return {
			provider: dataset.dsgoProvider || 'openstreetmap',
			// Clamp latitude between -90 and 90
			lat: Number.isFinite(lat) ? Math.max(-90, Math.min(90, lat)) : 0,
			// Clamp longitude between -180 and 180
			lng: Number.isFinite(lng) ? Math.max(-180, Math.min(180, lng)) : 0,
			// Clamp zoom between 1 and 20
			zoom: Number.isInteger(zoom) && zoom >= 1 && zoom <= 20 ? zoom : 13,
			address: dataset.dsgoAddress || '',
			markerIcon: dataset.dsgoMarkerIcon || '📍',
			markerColor: dataset.dsgoMarkerColor || '#e74c3c',
			markerPopup: dataset.dsgoMarkerPopup || '',
			grayscale: dataset.dsgoGrayscale === 'true',
			privacyMode: dataset.dsgoPrivacyMode === 'true',
			apiKey: dataset.dsgoApiKey || '',
			mapStyle: dataset.dsgoMapStyle || 'standard',
			// Localized region label (privacy mode only; see render.php).
			label: dataset.dsgoMapLabel || '',
		};
	}

	/**
	 * Setup privacy mode (click-to-load).
	 */
	setupPrivacyMode() {
		const button = this.element.querySelector('.dsgo-map__load-button');
		if (!button) {
			return;
		}

		button.addEventListener('click', this.loadMapBound);
	}

	/**
	 * Load and initialize the map.
	 * If lat/lng are both 0 and an address is present, geocode the address first.
	 * This allows address-only block markup (e.g. LLM-generated) to work without coordinates.
	 */
	async loadMap() {
		// A double click in privacy mode (or a repeat call) must not start a
		// second load while the geocode/script await is in flight.
		if (this.isLoading || this.isLoaded) {
			return;
		}
		this.isLoading = true;

		try {
			// The keyless Google provider is a server-rendered iframe. In privacy
			// mode render.php parks the URL in data-dsgo-src so nothing reaches
			// Google until now; there is no JS map library to initialize.
			if (this.config.provider === 'googlemaps-embed') {
				this.revealEmbed();
				this.isLoaded = true;
				return;
			}

			if (
				this.config.lat === 0 &&
				this.config.lng === 0 &&
				this.config.address
			) {
				const result = await geocodeAddress(this.config.address);
				if (result) {
					this.config.lat = result.lat;
					this.config.lng = result.lng;
				} else {
					// Don't pin a normal-looking marker on a wrong place.
					this.showError(
						__(
							'The map location could not be found for this address.',
							'designsetgo'
						)
					);
					return;
				}
			}

			if (this.config.provider === 'googlemaps') {
				await initGoogleMap(this);
			} else {
				await initOpenStreetMap(this);
			}
			this.isLoaded = true;

			// Hide privacy overlay if present
			const overlay = this.element.querySelector(
				'.dsgo-map__privacy-overlay'
			);
			if (overlay) {
				overlay.style.display = 'none';
			}

			// Focus management for accessibility
			const container = this.element.querySelector(
				'.dsgo-map__container'
			);
			if (container && this.config.privacyMode) {
				// Make container focusable and move focus to it
				container.setAttribute('tabindex', '-1');
				container.focus();
			}
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Failed to load map:', error);
			this.showError(
				__(
					'Failed to load map. Please check your settings.',
					'designsetgo'
				)
			);
		} finally {
			this.isLoading = false;
		}
	}

	/**
	 * Reveal a parked iframe embed and hide the privacy overlay.
	 */
	revealEmbed() {
		const iframe = this.element.querySelector('.dsgo-map__iframe');
		if (iframe && iframe.dataset.dsgoSrc) {
			iframe.src = iframe.dataset.dsgoSrc;
			delete iframe.dataset.dsgoSrc;
		}

		const overlay = this.element.querySelector(
			'.dsgo-map__privacy-overlay'
		);
		if (overlay) {
			overlay.style.display = 'none';
		}

		// Move focus into the map the visitor just asked for.
		if (iframe && this.config.privacyMode) {
			iframe.focus();
		}
	}

	/**
	 * Show error message.
	 *
	 * @param {string} message - Error message to display.
	 */
	showError(message) {
		const errorDiv = document.createElement('div');
		errorDiv.className = 'dsgo-map__error';
		errorDiv.setAttribute('role', 'alert');
		errorDiv.textContent = message;
		errorDiv.style.cssText = `
			padding: 20px;
			text-align: center;
			color: #d63638;
			background: #fef7f7;
			border: 1px solid #f0c0c0;
			border-radius: 4px;
		`;

		// Hide privacy overlay if present
		const overlay = this.element.querySelector(
			'.dsgo-map__privacy-overlay'
		);
		if (overlay) {
			overlay.style.display = 'none';
		}

		this.element.appendChild(errorDiv);
	}

	/**
	 * Cleanup and destroy the map instance.
	 * Removes event listeners and frees memory.
	 */
	destroy() {
		// Remove privacy mode event listener
		if (this.config.privacyMode) {
			const button = this.element.querySelector('.dsgo-map__load-button');
			if (button) {
				button.removeEventListener('click', this.loadMapBound);
			}
		}

		// Clean up map instance
		if (this.mapInstance) {
			try {
				// Leaflet cleanup (OpenStreetMap)
				if (
					this.config.provider === 'openstreetmap' &&
					this.mapInstance.remove
				) {
					this.mapInstance.remove();
				}
				// Google Maps cleanup
				else if (
					this.config.provider === 'googlemaps' &&
					window.google?.maps
				) {
					// Google Maps doesn't have a destroy method, just null the reference
					// The garbage collector will handle cleanup
				}
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Error cleaning up map:', error);
			}

			this.mapInstance = null;
		}

		// Clear element reference
		this.element = null;
	}
}
