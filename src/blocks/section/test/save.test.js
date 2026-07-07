/**
 * Section Block - save.js Tests
 *
 * Verifies shape dividers render as class-based markup (CSS mask-image
 * contract) with no inline SVG, that the theme-inherit option works, that the
 * shape region carries no fill (it is transparent / see-through to the
 * section background), and that the position-aware vertical-flip default is
 * emitted correctly.
 *
 * @since 2.6.0
 */

// `save.js` imports `@wordpress/block-editor`, which (in this repo's current
// dependency tree) bundles its OWN nested copy of `@wordpress/blocks`
// (block-editor requires ^14.15.0; the top-level package resolves to
// 13.10.0). `useBlockProps.save()` / `useInnerBlocksProps.save()` read block
// support metadata via `getBlockType()` from THAT nested registry, so the
// block must be registered on the same module instance block-editor uses —
// registering via the top-level `@wordpress/blocks` import leaves
// block-editor's internal registry empty, which throws when save() runs
// (`useBlockProps.save` needs `blockType.attributes.align`, etc.) and causes
// `serialize()` to silently no-op to the collapsed comment form. Importing
// from the nested path keeps the block-type registry and the save() call in
// sync so this test exercises the real save() output.
import {
	createBlock,
	serialize,
	registerBlockType,
	setCategories,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';

// The block's category ("designsetgo") isn't registered in the Jest
// environment (that happens in PHP via block-categories filters), which
// otherwise makes registerBlockType() reject the block and causes
// createBlock()/serialize() to silently no-op (self-closing comment, save()
// never called). Register it so save() actually runs.
setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save });

describe('section save - shape dividers', () => {
	test('save emits class-based divider, no inline SVG', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 80,
			})
		);
		expect(html).toContain('dsgo-shape-divider--top');
		expect(html).toContain('is-shape-wave');
		expect(html).not.toContain('<svg');
	});

	test('save emits is-shape-inherit for inherit value', () => {
		expect(
			serialize(
				createBlock(metadata.name, { shapeDividerBottom: 'inherit' })
			)
		).toContain('is-shape-inherit');
	});

	test('default divider omits height/width custom props (CSS defaults apply)', () => {
		const html = serialize(
			createBlock(metadata.name, { shapeDividerTop: 'wave' })
		);
		expect(html).toContain('is-shape-wave');
		expect(html).not.toContain('--dsgo-shape-height');
		expect(html).not.toContain('--dsgo-shape-width');
	});

	test('non-default height is emitted', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 80,
			})
		);
		expect(html).toContain('--dsgo-shape-height:80px');
	});

	test('shape region carries no fill var (transparent / see-through)', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				backgroundColor: 'contrast',
			})
		);
		expect(html).not.toContain('--dsgo-shape-fill');
	});

	test('bottom dividers flip vertically by default; top dividers do not', () => {
		const bottom = serialize(
			createBlock(metadata.name, { shapeDividerBottom: 'wave' })
		);
		expect(bottom).toContain('dsgo-shape-divider--bottom');
		expect(bottom).toContain('is-flip-y');

		const top = serialize(
			createBlock(metadata.name, { shapeDividerTop: 'wave' })
		);
		expect(top).toContain('dsgo-shape-divider--top');
		expect(top).not.toContain('is-flip-y');
	});

	test('flipY inverts the per-position default (bottom + flipY = not flipped)', () => {
		const bottomFlipped = serialize(
			createBlock(metadata.name, {
				shapeDividerBottom: 'wave',
				shapeDividerBottomFlipY: true,
			})
		);
		expect(bottomFlipped).not.toContain('is-flip-y');
	});
});

describe('section save - overlay class', () => {
	test('no overlay by default', () => {
		const html = serialize(createBlock(metadata.name, {}));
		expect(html).not.toContain('dsgo-stack--has-overlay');
	});

	test('overlayColor emits overlay class + inline color var', () => {
		const html = serialize(
			createBlock(metadata.name, { overlayColor: 'contrast' })
		);
		expect(html).toContain('dsgo-stack--has-overlay');
		expect(html).toContain('--dsgo-overlay-color');
	});

	test('is-style-overlay-dark className emits overlay class without inline color var', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-overlay-dark' })
		);
		expect(html).toContain('dsgo-stack--has-overlay');
		// Color comes from the style variation's stylesheet, not inline.
		expect(html).not.toContain('--dsgo-overlay-color');
	});

	test('future is-style-overlay-* variations also enable the overlay', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-overlay-light' })
		);
		expect(html).toContain('dsgo-stack--has-overlay');
	});

	test('unrelated is-style-* variation does not enable the overlay', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-rounded' })
		);
		expect(html).not.toContain('dsgo-stack--has-overlay');
	});
});
