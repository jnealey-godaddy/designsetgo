/**
 * Tests for the keyless Google Maps embed URL builder.
 *
 * The PHP twin in includes/helpers.php is covered by
 * tests/phpunit/map-embed-render-test.php; these assertions are deliberately
 * the same shape so a drift between the two is visible.
 */

import { buildEmbedUrl } from '../../src/blocks/map/utils/embed-url';

/**
 * Read one query parameter from a built URL.
 *
 * Substring assertions are unsafe here: 'z=13' contains 'z=1', so a clamp
 * regression would pass unnoticed.
 *
 * @param {string} url  - Built embed URL.
 * @param {string} name - Parameter name.
 * @return {string|null} Parameter value.
 */
function param(url, name) {
	return new URL(url).searchParams.get(name);
}

describe('buildEmbedUrl', () => {
	it('prefers an address over coordinates', () => {
		const url = buildEmbedUrl(
			'123 Main St, Springfield',
			40.7128,
			-74.006,
			13
		);

		expect(param(url, 'q')).toBe('123 Main St, Springfield');
		expect(url).not.toContain('40.7128');
	});

	it('falls back to coordinates when there is no address', () => {
		expect(param(buildEmbedUrl('', 40.7128, -74.006, 13), 'q')).toBe(
			'40.7128,-74.006'
		);
	});

	it('flattens multi-line addresses', () => {
		const url = buildEmbedUrl('123 Main St\nSpringfield, IL', 0, 0, 13);

		expect(param(url, 'q')).toBe('123 Main St, Springfield, IL');
		expect(url).not.toContain('%0A');
	});

	it('clamps zoom to the supported range', () => {
		expect(param(buildEmbedUrl('', 0, 0, 99), 'z')).toBe('20');
		expect(param(buildEmbedUrl('', 0, 0, -5), 'z')).toBe('1');
	});

	it('clamps a zero zoom up to 1, matching the PHP twin', () => {
		// PHP does max(1, min(20, (int) $zoom)), so 0 becomes 1 — not the
		// attribute default. A falsy-check here would wrongly substitute 13.
		expect(param(buildEmbedUrl('', 0, 0, 0), 'z')).toBe('1');
	});

	it('treats an unparseable zoom the way PHP casts it', () => {
		// (int) 'abc' and (int) null are both 0 in PHP, which then clamps to 1.
		expect(param(buildEmbedUrl('', 0, 0, 'abc'), 'z')).toBe('1');
		expect(param(buildEmbedUrl('', 0, 0, null), 'z')).toBe('1');
		expect(param(buildEmbedUrl('', 0, 0, undefined), 'z')).toBe('1');
	});

	it('truncates a fractional zoom toward zero like an int cast', () => {
		expect(param(buildEmbedUrl('', 0, 0, 7.9), 'z')).toBe('7');
	});

	it('renders zero coordinates as a plain zero', () => {
		expect(param(buildEmbedUrl('', 0, 0, 13), 'q')).toBe('0,0');
	});

	it('requests the bare embed output', () => {
		const url = buildEmbedUrl('Paris', 0, 0, 13);

		expect(url.startsWith('https://maps.google.com/maps?')).toBe(true);
		expect(url).toContain('output=embed');
	});
});
