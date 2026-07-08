/**
 * Scroll Marquee Block - Auto-Width Deprecation Migration Tests
 *
 * Verifies:
 *  - the current save() emits `--dsgo-marquee-image-width:auto` for the new
 *    default, and an explicit width is still written inline;
 *  - OLD marquees that baked the pre-`auto` `--dsgo-marquee-image-width:300px`
 *    default inline (empty comment) still parse cleanly against the current
 *    save() + v4 deprecation instead of showing WordPress's "unexpected or
 *    invalid content / Attempt Recovery" warning, and keep 300px so existing
 *    designs are byte-preserved.
 */

import {
	registerBlockType,
	setCategories,
	parse,
	createBlock,
	serialize,
	getBlockContent,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';
import deprecated from '../deprecated';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save, deprecated });

// deprecated.js exports newest-first: [v4, v3, ...].
const [v4Deprecation] = deprecated;

// Minimal valid rows payload (createBlock needs at least one image so the
// migrated markup is non-trivial and matches real content).
const ROWS = [
	{
		images: [{ id: 1, url: 'https://example.com/a.jpg', alt: 'a' }],
		direction: 'left',
	},
];

describe('scroll-marquee save() - auto image width', () => {
	test('a new marquee emits the auto width custom property by default', () => {
		const markup = serialize(createBlock(metadata.name, { rows: ROWS }));
		expect(markup).toContain('--dsgo-marquee-image-width:auto');
		expect(markup).not.toContain('--dsgo-marquee-image-width:300px');
	});

	test('an explicit image width is still written inline', () => {
		const markup = serialize(
			createBlock(metadata.name, { rows: ROWS, imageWidth: '400px' })
		);
		expect(markup).toContain('--dsgo-marquee-image-width:400px');
	});
});

describe('scroll-marquee deprecations - v4 auto-width migration', () => {
	// Derive byte-exact OLD default-width markup from the current canonical
	// output: the pre-`auto` save baked the 300px default inline where the
	// current save now writes `auto` (the sole difference).
	const canonical = serialize(createBlock(metadata.name, { rows: ROWS }));
	const OLD_MARKUP = canonical.replace(
		'--dsgo-marquee-image-width:auto',
		'--dsgo-marquee-image-width:300px'
	);

	test('derived old markup differs from canonical as expected', () => {
		expect(OLD_MARKUP).toContain('--dsgo-marquee-image-width:300px');
		expect(OLD_MARKUP).not.toContain('--dsgo-marquee-image-width:auto');
	});

	test('old default-width marquee migrates silently and keeps 300px', () => {
		const [block] = parse(OLD_MARKUP);

		expect(console).toHaveInformed();
		expect(block.name).toBe('designsetgo/scroll-marquee');
		expect(block.isValid).toBe(true);
		expect(block.attributes.imageWidth).toBe('300px');
		// Re-serialized with the current save(): still 300px, byte-preserved.
		expect(getBlockContent(block)).toContain(
			'--dsgo-marquee-image-width:300px'
		);
		expect(getBlockContent(block)).not.toContain(
			'--dsgo-marquee-image-width:auto'
		);
	});

	test('migrated HTML body is byte-identical to the stored old markup', () => {
		// The migrated block re-serializes with `"imageWidth":"300px"` pinned in
		// the block comment (it is no longer the default), but the saved HTML
		// body — what renders on the page — is byte-for-byte preserved.
		const OLD_BODY = getBlockContent(
			createBlock(metadata.name, { rows: ROWS, imageWidth: '300px' })
		);
		const [block] = parse(OLD_MARKUP);
		expect(console).toHaveInformed();
		expect(getBlockContent(block)).toBe(OLD_BODY);
		expect(OLD_BODY).toContain('--dsgo-marquee-image-width:300px');
	});

	test('isEligible flags old default-width markup', () => {
		expect(
			v4Deprecation.isEligible({}, [], { innerHTML: OLD_MARKUP })
		).toBe(true);
	});

	test('isEligible ignores current (auto) markup', () => {
		expect(v4Deprecation.isEligible({}, [], { innerHTML: canonical })).toBe(
			false
		);
	});

	test('isEligible ignores older border-radius-bearing markup', () => {
		const oldBorderRadiusMarkup = OLD_MARKUP.replace(
			'--dsgo-marquee-object-fit:cover',
			'--dsgo-marquee-object-fit:cover;--dsgo-marquee-border-radius:8px'
		);
		expect(
			v4Deprecation.isEligible({}, [], {
				innerHTML: oldBorderRadiusMarkup,
			})
		).toBe(false);
	});

	test('migrate is passthrough (keeps 300px from the deprecation schema)', () => {
		expect(
			v4Deprecation.migrate({ imageWidth: '300px', rows: ROWS })
		).toEqual({ imageWidth: '300px', rows: ROWS });
	});
});
