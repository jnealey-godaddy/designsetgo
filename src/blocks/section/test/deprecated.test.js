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
// deprecated.js exports deprecations newest-first: [v9, v8, v7, v6, v5, v4, v3, v2, v1].
const [, , , , , v4Deprecation] = deprecated;
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
		// deprecated.js exports newest-first: [v9, v8, v7, v6, v5, v4, v3, v2, v1].
		const [, , , , , v4Dep, v3Dep] = deprecated;

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
	// deprecated.js exports newest-first: [v9, v8, v7, v6, v5, v4, v3, v2, v1].
	const [, , v7Deprecation] = deprecated;

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

	test('migration retains shapeDividerTopColor/shapeDividerBottomColor (v7 attribute schema must declare them)', () => {
		// v6/block.json both declare these legacy/preview-only swatch
		// attributes. If v7.attributes omitted them, WP would parse the
		// stored comment JSON without them (a deprecation's own `attributes`
		// schema is used exclusively, not merged with the current block
		// type), and migrate()'s passthrough would silently drop the values.
		const markupWithShapeColors = serialize(
			createBlock(metadata.name, {
				className: 'is-style-overlay-dark',
				shapeDividerTopColor: 'contrast',
				shapeDividerBottomColor: 'accent',
			})
		).replace(' dsgo-stack--has-overlay', '');

		const [block] = parse(markupWithShapeColors);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.shapeDividerTopColor).toBe('contrast');
		expect(block.attributes.shapeDividerBottomColor).toBe('accent');
	});

	test('isEligible detects an overlay variation lacking the overlay class', () => {
		const html =
			'<div class="wp-block-designsetgo-section is-style-overlay-dark dsgo-stack"><div class="dsgo-stack__inner"></div></div>';
		expect(
			v7Deprecation.isEligible(
				{ className: 'is-style-overlay-dark' },
				[],
				{ blockNode: { innerHTML: html } }
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
				{ blockNode: { innerHTML: html } }
			)
		).toBe(false);
	});

	test('isEligible ignores sections without an overlay variation', () => {
		const html =
			'<div class="wp-block-designsetgo-section dsgo-stack"><div class="dsgo-stack__inner"></div></div>';
		expect(
			v7Deprecation.isEligible({ className: '' }, [], {
				blockNode: { innerHTML: html },
			})
		).toBe(false);
	});

	test('migrate adds no clearance when the section has no divider', () => {
		const attrs = { className: 'is-style-overlay-dark', overlayColor: '' };
		const migrated = v7Deprecation.migrate(attrs);
		expect(migrated).toEqual(attrs);
		expect(migrated.shapeDividerTopSpacing).toBeUndefined();
		expect(migrated.shapeDividerBottomSpacing).toBeUndefined();
	});

	test('migrate carries height-derived clearance for a v7-signature divider (cascade fix)', () => {
		// A block that matches v7's own signature never reaches v9.migrate(), so
		// the height→spacing carry-over must run here too or the clearance is
		// silently dropped. See migrateShapeDividerSpacing in deprecated.js.
		const migrated = v7Deprecation.migrate({
			className: 'is-style-overlay-dark',
			shapeDividerTop: 'wave',
			shapeDividerTopHeight: 80,
		});
		expect(migrated.shapeDividerTopSpacing).toBe('80px');
	});
});

