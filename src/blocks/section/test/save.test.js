/**
 * Section Block - save.js Tests
 *
 * Verifies shape dividers render as class-based markup (CSS mask-image
 * contract) with no inline SVG, and that the theme-inherit option and
 * fill/band color defaults behave as specified.
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

	test('fill defaults to section background color when set', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				backgroundColor: 'contrast',
			})
		);
		expect(html).toContain('--dsgo-shape-fill');
	});
});
