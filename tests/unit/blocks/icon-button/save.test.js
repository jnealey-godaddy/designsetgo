/**
 * Icon Button Block - save() Tests
 *
 * Covers the justification-wrapper refactor: the block root is now a plain
 * block-level `.dsgo-justify` wrapper (capped at the content column by core's
 * constrained layout), with the visible button shrink-wrapped inside it and
 * carrying all visual supports (border/color/shadow/typography).
 */

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks;
// useBlockProps.save() (used by save.js) resolves block supports against
// THAT copy's registry, so registration/parsing must go through the same
// instance or save() silently collapses to a self-closing comment instead of
// throwing (see icon-align-deprecation.test.js for the same pattern).
import {
	createBlock,
	serialize,
	registerBlockType,
	setCategories,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../../../../src/blocks/icon-button/block.json';
import save from '../../../../src/blocks/icon-button/save';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);
registerBlockType(metadata.name, { ...metadata, save });

describe('icon-button save', () => {
	it('wraps the button in a block-level justification wrapper', () => {
		const html = serialize(
			createBlock(metadata.name, {
				text: 'Go',
				url: 'https://example.com',
				justification: 'left',
			})
		);

		// Wrapper is the block root and carries the positioning classes.
		expect(html).toMatch(
			/<div class="[^"]*wp-block-designsetgo-icon-button[^"]*dsgo-justify dsgo-justify--left/
		);
		// The visible button lives inside it and keeps its own classes.
		expect(html).toMatch(
			/<a class="dsgo-icon-button[^"]*wp-element-button/
		);
	});

	it('puts border radius on the button, not the wrapper', () => {
		const html = serialize(
			createBlock(metadata.name, {
				text: 'Go',
				style: { border: { radius: '4px' } },
			})
		);

		expect(html).toMatch(/<button[^>]*style="[^"]*border-radius:4px/);
		expect(html).not.toMatch(/<div[^>]*style="[^"]*border-radius/);
	});

	it('stretches the button when fullWidth is set', () => {
		const html = serialize(
			createBlock(metadata.name, { text: 'Go', fullWidth: true })
		);
		expect(html).toContain('dsgo-icon-button--full-width');
	});
});