describe('section deprecations - style-kit hover variation migration (v8)', () => {
	// deprecated.js exports newest-first: [v9, v8, v7, v6, v5, v4, v3, v2, v1].
	const [, v8Deprecation] = deprecated;

	// Reproduce content saved BEFORE hover-variation classes existed by taking
	// the block's REAL current serialization and stripping the hover-text
	// activation class it now adds. Deriving from the real save() (rather than
	// v8's own save()) keeps this a genuine regression guard — see the
	// identical rationale on the v7 overlay fixture above.
	const canonicalHoverMarkup = serialize(
		createBlock(metadata.name, { className: 'is-style-hover-text-light' })
	);
	const OLD_HOVER_VARIATION_MARKUP = canonicalHoverMarkup.replace(
		' dsgo-stack--has-hover-text',
		''
	);

	test('canonical markup carries the hover-text activation class', () => {
		expect(canonicalHoverMarkup).toContain('dsgo-stack--has-hover-text');
		expect(OLD_HOVER_VARIATION_MARKUP).not.toContain(
			'dsgo-stack--has-hover-text'
		);
	});

	test('old is-style-hover-text-light section (no activation class) migrates silently against current save()', () => {
		const [block] = parse(OLD_HOVER_VARIATION_MARKUP);

		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/section');
		expect(block.isValid).toBe(true);
		expect(block.attributes.className).toBe('is-style-hover-text-light');
		// The migrated block re-serializes with the activation class restored.
		expect(getBlockContent(block)).toContain('dsgo-stack--has-hover-text');
	});

	test('isEligible detects a hover-text variation lacking its activation class', () => {
		const html =
			'<div class="wp-block-designsetgo-section is-style-hover-text-light dsgo-stack"><div class="dsgo-stack__inner"></div></div>';
		expect(
			v8Deprecation.isEligible(
				{ className: 'is-style-hover-text-light' },
				[],
				{ blockNode: { innerHTML: html } }
			)
		).toBe(true);
	});

	test('isEligible detects a hover-icon variation lacking its activation class', () => {
		const html =
			'<div class="wp-block-designsetgo-section is-style-hover-icon-blue dsgo-stack"><div class="dsgo-stack__inner"></div></div>';
		expect(
			v8Deprecation.isEligible(
				{ className: 'is-style-hover-icon-blue' },
				[],
				{ blockNode: { innerHTML: html } }
			)
		).toBe(true);
	});

	test('isEligible ignores sections that already carry the matching activation class', () => {
		const html =
			'<div class="wp-block-designsetgo-section is-style-hover-text-light dsgo-stack dsgo-stack--has-hover-text"><div class="dsgo-stack__inner"></div></div>';
		expect(
			v8Deprecation.isEligible(
				{ className: 'is-style-hover-text-light' },
				[],
				{ blockNode: { innerHTML: html } }
			)
		).toBe(false);
	});

	test('isEligible detects a partial mismatch when only one of several hover families is missing its class', () => {
		const html =
			'<div class="wp-block-designsetgo-section is-style-hover-text-light is-style-hover-icon-blue dsgo-stack dsgo-stack--has-hover-icon"><div class="dsgo-stack__inner"></div></div>';
		expect(
			v8Deprecation.isEligible(
				{
					className:
						'is-style-hover-text-light is-style-hover-icon-blue',
				},
				[],
				{ blockNode: { innerHTML: html } }
			)
		).toBe(true);
	});

	test('isEligible ignores sections without a hover variation', () => {
		const html =
			'<div class="wp-block-designsetgo-section dsgo-stack"><div class="dsgo-stack__inner"></div></div>';
		expect(
			v8Deprecation.isEligible({ className: '' }, [], {
				blockNode: { innerHTML: html },
			})
		).toBe(false);
	});

	test('migrate adds no clearance when the section has no divider', () => {
		const attrs = {
			className: 'is-style-hover-text-light',
			hoverTextColor: '',
		};
		const migrated = v8Deprecation.migrate(attrs);
		expect(migrated).toEqual(attrs);
		expect(migrated.shapeDividerTopSpacing).toBeUndefined();
		expect(migrated.shapeDividerBottomSpacing).toBeUndefined();
	});

	test('migrate carries height-derived clearance for a v8-signature divider (cascade fix)', () => {
		// This is the exact gap the original PR missed: a divider section that
		// also carries a hover variation matches v8, not v9, so v9.migrate()
		// never runs. Without the carry-over here the section keeps its shape
		// divider but loses its clearance on the next save.
		const migrated = v8Deprecation.migrate({
			className: 'is-style-hover-text-light',
			shapeDividerBottom: 'wave',
			shapeDividerBottomHeight: 120,
		});
		expect(migrated.shapeDividerBottomSpacing).toBe('120px');
	});

	test('a real v8-signature divider section (hover variation + divider) migrates silently AND keeps its clearance end-to-end', () => {
		// End-to-end guard for the cascade gap: build byte-faithful v8-era
		// markup (a bottom divider with height-derived px clearance, a hover
		// variation, and NO activation class), then run it through the real
		// parse()/deprecation pipeline. It must route to v8 (not v9), migrate
		// silently, and come out the other side with BOTH the restored hover
		// activation class and its clearance preserved as an explicit spacing.
		const markup = buildOldMarkup(
			{
				className: 'is-style-hover-text-light',
				shapeDividerBottom: 'wave',
				shapeDividerBottomHeight: 120,
				backgroundColor: 'contrast',
			},
			v8Deprecation
		);

		const [block] = parse(markup);

		// A silent migration logs an info (WordPress's "Block successfully
		// updated"), which @wordpress/jest-console requires be asserted. Its
		// presence — with no accompanying warning/error — is what "silent"
		// (no "Attempt Recovery") means here.
		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/section');
		expect(block.isValid).toBe(true);
		// The carry-over ran through v8, not v9.
		expect(block.attributes.shapeDividerBottomSpacing).toBe('120px');
		// Re-serialized markup keeps the clearance and restores the hover class.
		const content = getBlockContent(block);
		expect(content).toContain('padding-bottom:120px');
		expect(content).toContain('dsgo-stack--has-hover-text');
	});
});

