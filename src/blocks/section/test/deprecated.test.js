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
// deprecated.js exports deprecations newest-first: [v6, v5, v4, v3, v2, v1].
const [, , v4Deprecation] = deprecated;
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
});
