/**
 * Section Divider Block - save.js Tests
 *
 * Verifies a default divider serializes as bare `is-shape-inherit` markup
 * with no inline style, and that shape/height/width/flip/fill props are
 * only emitted when they differ from their CSS-inherited defaults.
 *
 * @since 2.7.0
 */

// `save.js` imports `@wordpress/block-editor`, which (in this repo's current
// dependency tree) bundles its OWN nested copy of `@wordpress/blocks`
// (block-editor requires ^14.15.0; the top-level package resolves to
// 13.10.0). `useBlockProps.save()` reads block support metadata via
// `getBlockType()` from THAT nested registry, so the block must be
// registered on the same module instance block-editor uses — registering
// via the top-level `@wordpress/blocks` import leaves block-editor's
// internal registry empty, which throws when save() runs and causes
// `serialize()` to silently no-op to the collapsed comment form. Importing
// from the nested path keeps the block-type registry and the save() call in
// sync so this test exercises the real save() output. See
// src/blocks/section/test/save.test.js for the same pattern.
import {
	createBlock,
	serialize,
	registerBlockType,
	setCategories,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';

// The block's category ("design") isn't registered in the Jest environment
// (that happens in PHP via block-categories filters), which otherwise makes
// registerBlockType() reject the block and causes createBlock()/serialize()
// to silently no-op (self-closing comment, save() never called). Register
// it so save() actually runs.
setCategories([{ slug: 'design', title: 'Design' }]);

registerBlockType(metadata.name, { ...metadata, save, edit: () => null });

const serializeWith = (attrs) => serialize(createBlock(metadata.name, attrs));

describe('section-divider save', () => {
	it('serializes a default divider as bare inherit markup (no style)', () => {
		const html = serializeWith({});
		expect(html).toContain('dsgo-section-divider__shape');
		expect(html).toContain('is-shape-inherit');
		expect(html).not.toContain('style=');
		expect(html).not.toContain('--dsgo-shape-height');
	});

	it('emits the shape slug class when set', () => {
		const html = serializeWith({ shape: 'wave' });
		expect(html).toContain('is-shape-wave');
		expect(html).not.toContain('is-shape-inherit');
	});

	it('emits height var only when height is a number', () => {
		expect(serializeWith({ height: 140 })).toContain(
			'--dsgo-shape-height:140px'
		);
	});

	it('emits fill var only when fillColor is set', () => {
		expect(serializeWith({ fillColor: '#ff0000' })).toContain(
			'--dsgo-section-divider-fill:#ff0000'
		);
	});

	it('converts a preset fill color to a CSS var', () => {
		const html = serializeWith({
			fillColor: 'var:preset|color|accent-3',
		});
		expect(html).toContain(
			'--dsgo-section-divider-fill:var(--wp--preset--color--accent-3)'
		);
		// The raw preset token must not leak into the CSS value (it's only
		// valid in the block-comment attribute header, not as a CSS var).
		expect(html).not.toContain('--dsgo-section-divider-fill:var:preset');
	});

	it('emits flip transforms only when flipped', () => {
		expect(serializeWith({ flipX: true })).toContain(
			'--dsgo-shape-flip-x:-1'
		);
		expect(serializeWith({ flipY: true })).toContain(
			'--dsgo-shape-flip-y:-1'
		);
		expect(serializeWith({})).not.toContain('--dsgo-shape-flip');
	});

	it('emits width var only for an explicit width', () => {
		expect(serializeWith({ width: 150 })).toContain(
			'--dsgo-shape-width:150%'
		);
		expect(serializeWith({})).not.toContain('--dsgo-shape-width');
	});

	it('emits an explicit 100% width so it can pin against a theme token', () => {
		// Width is nullable, so 100 is an author choice, not "unset". It must
		// serialize, otherwise a theme.json
		// settings.custom.designsetgo.shapeDivider.width would silently win.
		expect(serializeWith({ width: 100 })).toContain(
			'--dsgo-shape-width:100%'
		);
	});

	it('emits background var only when backgroundColor is set', () => {
		expect(serializeWith({ backgroundColor: '#ff0000' })).toContain(
			'--dsgo-section-divider-bg:#ff0000'
		);
		expect(serializeWith({})).not.toContain('--dsgo-section-divider-bg');
	});

	it('converts a preset background color to a CSS var', () => {
		const html = serializeWith({
			backgroundColor: 'var:preset|color|accent-3',
		});
		expect(html).toContain(
			'--dsgo-section-divider-bg:var(--wp--preset--color--accent-3)'
		);
		expect(html).not.toContain('--dsgo-section-divider-bg:var:preset');
	});
});