describe('section deprecations - height-derived px clearance migration (v9)', () => {
	// deprecated.js exports newest-first: [v9, v8, v7, v6, v5, v4, v3, v2, v1].
	const [v9Deprecation] = deprecated;

	// v9's own save() reproduces the pre-change output: the inner container's
	// shape-divider clearance is derived from the divider height and emitted as
	// a px value. The current save() instead serializes the new
	// shapeDivider{Top,Bottom}Spacing attributes and emits nothing when unset,
	// so this markup is invalid against current save() and reaches v9 by
	// save-matching.
	test('old top-divider section migrates its height-derived px clearance into shapeDividerTopSpacing', () => {
		const markup = buildOldMarkup(
			{ shapeDividerTop: 'wave', shapeDividerTopHeight: 80 },
			v9Deprecation
		);
		// Guards the fixture: the old height-derived padding must be present.
		expect(markup).toContain('padding-top:80px');

		const [block] = parse(markup);

		// Silent migration logs an informational "Block successfully updated".
		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/section');
		expect(block.isValid).toBe(true);
		expect(block.attributes.shapeDividerTop).toBe('wave');
		// migrate() carries the height-derived px into the new spacing attribute
		// as a raw CSS length.
		expect(block.attributes.shapeDividerTopSpacing).toBe('80px');
		// The current save() converts the raw length through unchanged, so the
		// exact clearance survives the round trip byte-for-byte.
		expect(getBlockContent(block)).toContain('padding-top:80px');
	});

	test('old bottom-divider section migrates its clearance into shapeDividerBottomSpacing', () => {
		const markup = buildOldMarkup(
			{ shapeDividerBottom: 'tilt', shapeDividerBottomHeight: 120 },
			v9Deprecation
		);
		expect(markup).toContain('padding-bottom:120px');

		const [block] = parse(markup);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.shapeDividerBottomSpacing).toBe('120px');
		expect(getBlockContent(block)).toContain('padding-bottom:120px');
	});

	// Regression guard for the nullable height/width change. v7/v8/v9 all render
	// a FROZEN copy of the class-based divider, not the live component: their
	// attribute schemas still default height/width to 100, and at that value the
	// historical component emitted NO size custom property. If those versions
	// ever rendered the live component again, this markup — a v9 divider left at
	// the old default height — would stop byte-matching and every such section
	// would surface "unexpected or invalid content".
	test('a v9 divider left at the old default height still migrates silently', () => {
		const markup = buildOldMarkup(
			{ shapeDividerTop: 'wave' },
			v9Deprecation
		);
		// Guards the fixture: default height meant a flat 100px clearance and no
		// inline size var at all.
		expect(markup).toContain('padding-top:100px');
		expect(markup).not.toContain('--dsgo-shape-height');

		const [block] = parse(markup);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.shapeDividerTopSpacing).toBe('100px');
	});

	test('migrate leaves an already-set spacing attribute untouched', () => {
		const migrated = v9Deprecation.migrate({
			shapeDividerTop: 'wave',
			shapeDividerTopHeight: 80,
			shapeDividerTopSpacing: 'var:preset|spacing|50',
		});
		expect(migrated.shapeDividerTopSpacing).toBe('var:preset|spacing|50');
	});

	test('migrate is a no-op for sections without a divider', () => {
		const migrated = v9Deprecation.migrate({ shapeDividerTop: '' });
		expect(migrated.shapeDividerTopSpacing).toBeUndefined();
		expect(migrated.shapeDividerBottomSpacing).toBeUndefined();
	});
});

