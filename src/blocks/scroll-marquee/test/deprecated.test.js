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
	getSaveContent,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';
import deprecated from '../deprecated';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save, deprecated });

// deprecated.js exports newest-first: [v4, v3, v2, v1ObjectFit, v1].
const [v4Deprecation, , v2Deprecation, , v1Deprecation] = deprecated;

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

	test('a current marquee with an explicit 300px width is NOT force-migrated', () => {
		// The old default-width markup is byte-identical to a current marquee
		// whose author explicitly picks imageWidth "300px" (reachable once "Auto
		// width" is toggled off). With no isEligible, this valid block matches the
		// current save() and is skipped — no migration pass and no spurious
		// console.info (which the jest-console matcher would otherwise flag as
		// unexpected, failing this test).
		const currentMarkup = serialize(
			createBlock(metadata.name, { rows: ROWS, imageWidth: '300px' })
		);
		const [block] = parse(currentMarkup);
		expect(block.isValid).toBe(true);
		expect(block.attributes.imageWidth).toBe('300px');
		expect(getBlockContent(block)).toContain(
			'--dsgo-marquee-image-width:300px'
		);
	});

	test('migrate is passthrough (keeps 300px from the deprecation schema)', () => {
		expect(
			v4Deprecation.migrate({ imageWidth: '300px', rows: ROWS })
		).toEqual({ imageWidth: '300px', rows: ROWS });
	});
});

/**
 * Serializes a block exactly as the given historical version wrote it, from
 * that version's OWN save() and attribute schema, so the fixture is
 * byte-faithful instead of hand-transcribed.
 *
 * @param {Object} deprecation  Deprecation entry whose save()/schema to use.
 * @param {Object} attrs        Attribute overrides for that schema.
 * @param {Object} commentAttrs Attributes to write into the block comment.
 * @return {string} Serialized block comment + HTML.
 */
function legacyMarkup(deprecation, attrs, commentAttrs) {
	const depType = { ...metadata, ...deprecation };
	const full = {};
	Object.entries(deprecation.attributes).forEach(([key, schema]) => {
		full[key] = attrs[key] !== undefined ? attrs[key] : schema.default;
	});
	const html = getSaveContent(depType, { ...full, ...attrs }, []);
	const json = Object.keys(commentAttrs).length
		? ` ${JSON.stringify(commentAttrs)} `
		: ' ';
	return `<!-- wp:designsetgo/scroll-marquee${json}-->\n${html}\n<!-- /wp:designsetgo/scroll-marquee -->`;
}

/**
 * Parses, re-serializes, and re-parses — the exact round trip an author
 * performs by opening a page and pressing Update.
 *
 * @param {string} markup Stored block markup.
 * @return {Object} The block after one save cycle, plus stability info.
 */
function afterOneSave(markup) {
	const [migrated] = parse(markup);
	const saved = serialize([migrated]);
	const [reparsed] = parse(saved);
	return {
		migrated,
		saved,
		reparsed,
		stable: serialize([reparsed]) === saved,
	};
}

describe('scroll-marquee deprecations - migrated blocks survive a save (v2)', () => {
	// A pre-objectFit marquee: rows in the comment, border-radius var in the
	// markup, and no `--dsgo-marquee-object-fit` — the shape v2 claims.
	const V2_MARKUP = legacyMarkup(
		v2Deprecation,
		{ rows: ROWS, borderRadius: '12px' },
		{ rows: ROWS, borderRadius: '12px' }
	);

	test('the fixture is genuine pre-objectFit markup', () => {
		expect(V2_MARKUP).toContain('--dsgo-marquee-border-radius:12px');
		expect(V2_MARKUP).not.toContain('--dsgo-marquee-object-fit');
	});

	test('it migrates cleanly on first parse', () => {
		const [block] = parse(V2_MARKUP);
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
	});

	// The bug: migrate() left objectFit undefined, so save() emitted no
	// object-fit custom property — but the NEXT parse fills in block.json's
	// 'cover' default and regenerates it, so the block WordPress just wrote is
	// invalid the moment it is read back. Latent until someone hits Update.
	test('the block stays valid after being saved once', () => {
		const { reparsed, stable } = afterOneSave(V2_MARKUP);
		expect(console).toHaveInformed();
		expect(reparsed.isValid).toBe(true);
		expect(stable).toBe(true);
	});

	test('migration resolves objectFit to the schema default', () => {
		const { migrated, saved } = afterOneSave(V2_MARKUP);
		expect(console).toHaveInformed();
		expect(migrated.attributes.objectFit).toBe('cover');
		expect(saved).toContain('--dsgo-marquee-object-fit:cover');
	});

	// borderRadius was dropped from the schema in favour of the border support.
	// Deprecations do not cascade, so v2 must do the conversion v3 does — or
	// the radius is silently lost, since WordPress never serializes an
	// attribute the current block type does not declare.
	test('the legacy border radius survives as a border support value', () => {
		const { migrated, saved } = afterOneSave(V2_MARKUP);
		expect(console).toHaveInformed();
		expect(migrated.attributes.style?.border?.radius).toBe('12px');
		expect(migrated.attributes.borderRadius).toBeUndefined();
		expect(saved).toContain('border-radius:12px');
	});
});

describe('scroll-marquee deprecations - migrated blocks survive a save (v1)', () => {
	// The oldest shape: images recovered from the MARKUP (rows were
	// query-sourced, so they never appear in the comment), no object-fit var,
	// and border radius still a comment-backed attribute. The radius must be
	// in the comment: it is not HTML-sourced, so a non-default value lives
	// there or the block cannot round-trip — an empty comment would mean the
	// radius was at its 8px default.
	const V1_MARKUP = legacyMarkup(
		v1Deprecation,
		{ rows: ROWS, borderRadius: '12px' },
		{ borderRadius: '12px' }
	);

	test('the fixture is genuine v1 markup', () => {
		expect(V1_MARKUP).toContain('--dsgo-marquee-border-radius:12px');
		expect(V1_MARKUP).not.toContain('--dsgo-marquee-object-fit');
		// rows are recovered from the markup, never serialized in the comment.
		expect(V1_MARKUP).not.toContain('"rows"');
	});

	test('the block stays valid after being saved once', () => {
		const { migrated, reparsed, stable } = afterOneSave(V1_MARKUP);
		expect(console).toHaveInformed();
		expect(migrated.isValid).toBe(true);
		expect(reparsed.isValid).toBe(true);
		expect(stable).toBe(true);
	});

	test('it keeps its images, objectFit and border radius', () => {
		const { migrated } = afterOneSave(V1_MARKUP);
		expect(console).toHaveInformed();
		expect(migrated.attributes.rows[0].images).toHaveLength(1);
		expect(migrated.attributes.objectFit).toBe('cover');
		expect(migrated.attributes.style?.border?.radius).toBe('12px');
	});
});
