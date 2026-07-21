/**
 * Grid Block - Style-Variation Deprecation Migration Tests
 *
 * Verifies OLD grids saved before overlay support existed (and before hover
 * style-kit variation detection existed) still parse cleanly against the
 * CURRENT save() + deprecations pipeline instead of showing WordPress's
 * "unexpected or invalid content / Attempt Recovery" warning.
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

describe('grid deprecations - style-kit overlay variation migration', () => {
	// deprecated.js exports:
	// [legacyResponsiveTabletClass, legacyMinWidth, styleVariationClasses, v1].
	const [, , styleVariationClassesDeprecation] = deprecated;

	const canonicalOverlayMarkup = serialize(
		createBlock(metadata.name, { className: 'is-style-overlay-dark' })
	);
	const OLD_OVERLAY_VARIATION_MARKUP = canonicalOverlayMarkup.replace(
		' dsgo-grid--has-overlay',
		''
	);

	test('canonical markup carries the overlay class', () => {
		expect(canonicalOverlayMarkup).toContain('dsgo-grid--has-overlay');
		expect(OLD_OVERLAY_VARIATION_MARKUP).not.toContain(
			'dsgo-grid--has-overlay'
		);
	});

	test('old is-style-overlay-dark grid (no overlay class) migrates silently against current save()', () => {
		const [block] = parse(OLD_OVERLAY_VARIATION_MARKUP);

		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/grid');
		expect(block.isValid).toBe(true);
		expect(block.attributes.className).toBe('is-style-overlay-dark');
		expect(getBlockContent(block)).toContain('dsgo-grid--has-overlay');
	});

	test('isEligible detects an overlay variation lacking the overlay class', () => {
		const html =
			'<div class="wp-block-designsetgo-grid is-style-overlay-dark dsgo-grid"><div class="dsgo-grid__inner"></div></div>';
		expect(
			styleVariationClassesDeprecation.isEligible(
				{ className: 'is-style-overlay-dark' },
				[],
				{ blockNode: { innerHTML: html } }
			)
		).toBe(true);
	});

	test('isEligible ignores grids that already carry the overlay class', () => {
		const html =
			'<div class="wp-block-designsetgo-grid is-style-overlay-dark dsgo-grid dsgo-grid--has-overlay"><div class="dsgo-grid__inner"></div></div>';
		expect(
			styleVariationClassesDeprecation.isEligible(
				{ className: 'is-style-overlay-dark' },
				[],
				{ blockNode: { innerHTML: html } }
			)
		).toBe(false);
	});

	test('isEligible ignores grids without an overlay variation', () => {
		const html =
			'<div class="wp-block-designsetgo-grid dsgo-grid"><div class="dsgo-grid__inner"></div></div>';
		expect(
			styleVariationClassesDeprecation.isEligible({ className: '' }, [], {
				blockNode: { innerHTML: html },
			})
		).toBe(false);
	});

	test('migrate is a passthrough', () => {
		const attrs = { className: 'is-style-overlay-dark', overlayColor: '' };
		expect(styleVariationClassesDeprecation.migrate(attrs)).toBe(attrs);
	});
});

describe('grid deprecations - style-kit hover variation migration', () => {
	const [, , styleVariationClassesDeprecation] = deprecated;

	const canonicalHoverMarkup = serialize(
		createBlock(metadata.name, { className: 'is-style-hover-text-light' })
	);
	const OLD_HOVER_VARIATION_MARKUP = canonicalHoverMarkup.replace(
		' dsgo-grid--has-hover-text',
		''
	);

	test('canonical markup carries the hover-text activation class', () => {
		expect(canonicalHoverMarkup).toContain('dsgo-grid--has-hover-text');
		expect(OLD_HOVER_VARIATION_MARKUP).not.toContain(
			'dsgo-grid--has-hover-text'
		);
	});

	test('old is-style-hover-text-light grid (no activation class) migrates silently against current save()', () => {
		const [block] = parse(OLD_HOVER_VARIATION_MARKUP);

		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/grid');
		expect(block.isValid).toBe(true);
		expect(block.attributes.className).toBe('is-style-hover-text-light');
		expect(getBlockContent(block)).toContain('dsgo-grid--has-hover-text');
	});

	test('isEligible detects a hover-icon variation lacking its activation class', () => {
		const html =
			'<div class="wp-block-designsetgo-grid is-style-hover-icon-blue dsgo-grid"><div class="dsgo-grid__inner"></div></div>';
		expect(
			styleVariationClassesDeprecation.isEligible(
				{ className: 'is-style-hover-icon-blue' },
				[],
				{ blockNode: { innerHTML: html } }
			)
		).toBe(true);
	});

	test('isEligible ignores grids without a hover variation', () => {
		const html =
			'<div class="wp-block-designsetgo-grid dsgo-grid"><div class="dsgo-grid__inner"></div></div>';
		expect(
			styleVariationClassesDeprecation.isEligible({ className: '' }, [], {
				blockNode: { innerHTML: html },
			})
		).toBe(false);
	});

	test('migrate is a passthrough', () => {
		const attrs = {
			className: 'is-style-hover-text-light',
			hoverTextColor: '',
		};
		expect(styleVariationClassesDeprecation.migrate(attrs)).toBe(attrs);
	});
});

describe('grid deprecations - legacyMinWidth precedence over styleVariationClasses', () => {
	// A legacy grid can match BOTH deprecations' isEligible at once: a
	// hard-coded `minmax(...)` inline style (legacyMinWidth) AND a style-kit
	// variation class with no matching activation class (styleVariationClasses).
	// legacyMinWidth must be listed first so it resolves for this ambiguous
	// case — it's the only one that recovers `columnMinWidth` on migrate();
	// styleVariationClasses.migrate() is a passthrough and would silently
	// drop it.
	const [, legacyMinWidthDeprecation, styleVariationClassesDeprecation] =
		deprecated;

	const canonicalOverlayMarkup = serialize(
		createBlock(metadata.name, { className: 'is-style-overlay-dark' })
	);
	const COMBINED_MARKUP = canonicalOverlayMarkup
		.replace(' dsgo-grid--has-overlay', '')
		.replace(
			'grid-template-columns:repeat(3, 1fr)',
			'grid-template-columns:repeat(3, minmax(200px, 1fr))'
		);

	test('combined-shape markup carries neither the overlay class nor a columnMinWidth-derived template', () => {
		expect(COMBINED_MARKUP).not.toContain('dsgo-grid--has-overlay');
		expect(COMBINED_MARKUP).toContain(
			'grid-template-columns:repeat(3, minmax(200px, 1fr))'
		);
	});

	test('both legacyMinWidth and styleVariationClasses isEligible match the combined-shape markup', () => {
		const attributes = { className: 'is-style-overlay-dark' };
		expect(
			legacyMinWidthDeprecation.isEligible(attributes, [], {
				blockNode: { innerHTML: COMBINED_MARKUP },
			})
		).toBe(true);
		expect(
			styleVariationClassesDeprecation.isEligible(attributes, [], {
				blockNode: { innerHTML: COMBINED_MARKUP },
			})
		).toBe(true);
	});

	test('legacyMinWidth is listed before styleVariationClasses in the deprecation array', () => {
		expect(deprecated.indexOf(legacyMinWidthDeprecation)).toBeLessThan(
			deprecated.indexOf(styleVariationClassesDeprecation)
		);
	});

	test('parsing the combined-shape markup recovers columnMinWidth instead of silently dropping it', () => {
		const [block] = parse(COMBINED_MARKUP);

		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/grid');
		expect(block.isValid).toBe(true);
		expect(block.attributes.columnMinWidth).toBe('200px');
	});
});
