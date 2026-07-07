/**
 * Row Block - save.js Tests
 *
 * Verifies overlay and hover style-kit variations (`is-style-overlay-*`,
 * `is-style-hover-{text,icon,button}-*`) emit the matching activation class,
 * mirroring Section's behavior.
 */

// See section/test/save.test.js for why these must come from the NESTED
// `@wordpress/blocks` copy bundled by `@wordpress/block-editor`.
import {
	createBlock,
	serialize,
	registerBlockType,
	setCategories,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save });

describe('row save - overlay class', () => {
	test('no overlay by default', () => {
		const html = serialize(createBlock(metadata.name));
		expect(html).not.toContain('dsgo-flex--has-overlay');
	});

	test('overlayColor emits overlay class + inline color var', () => {
		const html = serialize(
			createBlock(metadata.name, { overlayColor: '#000000' })
		);
		expect(html).toContain('dsgo-flex--has-overlay');
		expect(html).toContain('--dsgo-overlay-color');
	});

	test('is-style-overlay-dark className emits overlay class without inline color var', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-overlay-dark' })
		);
		expect(html).toContain('dsgo-flex--has-overlay');
		expect(html).not.toContain('--dsgo-overlay-color');
	});

	test('unrelated is-style-* variation does not enable the overlay', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-rounded' })
		);
		expect(html).not.toContain('dsgo-flex--has-overlay');
	});
});

describe('row save - hover variation activation classes', () => {
	test('no hover activation classes by default', () => {
		const html = serialize(createBlock(metadata.name));
		expect(html).not.toContain('dsgo-flex--has-hover-text');
		expect(html).not.toContain('dsgo-flex--has-hover-icon');
		expect(html).not.toContain('dsgo-flex--has-hover-button');
	});

	test('is-style-hover-text-* emits only the hover-text activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-text-light',
			})
		);
		expect(html).toContain('dsgo-flex--has-hover-text');
		expect(html).not.toContain('dsgo-flex--has-hover-icon');
		expect(html).not.toContain('dsgo-flex--has-hover-button');
	});

	test('is-style-hover-icon-* emits only the hover-icon activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-icon-blue',
			})
		);
		expect(html).toContain('dsgo-flex--has-hover-icon');
		expect(html).not.toContain('dsgo-flex--has-hover-text');
	});

	test('is-style-hover-button-* emits only the hover-button activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-button-accent',
			})
		);
		expect(html).toContain('dsgo-flex--has-hover-button');
		expect(html).not.toContain('dsgo-flex--has-hover-text');
	});

	test('setting a hover attribute alone does NOT add an activation class (inline gate handles it)', () => {
		const html = serialize(
			createBlock(metadata.name, { hoverTextColor: '#ffffff' })
		);
		expect(html).not.toContain('dsgo-flex--has-hover-text');
		expect(html).toContain('--dsgo-hover-text-color');
	});
});
