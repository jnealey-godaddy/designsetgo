/**
 * Unit tests for the e2e pattern-discovery helper.
 *
 * Reads the real patterns/*\/*.php files, so it doubles as a guard that the
 * pattern set the e2e sweep will cover stays sane and that slug derivation
 * matches the PHP loader's `designsetgo/<category>/<name>` shape.
 */
const { listDesignSetGoPatterns } = require('../../e2e/helpers/patterns');

describe('listDesignSetGoPatterns', () => {
	const patterns = listDesignSetGoPatterns();
	const slugs = patterns.map((p) => p.slug);

	it('returns a non-empty, slug-sorted array of designsetgo patterns', () => {
		expect(Array.isArray(patterns)).toBe(true);
		expect(patterns.length).toBeGreaterThan(100);
		expect(slugs.every((s) => s.startsWith('designsetgo/'))).toBe(true);
		expect([...slugs]).toEqual([...slugs].sort());
	});

	it('derives slugs as designsetgo/<category>/<name>', () => {
		expect(slugs).toContain('designsetgo/content/content-split-image');
		expect(slugs).toContain('designsetgo/hero/hero-parallax');
		expect(slugs).toContain('designsetgo/testimonials/testimonials-grid');
		expect(
			slugs.every((s) =>
				/^designsetgo\/[a-z0-9_-]+\/[a-z0-9_-]+$/.test(s)
			)
		).toBe(true);
	});

	it('reads a human-readable title from each pattern header', () => {
		const split = patterns.find(
			(p) => p.slug === 'designsetgo/content/content-split-image'
		);
		expect(split).toBeDefined();
		expect(split.title).toBe('Split Content with Image');
		expect(
			patterns.every((p) => typeof p.title === 'string' && p.title)
		).toBe(true);
	});
});
