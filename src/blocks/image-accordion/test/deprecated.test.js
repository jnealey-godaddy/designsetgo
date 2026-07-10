/**
 * Image Accordion Block - Themeable Height/Gap Deprecation Migration Tests
 *
 * Verifies:
 *  - the current save() omits the inline height/gap custom properties by default
 *    (unset), so the stylesheet (theme token → literal fallback) owns them;
 *  - an explicit author height/gap is still written inline;
 *  - OLD image accordions (always-inline height:500px / gap:4px) parse cleanly
 *    against the current save() + v1 deprecation and migrate silently instead of
 *    showing WordPress's "unexpected or invalid content / Attempt Recovery"
 *    warning; migration PINS whatever height/gap the old markup carried (it does
 *    not strip default values back to inherit);
 *  - a current, valid block whose explicit value equals the old default is
 *    never routed through the deprecation and keeps its attribute.
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

// deprecated.js exports newest-first: [v2, v1].
const [v2Deprecation, v1Deprecation] = deprecated;

describe('image-accordion save() - themeable height/gap', () => {
	test('default (unset) save omits the inline height/gap custom props', () => {
		const markup = serialize(createBlock(metadata.name));
		expect(markup).not.toContain('--dsgo-image-accordion-height:');
		expect(markup).not.toContain('--dsgo-image-accordion-gap:');
		// The other custom props are still emitted.
		expect(markup).toContain('--dsgo-image-accordion-expanded-ratio:');
	});

	test('explicit height and gap are written inline', () => {
		const markup = serialize(
			createBlock(metadata.name, { height: '600px', gap: '12px' })
		);
		expect(markup).toContain('--dsgo-image-accordion-height:600px');
		expect(markup).toContain('--dsgo-image-accordion-gap:12px');
	});

	test('an explicit height alone does not emit an inline gap', () => {
		const markup = serialize(
			createBlock(metadata.name, { height: '600px' })
		);
		expect(markup).toContain('--dsgo-image-accordion-height:600px');
		expect(markup).not.toContain('--dsgo-image-accordion-gap:');
	});
});

describe('image-accordion deprecations - v1 themeable height/gap migration', () => {
	// Derive byte-exact OLD markup from the current canonical output: the
	// pre-refactor format always baked height:500px / gap:4px inline right
	// before the expanded-ratio custom property.
	const canonical = serialize(createBlock(metadata.name));
	const OLD_MARKUP = canonical.replace(
		'--dsgo-image-accordion-expanded-ratio:',
		'--dsgo-image-accordion-height:500px;--dsgo-image-accordion-gap:4px;--dsgo-image-accordion-expanded-ratio:'
	);

	test('derived old markup differs from canonical as expected', () => {
		expect(canonical).not.toContain('--dsgo-image-accordion-height:');
		expect(OLD_MARKUP).toContain('--dsgo-image-accordion-height:500px');
		expect(OLD_MARKUP).toContain('--dsgo-image-accordion-gap:4px');
	});

	test('old default accordion (inline height/gap) migrates silently and stays pinned', () => {
		const [block] = parse(OLD_MARKUP);

		expect(console).toHaveInformed();
		expect(block.name).toBe('designsetgo/image-accordion');
		expect(block.isValid).toBe(true);
		// Passthrough migrate pins the old values; the block renders exactly as
		// authored (no silent change to inherit).
		expect(block.attributes.height).toBe('500px');
		expect(block.attributes.gap).toBe('4px');
		expect(getBlockContent(block)).toContain(
			'--dsgo-image-accordion-height:500px'
		);
		expect(getBlockContent(block)).toContain(
			'--dsgo-image-accordion-gap:4px'
		);
	});

	test('old accordion with an explicit non-default height migrates and keeps both values pinned', () => {
		// A current block with an explicit height (gap left unset) emits only the
		// height inline. The OLD save additionally baked gap:4px inline; that
		// always-both markup mismatches the current save() and is migrated. Both
		// values are preserved (pinned) — nothing is silently dropped.
		const canonicalHeight = serialize(
			createBlock(metadata.name, { height: '600px' })
		);
		const oldExplicit = canonicalHeight.replace(
			'--dsgo-image-accordion-expanded-ratio:',
			'--dsgo-image-accordion-gap:4px;--dsgo-image-accordion-expanded-ratio:'
		);
		const [block] = parse(oldExplicit);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.height).toBe('600px');
		expect(block.attributes.gap).toBe('4px');
		expect(getBlockContent(block)).toContain(
			'--dsgo-image-accordion-height:600px'
		);
		expect(getBlockContent(block)).toContain(
			'--dsgo-image-accordion-gap:4px'
		);
	});

	test('a current block whose explicit height equals the old default is NOT migrated', () => {
		// Regression guard: height "500px" (gap unset) is a plausible explicit
		// author choice equal to the historical default. The current save() emits
		// only the height prop, so this markup matches the current save() and is
		// valid WITHOUT any deprecation — the value survives untouched. There is
		// no toHaveInformed() assertion, so if a spurious migration fired the
		// jest-console matcher would fail this test.
		const currentMarkup = serialize(
			createBlock(metadata.name, { height: '500px' })
		);
		const [block] = parse(currentMarkup);
		expect(block.isValid).toBe(true);
		expect(block.attributes.height).toBe('500px');
		expect(getBlockContent(block)).toContain(
			'--dsgo-image-accordion-height:500px'
		);
	});

	test('a current block with BOTH height and gap explicit is NOT force-migrated', () => {
		// The old "always both props inline" markup is byte-identical to a current
		// block that sets both height and gap explicitly. With no isEligible, this
		// valid block matches the current save() and is skipped (no migration
		// pass, no spurious console.info — which the jest-console matcher would
		// otherwise flag as unexpected).
		const currentMarkup = serialize(
			createBlock(metadata.name, { height: '700px', gap: '20px' })
		);
		const [block] = parse(currentMarkup);
		expect(block.isValid).toBe(true);
		expect(block.attributes.height).toBe('700px');
		expect(block.attributes.gap).toBe('20px');
		expect(getBlockContent(block)).toContain(
			'--dsgo-image-accordion-height:700px'
		);
		expect(getBlockContent(block)).toContain(
			'--dsgo-image-accordion-gap:20px'
		);
	});

	test('migrate is a passthrough that pins values (never strips defaults)', () => {
		expect(
			v1Deprecation.migrate({
				height: '500px',
				gap: '4px',
				triggerType: 'hover',
			})
		).toEqual({ height: '500px', gap: '4px', triggerType: 'hover' });
		expect(
			v1Deprecation.migrate({
				height: '600px',
				gap: '12px',
				triggerType: 'hover',
			})
		).toEqual({ height: '600px', gap: '12px', triggerType: 'hover' });
	});
});

describe('image-accordion save() - overlay gated on enableOverlay', () => {
	test('overlay enabled (default) emits the overlay props and data-enable-overlay', () => {
		const markup = serialize(createBlock(metadata.name));
		expect(markup).toContain('--dsgo-image-accordion-overlay-color:');
		expect(markup).toContain('--dsgo-image-accordion-overlay-opacity:');
		expect(markup).toContain(
			'--dsgo-image-accordion-overlay-opacity-expanded:'
		);
		expect(markup).toContain('data-enable-overlay="true"');
	});

	test('overlay disabled omits all three overlay props and data-enable-overlay', () => {
		const markup = serialize(
			createBlock(metadata.name, { enableOverlay: false })
		);
		expect(markup).not.toContain('--dsgo-image-accordion-overlay-color:');
		expect(markup).not.toContain('--dsgo-image-accordion-overlay-opacity:');
		expect(markup).not.toContain(
			'--dsgo-image-accordion-overlay-opacity-expanded:'
		);
		expect(markup).not.toContain('data-enable-overlay');
		// Non-overlay custom props are still present.
		expect(markup).toContain('--dsgo-image-accordion-expanded-ratio:');
		expect(markup).toContain('data-trigger-type="hover"');
	});

	test('overlay disabled ignores custom overlay opacity/color attribute values', () => {
		// Even with non-default overlay values set, disabling the overlay must
		// leave no overlay trace in the markup — the whole point of the fix.
		// Inspect the rendered HTML only — the attribute values still live in the
		// block comment (that is correct; they persist so re-enabling restores
		// them), they just must not surface in the markup.
		const html = getBlockContent(
			createBlock(metadata.name, {
				enableOverlay: false,
				overlayOpacity: 60,
				overlayOpacityExpanded: 35,
				overlayColor: '#123456',
			})
		);
		expect(html).not.toContain('--dsgo-image-accordion-overlay');
		expect(html).not.toContain('#123456');
		expect(html).not.toContain('data-enable-overlay');
	});
});

describe('image-accordion deprecations - v2 overlay-gating migration', () => {
	test('an overlay-ON block matches the current save() directly (no deprecation)', () => {
		// The enabled output is byte-identical to v2, so overlay-on content is
		// valid without any migration. No toHaveInformed() assertion here means a
		// spurious deprecation pass would trip the jest-console matcher.
		const markup = serialize(createBlock(metadata.name));
		const [block] = parse(markup);
		expect(block.isValid).toBe(true);
		expect(block.attributes.enableOverlay).toBe(true);
		expect(getBlockContent(block)).toContain('data-enable-overlay="true"');
	});

	test('old overlay-OFF markup (overlay props + data-enable-overlay="false") migrates silently and re-serializes lean', () => {
		// Reconstruct the v2 overlay-off format: the overlay props and
		// data-enable-overlay were baked in even with the overlay disabled.
		// Build it from a current disabled block, then splice the stale bits back.
		const disabled = serialize(
			createBlock(metadata.name, { enableOverlay: false })
		);
		const OLD_OFF = disabled
			.replace(
				'--dsgo-image-accordion-transition:0.5s',
				'--dsgo-image-accordion-transition:0.5s;--dsgo-image-accordion-overlay-color:#000000;--dsgo-image-accordion-overlay-opacity:0.4;--dsgo-image-accordion-overlay-opacity-expanded:0.2'
			)
			.replace(
				'data-default-expanded="0"',
				'data-default-expanded="0" data-enable-overlay="false"'
			);

		// Sanity: the stale markup really carries the bits the current save drops.
		expect(OLD_OFF).toContain(
			'--dsgo-image-accordion-overlay-color:#000000'
		);
		expect(OLD_OFF).toContain('data-enable-overlay="false"');

		const [block] = parse(OLD_OFF);

		expect(console).toHaveInformed();
		expect(block.name).toBe('designsetgo/image-accordion');
		expect(block.isValid).toBe(true);
		expect(block.attributes.enableOverlay).toBe(false);
		// Re-serialized markup drops the dead overlay props and the marker.
		const migrated = getBlockContent(block);
		expect(migrated).not.toContain('--dsgo-image-accordion-overlay');
		expect(migrated).not.toContain('data-enable-overlay');
	});

	test('v2 migrate is a passthrough', () => {
		expect(
			v2Deprecation.migrate({
				enableOverlay: false,
				overlayOpacity: 40,
				triggerType: 'hover',
			})
		).toEqual({
			enableOverlay: false,
			overlayOpacity: 40,
			triggerType: 'hover',
		});
	});
});
