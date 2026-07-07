/**
 * Section Block - Shape Divider Migration Tests
 *
 * Verifies that OLD sections saved with inline-SVG shape dividers (pre
 * class-based CSS rendering, commits 88f98fa/b81ba13/c01f810d) still parse
 * cleanly against the CURRENT save() + deprecations pipeline instead of
 * showing WordPress's "unexpected or invalid content / Attempt Recovery"
 * warning.
 *
 * @since 2.6.0
 */

// See save.test.js for the full explanation of why these must be imported
// from the NESTED `@wordpress/blocks` copy that `@wordpress/block-editor`
// bundles, rather than the top-level package: block-editor's
// useBlockProps.save()/useInnerBlocksProps.save() read block-support
// metadata via getBlockType() against that nested registry, so the block
// must be registered on the same module instance for save()/parse() to
// exercise real output instead of silently no-op'ing.
import {
	registerBlockType,
	setCategories,
	parse,
	getSaveContent,
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

/**
 * Builds a serialized `<!-- wp:designsetgo/section {...} -->` fixture from a
 * given deprecation entry's OWN save() function, so the markup is a
 * byte-faithful reproduction of what that historical version actually wrote
 * to post_content (no hand-transcription of padding/style strings).
 *
 * @param {Object} attrs       Attributes (merged over block.json defaults).
 * @param {Object} deprecation Deprecation entry (e.g. v4, v5) whose save() to use.
 * @return {string} Serialized block comment + HTML.
 */
function buildOldMarkup(attrs, deprecation) {
	const depBlockType = { ...metadata, ...deprecation };
	const fullAttrs = { ...deprecation.attributes };
	// Apply block.json-style defaults from the deprecation's own attribute schema.
	Object.keys(fullAttrs).forEach((key) => {
		fullAttrs[key] =
			attrs[key] !== undefined ? attrs[key] : fullAttrs[key].default;
	});
	const mergedAttrs = { ...fullAttrs, ...attrs };
	const html = getSaveContent(depBlockType, mergedAttrs, []);
	return `<!-- wp:designsetgo/section ${JSON.stringify(attrs)} -->\n${html}\n<!-- /wp:designsetgo/section -->`;
}

// Representative pre-c01f810d serialized markup: inline <svg> shape divider,
// old --dsgo-shape-offset/-color CSS var contract (no class-based
// is-shape-{slug} / --dsgo-shape-fill / --dsgo-shape-band vars). No explicit
// shapeDividerTopColor, so the pre-inheritance `currentColor` fallback
// applies (matches deprecation v3's OldShapeDivider save output). This is
// the exact fixture given in the task's migration plan.
const OLD_SVG_MARKUP = `<!-- wp:designsetgo/section {"shapeDividerTop":"wave","shapeDividerTopHeight":80,"backgroundColor":"contrast"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-stack--has-shape-divider has-contrast-background-color has-background"><div class="dsgo-shape-divider dsgo-shape-divider--top" style="--dsgo-shape-height:80px;--dsgo-shape-width:100%;--dsgo-shape-offset:-0%;--dsgo-shape-color:currentColor" aria-hidden="true"><svg viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M0,0 C300,120 900,0 1200,80 L1200,120 L0,120 Z"></path></svg></div><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto;padding-top:80px"></div></div>
<!-- /wp:designsetgo/section -->`;

// Bottom-divider, v4-era markup: shape color inherits the section's
// background color (no explicit shapeDividerBottomColor set), matching
// V4ShapeDivider's inheritance behavior used by deprecations v4/v5/v6.
// Built from v4's own save() so the fixture is byte-exact.
// deprecated.js exports deprecations newest-first: [v7, v6, v5, v4, v3, v2, v1].
const [, , , v4Deprecation] = deprecated;
const OLD_SVG_MARKUP_BOTTOM_INHERITED = buildOldMarkup(
	{
		shapeDividerBottom: 'tilt',
		shapeDividerBottomHeight: 60,
		backgroundColor: 'contrast',
	},
	v4Deprecation
);

describe('section deprecations - shape divider SVG to class-based migration', () => {
	test('old inline-SVG markup (currentColor fallback) migrates cleanly against current save()', () => {
		const [block] = parse(OLD_SVG_MARKUP);

		// A successful silent deprecation migration makes WordPress's parser
		// log an informational "Block successfully updated" console.info
		// (see @wordpress/blocks parser/index.js). This is the expected,
		// desired outcome — not a warning — so it must be explicitly
		// consumed here or @wordpress/jest-console's strict mock will fail
		// the test for an "unexpected" console call.
		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/section');
		expect(block.isValid).toBe(true);
		expect(block.attributes.shapeDividerTop).toBe('wave');
		expect(block.attributes.shapeDividerTopHeight).toBe(80);
		expect(block.attributes.backgroundColor).toBe('contrast');
	});

	test('old inline-SVG markup (background-inherited fill) migrates cleanly against current save()', () => {
		const [block] = parse(OLD_SVG_MARKUP_BOTTOM_INHERITED);

		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/section');
		expect(block.isValid).toBe(true);
		expect(block.attributes.shapeDividerBottom).toBe('tilt');
		expect(block.attributes.shapeDividerBottomHeight).toBe(60);
		expect(block.attributes.backgroundColor).toBe('contrast');
	});

	// Regression guard for the drops/fan/steps/slime redesign. Those four
	// shapes' geometry changed in SHAPE_DIVIDERS for the see-through mask model.
	// The deprecation save() functions (OldShapeDivider for v3, V4ShapeDivider
	// for v4/v5/v6) reproduce the OLD inline-<svg> markup for byte-matching, so
	// they MUST emit the pre-redesign geometry (via LEGACY_SHAPE_DIVIDERS) — not
	// the live library — or content saved before the redesign fails to match any
	// deprecation and shows "unexpected or invalid content". The other tests use
	// wave/tilt, which were NOT redesigned, so they can't catch this.
	test('deprecations reproduce frozen legacy geometry for redesigned shapes (drops)', () => {
		// deprecated.js exports newest-first: [v7, v6, v5, v4, v3, v2, v1].
		const [, , , v4Dep, v3Dep] = deprecated;

		[v3Dep, v4Dep].forEach((deprecation) => {
			const markup = buildOldMarkup(
				{ shapeDividerTop: 'drops', shapeDividerTopHeight: 80 },
				deprecation
			);
			// Pre-redesign drops was five <ellipse>s over a base <rect>.
			expect(markup).toContain('<ellipse');
			// The redesigned single-<path> arc signature must NOT appear.
			expect(markup).not.toContain('A100,95');
		});
	});
});

describe('section deprecations - style-kit overlay variation migration (v7)', () => {
	// v7 is the newest deprecation (index 0).
	const [v7Deprecation] = deprecated;

	// Reproduce content saved BEFORE this change by taking what the block
	// ACTUALLY serializes today (carrying block.json defaults such as the
	// spacing padding) and stripping the overlay class. Deriving the fixture
	// from the real save() — rather than from v7's own save() — is what makes
	// this a genuine regression guard: v7's `save()` only byte-matches this
	// markup when v7's attribute schema reproduces block.json's `style` default
	// (the padding). An earlier version of v7 that omitted that default passed a
	// tautological buildOldMarkup(v7) fixture but failed against real content.
	const canonicalMarkup = serialize(
		createBlock(metadata.name, { className: 'is-style-overlay-dark' })
	);
	const OLD_OVERLAY_VARIATION_MARKUP = canonicalMarkup.replace(
		' dsgo-stack--has-overlay',
		''
	);

	test('canonical markup carries the overlay class and the default spacing padding', () => {
		// Guards the fixture itself: if either signal disappears the migration
		// test below stops being meaningful.
		expect(canonicalMarkup).toContain('dsgo-stack--has-overlay');
		expect(OLD_OVERLAY_VARIATION_MARKUP).not.toContain(
			'dsgo-stack--has-overlay'
		);
		expect(OLD_OVERLAY_VARIATION_MARKUP).toContain(
			'--wp--preset--spacing--50'
		);
	});

	test('old is-style-overlay-dark section (no overlay class) migrates silently against current save()', () => {
		const [block] = parse(OLD_OVERLAY_VARIATION_MARKUP);

		// Silent migration logs an informational "Block successfully updated".
		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/section');
		expect(block.isValid).toBe(true);
		// migrate() is a passthrough — the variation class is retained and the
		// current save() now derives the overlay class from it.
		expect(block.attributes.className).toBe('is-style-overlay-dark');
		// The migrated block re-serializes with the overlay class restored.
		expect(getBlockContent(block)).toContain('dsgo-stack--has-overlay');
	});

	test('isEligible detects an overlay variation lacking the overlay class', () => {
		const html =
			'<div class="wp-block-designsetgo-section is-style-overlay-dark dsgo-stack"><div class="dsgo-stack__inner"></div></div>';
		expect(
			v7Deprecation.isEligible(
				{ className: 'is-style-overlay-dark' },
				[],
				{ innerHTML: html }
			)
		).toBe(true);
	});

	test('isEligible ignores sections that already carry the overlay class', () => {
		const html =
			'<div class="wp-block-designsetgo-section is-style-overlay-dark dsgo-stack dsgo-stack--has-overlay"><div class="dsgo-stack__inner"></div></div>';
		expect(
			v7Deprecation.isEligible(
				{ className: 'is-style-overlay-dark' },
				[],
				{ innerHTML: html }
			)
		).toBe(false);
	});

	test('isEligible ignores sections without an overlay variation', () => {
		const html =
			'<div class="wp-block-designsetgo-section dsgo-stack"><div class="dsgo-stack__inner"></div></div>';
		expect(
			v7Deprecation.isEligible({ className: '' }, [], { innerHTML: html })
		).toBe(false);
	});

	test('migrate is a passthrough', () => {
		const attrs = { className: 'is-style-overlay-dark', overlayColor: '' };
		expect(v7Deprecation.migrate(attrs)).toBe(attrs);
	});
});
