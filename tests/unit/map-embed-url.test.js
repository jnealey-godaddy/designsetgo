/**
 * Tests for the keyless Google Maps embed URL builder.
 *
 * The PHP twin in includes/helpers.php is covered by
 * tests/phpunit/map-embed-render-test.php; these assertions are deliberately
 * the same shape so a drift between the two is visible.
 */

import { buildEmbedUrl } from '../../src/blocks/map/utils/embed-url';

describe('buildEmbedUrl', () => {
	it('prefers an address over coordinates', () => {
		const url = buildEmbedUrl(
			'123 Main St, Springfield',
			40.7128,
			-74.006,
			13
		);

		expect(url).toContain('q=123+Main+St%2C+Springfield');
		expect(url).not.toContain('40.7128');
	});

	it('falls back to coordinates when there is no address', () => {
		expect(buildEmbedUrl('', 40.7128, -74.006, 13)).toContain(
			'q=40.7128%2C-74.006'
		);
	});

	it('flattens multi-line addresses', () => {
		const url = buildEmbedUrl('123 Main St\nSpringfield, IL', 0, 0, 13);

		expect(url).toContain('q=123+Main+St%2C+Springfield%2C+IL');
		expect(url).not.toContain('%0A');
	});

	it('clamps zoom to the supported range', () => {
		expect(buildEmbedUrl('', 0, 0, 99)).toContain('z=20');
		expect(buildEmbedUrl('', 0, 0, -5)).toContain('z=1');
	});

	it('renders zero coordinates as a plain zero', () => {
		expect(buildEmbedUrl('', 0, 0, 13)).toContain('q=0%2C0');
	});

	it('requests the bare embed output', () => {
		const url = buildEmbedUrl('Paris', 0, 0, 13);

		expect(url.startsWith('https://maps.google.com/maps?')).toBe(true);
		expect(url).toContain('output=embed');
	});
});