describe('section - nullable shape divider height/width (theme inheritance)', () => {
	// shapeDivider{Top,Bottom}{Height,Width} used to default to 100 and emit no
	// custom property at that value. They now default to null ("inherit the
	// theme.json token"), which needs NO deprecation precisely because the
	// serialized markup is unchanged: WordPress never wrote the attribute to the
	// comment while it equalled the old default, and save() emitted no size var
	// then either. These tests pin both halves of that claim.
	const legacyDefaultSizeMarkup = `<!-- wp:designsetgo/section {"shapeDividerTop":"wave"} -->\n${getSaveContent(
		{ ...metadata, save },
		{ ...createBlock(metadata.name).attributes, shapeDividerTop: 'wave' },
		[]
	)}\n<!-- /wp:designsetgo/section -->`;

	test('the fixture carries no inline size var, as pre-change content did', () => {
		expect(legacyDefaultSizeMarkup).toContain('is-shape-wave');
		expect(legacyDefaultSizeMarkup).not.toContain('--dsgo-shape-height');
		expect(legacyDefaultSizeMarkup).not.toContain('--dsgo-shape-width');
	});

	test('content saved at the old default size stays valid and resolves to inherit', () => {
		const [block] = parse(legacyDefaultSizeMarkup);

		// No "Block successfully updated" info here: the block matches the
		// current save() outright, so no deprecation runs at all.
		expect(block.isValid).toBe(true);
		expect(block.attributes.shapeDividerTopHeight).toBeNull();
		expect(block.attributes.shapeDividerTopWidth).toBeNull();
	});

	test('an explicitly-sized legacy divider keeps its exact size', () => {
		const markup = `<!-- wp:designsetgo/section {"shapeDividerTop":"wave","shapeDividerTopHeight":80,"shapeDividerTopWidth":140} -->\n${getSaveContent(
			{ ...metadata, save },
			{
				...createBlock(metadata.name).attributes,
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 80,
				shapeDividerTopWidth: 140,
			},
			[]
		)}\n<!-- /wp:designsetgo/section -->`;

		const [block] = parse(markup);

		expect(block.isValid).toBe(true);
		expect(block.attributes.shapeDividerTopHeight).toBe(80);
		expect(block.attributes.shapeDividerTopWidth).toBe(140);
		expect(getBlockContent(block)).toContain('--dsgo-shape-height:80px');
		expect(getBlockContent(block)).toContain('--dsgo-shape-width:140%');
	});
});
