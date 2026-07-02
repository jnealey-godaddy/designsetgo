/**
 * Scroll Marquee Block - HTML-sourced legacy deprecation
 *
 * Regression test proving that older Scrolling Gallery content whose image
 * rows live only in the HTML (empty block comment, `source: 'query'` era)
 * AND which already carries the `--dsgo-marquee-object-fit` custom property
 * still parses as valid — no "Attempt Recovery" notice.
 *
 * This is the exact stored shape found on real pages (see
 * __fixtures__/scroll-marquee-html-sourced.html, captured from a live post):
 *   - empty comment  → rows are NOT serialized, only present in the markup
 *   - object-fit var → newer than the pre-objectFit deprecations (v1/v2)
 *   - no border-radius var → newer than the pre-native-border deprecation (v3)
 *
 * None of the v3/v2/v1 deprecations reproduce that combination, so without a
 * dedicated deprecation the block fails validation.
 *
 * Deliberately uses the real @wordpress/blocks parser/validator (not mocked)
 * since the thing under test IS the parser's deprecation-matching behavior.
 *
 * @package
 */

import fs from 'fs';
import path from 'path';

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks.
// useBlockProps.save() resolves block supports against THAT copy's registry,
// so registration/parsing here must go through the same instance.
const {
	registerBlockType,
	unregisterBlockType,
	parse,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import metadata from '../../src/blocks/scroll-marquee/block.json';
import save from '../../src/blocks/scroll-marquee/save';
import deprecated from '../../src/blocks/scroll-marquee/deprecated';

const htmlSourcedMarkup = fs.readFileSync(
	path.join(__dirname, '__fixtures__/scroll-marquee-html-sourced.html'),
	'utf8'
);

describe('Scroll Marquee - HTML-sourced legacy deprecation', () => {
	beforeAll(() => {
		registerBlockType(metadata.name, {
			...metadata,
			// The custom 'designsetgo' category isn't registered in the jest
			// environment; category is irrelevant to parse/validation, so use
			// a built-in one to avoid an unrelated invalid-category warning.
			category: 'media',
			save,
			deprecated,
		});
	});

	afterAll(() => {
		unregisterBlockType(metadata.name);
	});

	it('parses HTML-sourced content (empty comment, object-fit, no border-radius) as valid', () => {
		const [block] = parse(htmlSourcedMarkup);

		// The parser logs an info message when a deprecated version's `save`
		// matches and the block gets silently migrated — the behavior under
		// test (no "Attempt Recovery" warning/error is emitted).
		expect(console).toHaveInformed();

		expect(block).toBeTruthy();
		expect(block.name).toBe(metadata.name);
		expect(block.isValid).toBe(true);
	});

	it('recovers the image rows from the markup during migration', () => {
		const [block] = parse(htmlSourcedMarkup);

		expect(console).toHaveInformed();

		expect(Array.isArray(block.attributes.rows)).toBe(true);
		const totalImages = block.attributes.rows.reduce(
			(sum, row) => sum + (row.images ? row.images.length : 0),
			0
		);
		expect(totalImages).toBeGreaterThan(0);
		// Every recovered image keeps its source URL.
		block.attributes.rows.forEach((row) => {
			(row.images || []).forEach((image) => {
				expect(typeof image.url).toBe('string');
				expect(image.url.length).toBeGreaterThan(0);
			});
		});
	});
});
