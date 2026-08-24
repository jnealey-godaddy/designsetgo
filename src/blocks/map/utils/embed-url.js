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

	// Mirror PHP's max(1, min(20, (int) $zoom)) exactly. Two traps: `|| 13`
	// would treat a zoom of 0 as missing and substitute the attribute default
	// where PHP clamps to 1, and PHP's (int) cast turns any unparseable value
	// into 0 (which then clamps to 1) rather than a default. The block UI's
	// RangeControl has min={1}, but patterns and the API can set dsgoZoom
	// freely, and a mismatch here shows one zoom in the editor preview and
	// another on the front end.
	const parsedZoom = parseInt(zoom, 10);
	const safeZoom = Math.max(
		1,
		Math.min(20, Number.isFinite(parsedZoom) ? parsedZoom : 0)
	);

	const params = new URLSearchParams({
		q: query,
		z: String(safeZoom),
		output: 'embed',
	});

	return `https://maps.google.com/maps?${params.toString()}`;
}
