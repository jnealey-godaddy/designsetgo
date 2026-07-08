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

// deprecated.js exports newest-first: [v1].
const [v1Deprecation] = deprecated;

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

	test('a current block whose explicit height equals the old default is NOT routed through the deprecation and keeps its value', () => {
		// Regression guard: height "500px" (gap unset) is a plausible explicit
		// author choice equal to the historical default. The current save() emits
		// only the height prop, so this markup matches the current save() and is
		// valid WITHOUT any deprecation — the value must survive untouched.
		const currentMarkup = serialize(
			createBlock(metadata.name, { height: '500px' })
		);
		// isEligible must not flag it (only the height prop is present, not gap).
		expect(
			v1Deprecation.isEligible({}, [], { innerHTML: currentMarkup })
		).toBe(false);

		const [block] = parse(currentMarkup);
		expect(block.isValid).toBe(true);
		expect(block.attributes.height).toBe('500px');
		expect(getBlockContent(block)).toContain(
			'--dsgo-image-accordion-height:500px'
		);
	});

	test('isEligible flags old markup (both inline height + gap custom props)', () => {
		expect(
			v1Deprecation.isEligible({}, [], { innerHTML: OLD_MARKUP })
		).toBe(true);
	});

	test('isEligible ignores current default markup and single-prop markup', () => {
		expect(v1Deprecation.isEligible({}, [], { innerHTML: canonical })).toBe(
			false
		);
		// A current block that sets only one of the two explicitly carries only
		// one inline prop → not the old always-both signature.
		const heightOnly = serialize(
			createBlock(metadata.name, { height: '600px' })
		);
		expect(
			v1Deprecation.isEligible({}, [], { innerHTML: heightOnly })
		).toBe(false);
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
