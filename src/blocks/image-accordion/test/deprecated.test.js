/**
 * Image Accordion Block - Deprecation / Migration Tests
 *
 * Covers two transitions, newest-first [v2, v1]:
 *
 *  - v2 (themeable overlay): the current save() writes the three overlay custom
 *    properties (color / opacity / opacity-expanded) ONLY when the author set
 *    them; unset they are omitted so the item stylesheet default (parent var →
 *    theme token → literal) owns the scrim while it stays ENABLED. Older content
 *    that always baked the overlay props from the #000000 / 40 / 20 defaults
 *    migrates silently and DROPS default-valued overlay props so they inherit;
 *    an explicitly customised overlay value is preserved.
 *
 *  - v1 (themeable height/gap): unchanged pinning behaviour for height/gap, now
 *    also strips default-valued overlay (a v1-era block baked BOTH height/gap and
 *    overlay from defaults, so a default overlay there is provably implicit).
 *
 * Also verifies that valid current content — a block whose overlay props are all
 * explicit, or whose only customisation equals a historical default — is never
 * force-migrated (no isEligible on either deprecation).
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

describe('image-accordion save() - themeable overlay', () => {
	test('default (unset) save omits the overlay custom props but keeps the scrim on', () => {
		const markup = serialize(createBlock(metadata.name));
		expect(markup).not.toContain('--dsgo-image-accordion-overlay-color');
		expect(markup).not.toContain('--dsgo-image-accordion-overlay-opacity');
		expect(markup).not.toContain(
			'--dsgo-image-accordion-overlay-opacity-expanded'
		);
		// The scrim is still enabled — only its values inherit.
		expect(markup).toContain('data-enable-overlay="true"');
		// Non-overlay custom props are still emitted.
		expect(markup).toContain('--dsgo-image-accordion-expanded-ratio:');
	});

	test('explicit overlay props are written inline', () => {
		const markup = serialize(
			createBlock(metadata.name, {
				overlayColor: '#ff0000',
				overlayOpacity: 90,
				overlayOpacityExpanded: 35,
			})
		);
		expect(markup).toContain(
			'--dsgo-image-accordion-overlay-color:#ff0000'
		);
		expect(markup).toContain('--dsgo-image-accordion-overlay-opacity:0.9');
		expect(markup).toContain(
			'--dsgo-image-accordion-overlay-opacity-expanded:0.35'
		);
	});

	test('an explicit overlay opacity alone does not emit color or expanded', () => {
		const markup = serialize(
			createBlock(metadata.name, { overlayOpacity: 60 })
		);
		expect(markup).toContain('--dsgo-image-accordion-overlay-opacity:0.6');
		expect(markup).not.toContain('--dsgo-image-accordion-overlay-color');
		expect(markup).not.toContain(
			'--dsgo-image-accordion-overlay-opacity-expanded'
		);
	});

	test('an explicit overlay opacity of 0 is emitted (not treated as unset)', () => {
		// hasExplicitNumber() uses a typeof guard, not truthiness, so a
		// deliberately transparent scrim (0) must serialise as an explicit
		// `:0` value rather than being dropped to the inherit fallback (which
		// would resolve to the 0.4 literal — the opposite of what was chosen).
		// Guards against a future "simplification" of the predicate to truthiness.
		const markup = serialize(
			createBlock(metadata.name, {
				overlayOpacity: 0,
				overlayOpacityExpanded: 0,
			})
		);
		expect(markup).toContain('--dsgo-image-accordion-overlay-opacity:0');
		expect(markup).toContain(
			'--dsgo-image-accordion-overlay-opacity-expanded:0'
		);
	});

	test('a block with an explicit overlay opacity of 0 stays valid without migration', () => {
		// The 0 value is byte-stable through the current save() (no deprecation
		// pass), so it must not be routed through a migration that could strip it.
		const [block] = parse(
			serialize(createBlock(metadata.name, { overlayOpacity: 0 }))
		);
		expect(block.isValid).toBe(true);
		expect(block.attributes.overlayOpacity).toBe(0);
	});
});

describe('image-accordion deprecations - v2 themeable overlay migration', () => {
	// Derive byte-exact v2-era markup from the current canonical output: v2 always
	// baked the three overlay props (from #000000 / 40 / 20) right after the
	// transition custom property; height/gap were already omit-when-unset.
	const canonical = serialize(createBlock(metadata.name));
	const V2_MARKUP = canonical.replace(
		'--dsgo-image-accordion-transition:0.5s',
		'--dsgo-image-accordion-transition:0.5s;--dsgo-image-accordion-overlay-color:#000000;--dsgo-image-accordion-overlay-opacity:0.4;--dsgo-image-accordion-overlay-opacity-expanded:0.2'
	);

	test('derived v2 markup differs from canonical as expected', () => {
		expect(canonical).not.toContain('--dsgo-image-accordion-overlay-color');
		expect(V2_MARKUP).toContain(
			'--dsgo-image-accordion-overlay-color:#000000'
		);
		expect(V2_MARKUP).toContain(
			'--dsgo-image-accordion-overlay-opacity:0.4'
		);
	});

	test('v2 default-overlay accordion migrates silently and inherits (overlay props dropped)', () => {
		const [block] = parse(V2_MARKUP);

		expect(console).toHaveInformed();
		expect(block.name).toBe('designsetgo/image-accordion');
		expect(block.isValid).toBe(true);
		// Default-valued overlay is provably implicit → dropped so it inherits.
		expect(block.attributes.overlayColor).toBeUndefined();
		expect(block.attributes.overlayOpacity).toBeUndefined();
		expect(block.attributes.overlayOpacityExpanded).toBeUndefined();

		const content = getBlockContent(block);
		expect(content).not.toContain('--dsgo-image-accordion-overlay-color');
		expect(content).not.toContain('--dsgo-image-accordion-overlay-opacity');
		// The scrim stays enabled after migration.
		expect(content).toContain('data-enable-overlay="true"');
	});

	test('v2 with an explicitly customised overlay opacity keeps that value', () => {
		// A v2-era block that set overlayOpacity:60 (color / expanded left at the
		// baked defaults). Build it from the current save output for that attr by
		// wrapping the emitted opacity with the two default props v2 always baked.
		const canonical60 = serialize(
			createBlock(metadata.name, { overlayOpacity: 60 })
		);
		const V2_OPACITY60 = canonical60.replace(
			'--dsgo-image-accordion-overlay-opacity:0.6',
			'--dsgo-image-accordion-overlay-color:#000000;--dsgo-image-accordion-overlay-opacity:0.6;--dsgo-image-accordion-overlay-opacity-expanded:0.2'
		);
		const [block] = parse(V2_OPACITY60);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		// The author's 60 survives; the two defaults inherit.
		expect(block.attributes.overlayOpacity).toBe(60);
		expect(block.attributes.overlayColor).toBeUndefined();
		expect(block.attributes.overlayOpacityExpanded).toBeUndefined();

		const content = getBlockContent(block);
		expect(content).toContain('--dsgo-image-accordion-overlay-opacity:0.6');
		expect(content).not.toContain('--dsgo-image-accordion-overlay-color');
	});

	test('a current block with all three overlay props explicit is NOT force-migrated', () => {
		// All-explicit overlay markup is byte-identical under the current save()
		// and v2.save(). With no isEligible it matches the current save() and is
		// valid WITHOUT a deprecation pass — no toHaveInformed(), so a spurious
		// migration would trip the jest-console matcher.
		const currentMarkup = serialize(
			createBlock(metadata.name, {
				overlayColor: '#123456',
				overlayOpacity: 55,
				overlayOpacityExpanded: 33,
			})
		);
		const [block] = parse(currentMarkup);
		expect(block.isValid).toBe(true);
		expect(block.attributes.overlayColor).toBe('#123456');
		expect(block.attributes.overlayOpacity).toBe(55);
		expect(block.attributes.overlayOpacityExpanded).toBe(33);
	});

	test('v2 migrate drops default overlay, keeps explicit, and passes other attrs through', () => {
		expect(
			v2Deprecation.migrate({
				overlayColor: '#000000',
				overlayOpacity: 40,
				overlayOpacityExpanded: 20,
				enableOverlay: true,
				triggerType: 'hover',
			})
		).toEqual({ enableOverlay: true, triggerType: 'hover' });

		expect(
			v2Deprecation.migrate({
				overlayColor: '#ff0000',
				overlayOpacity: 90,
				overlayOpacityExpanded: 20,
			})
		).toEqual({ overlayColor: '#ff0000', overlayOpacity: 90 });
	});
});

describe('image-accordion deprecations - v1 themeable height/gap migration', () => {
	// A genuine v1-era block baked BOTH height:500px / gap:4px inline AND the
	// three overlay props. Derive it byte-exactly from the current canonical.
	const canonical = serialize(createBlock(metadata.name));
	const V1_MARKUP = canonical
		.replace(
			'--dsgo-image-accordion-transition:0.5s',
			'--dsgo-image-accordion-transition:0.5s;--dsgo-image-accordion-overlay-color:#000000;--dsgo-image-accordion-overlay-opacity:0.4;--dsgo-image-accordion-overlay-opacity-expanded:0.2'
		)
		.replace(
			'--dsgo-image-accordion-expanded-ratio:',
			'--dsgo-image-accordion-height:500px;--dsgo-image-accordion-gap:4px;--dsgo-image-accordion-expanded-ratio:'
		);

	test('derived v1 markup bakes height/gap and overlay', () => {
		expect(V1_MARKUP).toContain('--dsgo-image-accordion-height:500px');
		expect(V1_MARKUP).toContain('--dsgo-image-accordion-gap:4px');
		expect(V1_MARKUP).toContain(
			'--dsgo-image-accordion-overlay-color:#000000'
		);
	});

	test('v1 accordion migrates: height/gap pinned, default overlay dropped', () => {
		const [block] = parse(V1_MARKUP);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		// height/gap are pinned (old markup can't distinguish explicit from
		// implicit defaults, so they are preserved).
		expect(block.attributes.height).toBe('500px');
		expect(block.attributes.gap).toBe('4px');
		// Overlay defaults are provably implicit here → inherited.
		expect(block.attributes.overlayColor).toBeUndefined();
		expect(block.attributes.overlayOpacity).toBeUndefined();

		const content = getBlockContent(block);
		expect(content).toContain('--dsgo-image-accordion-height:500px');
		expect(content).toContain('--dsgo-image-accordion-gap:4px');
		expect(content).not.toContain('--dsgo-image-accordion-overlay-color');
	});

	test('a current block whose explicit height equals the old default is NOT migrated', () => {
		// height "500px" (gap unset, no overlay) is a plausible explicit author
		// choice equal to the historical default. The current save() emits only
		// the height prop, so this markup is valid WITHOUT any deprecation.
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

	test('v1 migrate pins height/gap and strips default overlay', () => {
		expect(
			v1Deprecation.migrate({
				height: '500px',
				gap: '4px',
				overlayColor: '#000000',
				overlayOpacity: 40,
				overlayOpacityExpanded: 20,
				triggerType: 'hover',
			})
		).toEqual({ height: '500px', gap: '4px', triggerType: 'hover' });

		// A pinned non-default height alongside an explicitly customised overlay.
		expect(
			v1Deprecation.migrate({
				height: '600px',
				gap: '12px',
				overlayOpacity: 70,
			})
		).toEqual({ height: '600px', gap: '12px', overlayOpacity: 70 });
	});
});
