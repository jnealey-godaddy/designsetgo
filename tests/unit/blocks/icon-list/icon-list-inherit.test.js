/**
 * Icon List - Inheritable icon-style attribute
 *
 * The Icon List "Fill / Outline" toggle and stroke width are shared settings
 * that must reach the published frontend. Because WordPress does not pass block
 * context to a static save(), the child icon-list-item cannot read the parent's
 * iconStyle at serialization time. Instead the parent stamps the resolved values
 * onto its own wrapper as data-dsgo-icon-style / data-dsgo-icon-stroke-width, and
 * the lazy-icon injector inherits them for descendant placeholders.
 *
 * These assertions lock the wrapper output: emitted only when iconStyle is
 * explicitly set (so existing content with no style is byte-identical), and
 * absent otherwise.
 *
 * @package
 */

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks;
// useBlockProps.save() resolves supports against THAT registry, so register and
// render through the same instance (mirrors the svg-patterns test).
const {
	registerBlockType,
	unregisterBlockType,
	getSaveContent,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import metadata from '../../../../src/blocks/icon-list/block.json';
import save from '../../../../src/blocks/icon-list/save';

const BLOCK_NAME = metadata.name;

describe('Icon List inheritable icon-style attribute', () => {
	beforeAll(() => {
		registerBlockType(BLOCK_NAME, {
			...metadata,
			// The custom "designsetgo" category isn't registered in the
			// unit-test env; use a core category to avoid a warning.
			category: 'design',
			save,
		});
	});

	afterAll(() => {
		unregisterBlockType(BLOCK_NAME);
	});

	it('stamps data-dsgo-icon-style + stroke width on the wrapper when outlined', () => {
		const html = getSaveContent(BLOCK_NAME, {
			layout: 'vertical',
			iconStyle: 'outlined',
			strokeWidth: 2,
		});
		expect(html).toMatch(/data-dsgo-icon-style="outlined"/);
		expect(html).toMatch(/data-dsgo-icon-stroke-width="2"/);
	});

	it('stamps an explicit filled override so it beats the theme default', () => {
		const html = getSaveContent(BLOCK_NAME, {
			layout: 'vertical',
			iconStyle: 'filled',
		});
		expect(html).toMatch(/data-dsgo-icon-style="filled"/);
		// Stroke width is only meaningful for outlined icons.
		expect(html).not.toMatch(/data-dsgo-icon-stroke-width/);
	});

	it('omits the inherited attributes when iconStyle is unset (backward-compatible)', () => {
		const html = getSaveContent(BLOCK_NAME, { layout: 'vertical' });
		expect(html).not.toMatch(/data-dsgo-icon-style/);
		expect(html).not.toMatch(/data-dsgo-icon-stroke-width/);
	});
});
