/**
 * Geocoding Utilities
 *
 * Functions for converting addresses to coordinates using Nominatim API (OpenStreetMap).
 */

/**
 * Normalize a multi-line address into a comma-separated string suitable for geocoding.
 *
 * @param {string} address - Raw address, possibly with newlines.
 * @return {string} Normalized address.
 */
function normalizeAddress(address) {
	return address
		.replace(/u0022/g, '') // compat: " serialized as \" then stripped by wp_unslash → u0022
		.replace(/u0026quot;/g, '') // compat: " encoded as &quot; then &→& by serializer, \→stripped by wp_unslash
		.split(/[\r\n]+/)
		.map((line) => line.trim())
		.filter(Boolean)
		.join(', ');
}

/**
 * Query Nominatim for a single address string.
 *
 * @param {string} query - The address query.
 * @return {Promise<Object|null>} Object with lat, lng, and display_name, or null.
 */
async function queryNominatim(query) {
	const encodedAddress = encodeURIComponent(query);
	const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodedAddress}&limit=1`;

	const response = await fetch(url, {
		headers: {
			'User-Agent': 'DesignSetGo WordPress Plugin',
		},
	});

	if (!response.ok) {
		throw new Error('Geocoding request failed');
	}

	const data = await response.json();

	if (data && data.length > 0) {
		const result = data[0];
		return {
			lat: parseFloat(result.lat),
			lng: parseFloat(result.lon),
			display_name: result.display_name,
		};
	}

	return null;
}

/**
 * Geocode an address to latitude/longitude using Nominatim API.
 *
 * Normalizes newlines to commas before querying. If the full address fails
 * (e.g. it starts with a business name), retries without the first line.
 *
 * @param {string} address - The address to geocode.
 * @return {Promise<Object|null>} Object with lat, lng, and display_name, or null if failed.
 */
export async function geocodeAddress(address) {
	if (!address || address.trim() === '') {
		return null;
	}

	try {
		const normalized = normalizeAddress(address);
		const result = await queryNominatim(normalized);
		if (result) {
			return result;
		}

		// Retry without the first line (often a business name).
		const lines = normalized.split(', ');
		if (lines.length > 2) {
			return await queryNominatim(lines.slice(1).join(', '));
		}

		return null;
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('Geocoding error:', error);
		return null;
	}
}

/**
 * Reverse geocode coordinates to an address using Nominatim API.
 *
 * @param {number} lat - Latitude.
 * @param {number} lng - Longitude.
 * @return {Promise<string|null>} Address string or null if failed.
 */
export async function reverseGeocode(lat, lng) {
	if (typeof lat !== 'number' || typeof lng !== 'number') {
		return null;
	}

	try {
		const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`;

		const response = await fetch(url, {
			headers: {
				'User-Agent': 'DesignSetGo WordPress Plugin',
			},
		});

		if (!response.ok) {
			throw new Error('Reverse geocoding request failed');
		}

		const data = await response.json();

		if (data && data.display_name) {
			return data.display_name;
		}

		return null;
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('Reverse geocoding error:', error);
		return null;
	}
}

/**
 * Validate latitude value.
 *
 * @param {number} lat - Latitude to validate.
 * @return {boolean} True if valid.
 */
export function isValidLatitude(lat) {
	return typeof lat === 'number' && lat >= -90 && lat <= 90;
}

/**
 * Validate longitude value.
 *
 * @param {number} lng - Longitude to validate.
 * @return {boolean} True if valid.
 */
export function isValidLongitude(lng) {
	return typeof lng === 'number' && lng >= -180 && lng <= 180;
}

/**
 * Validate zoom level.
 *
 * @param {number} zoom - Zoom level to validate.
 * @return {boolean} True if valid.
 */
export function isValidZoom(zoom) {
	return typeof zoom === 'number' && zoom >= 1 && zoom <= 20;
}
