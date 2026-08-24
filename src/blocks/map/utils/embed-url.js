/**
 * Keyless Google Maps embed URL builder.
 *
 * Mirrors designsetgo_map_embed_url() in includes/helpers.php so the editor
 * preview and the server-rendered front end resolve to the same URL. Keep the
 * two in sync — the PHP side is the one that ships in the markup.
 */

/**
 * Format a coordinate without exponent or trailing-zero noise.
 *
 * @param {number} value - Coordinate value.
 * @return {string} Plain decimal representation.
 */
function formatCoordinate(value) {
	const numeric = Number.isFinite(value) ? value : 0;

	return numeric.toFixed(6).replace(/0+$/, '').replace(/\.$/, '');
}

/**
 * Build the embed URL for the googlemaps-embed provider.
 *
 * @param {string} address   - Street address; preferred over coordinates.
 * @param {number} latitude  - Latitude, used when address is empty.
 * @param {number} longitude - Longitude, used when address is empty.
 * @param {number} zoom      - Zoom level; clamped to Google's 1–20 range.
 * @return {string} Embed URL.
 */
export function buildEmbedUrl(address, latitude, longitude, zoom) {
	// Flatten multi-line addresses the way the geocoder does, so both providers
	// resolve the same author input to the same place.
	const flattened = String(address || '')
		.replace(/[\r\n]+/g, ', ')
		.replace(/\s+/g, ' ')
		.trim();

	const query = flattened
		? flattened
		: `${formatCoordinate(latitude)},${formatCoordinate(longitude)}`;

	const safeZoom = Math.max(1, Math.min(20, parseInt(zoom, 10) || 13));

	const params = new URLSearchParams({
		q: query,
		z: String(safeZoom),
		output: 'embed',
	});

	return `https://maps.google.com/maps?${params.toString()}`;
}
