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
 *    warning; an explicit non-default value survives migration.
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

	test('old default accordion (inline height/gap) migrates silently', () => {
		const [block] = parse(OLD_MARKUP);

		expect(console).toHaveInformed();
		expect(block.name).toBe('designsetgo/image-accordion');
		expect(block.isValid).toBe(true);
		// Re-serialized with the current save(): no inline height/gap.
		expect(getBlockContent(block)).not.toContain(
			'--dsgo-image-accordion-height:'
		);
		expect(getBlockContent(block)).not.toContain(
			'--dsgo-image-accordion-gap:'
		);
		// Inherited default → attributes cleared.
		expect(block.attributes.height).toBeUndefined();
		expect(block.attributes.gap).toBeUndefined();
	});

	test('old accordion with an explicit non-default height keeps it while a default gap is dropped', () => {
		// A current block with an explicit height (gap left unset) emits only the
		// height inline. The OLD save additionally baked gap:4px (the historical
		// default) inline, which is exactly the always-inline markup we need to
		// migrate: height is a non-default override to preserve, gap is the
		// default to strip so it inherits the themeable stylesheet default.
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
		expect(block.attributes.gap).toBeUndefined();
		expect(getBlockContent(block)).toContain(
			'--dsgo-image-accordion-height:600px'
		);
		expect(getBlockContent(block)).not.toContain(
			'--dsgo-image-accordion-gap:'
		);
	});

	test('old accordion with an explicit non-default gap keeps it while a default height is dropped', () => {
		// Mirror of the case above: explicit gap (non-default) preserved, the
		// baked default height:500px stripped so it inherits.
		const canonicalGap = serialize(
			createBlock(metadata.name, { gap: '12px' })
		);
		const oldExplicit = canonicalGap.replace(
			'--dsgo-image-accordion-gap:12px;',
			'--dsgo-image-accordion-height:500px;--dsgo-image-accordion-gap:12px;'
		);
		const [block] = parse(oldExplicit);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.gap).toBe('12px');
		expect(block.attributes.height).toBeUndefined();
		expect(getBlockContent(block)).toContain(
			'--dsgo-image-accordion-gap:12px'
		);
		expect(getBlockContent(block)).not.toContain(
			'--dsgo-image-accordion-height:'
		);
	});

	test('isEligible flags old markup (inline height custom prop)', () => {
		expect(
			v1Deprecation.isEligible({}, [], { innerHTML: OLD_MARKUP })
		).toBe(true);
	});

	test('isEligible ignores current default markup', () => {
		expect(v1Deprecation.isEligible({}, [], { innerHTML: canonical })).toBe(
			false
		);
	});

	test('migrate drops default (500px/4px) height/gap but preserves explicit ones', () => {
		expect(
			v1Deprecation.migrate({
				height: '500px',
				gap: '4px',
				triggerType: 'hover',
			})
		).toEqual({ triggerType: 'hover' });
		expect(
			v1Deprecation.migrate({
				height: '600px',
				gap: '12px',
				triggerType: 'hover',
			})
		).toEqual({ height: '600px', gap: '12px', triggerType: 'hover' });
	});
});
