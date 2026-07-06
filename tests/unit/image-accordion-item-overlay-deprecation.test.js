/**
 * Image Accordion Item - overlay-var deprecation
 *
 * Regression test proving that items saved before the overlay color/opacity
 * stopped being baked into the item markup (the `--dsgo-overlay-color` /
 * `--dsgo-overlay-opacity` custom properties) still parse as valid — silent
 * migration, no "Attempt Recovery".
 *
 * Also asserts the current save() no longer serializes `--dsgo-overlay-color`;
 * the overlay now cascades from the parent accordion's
 * `--dsgo-image-accordion-overlay-*` properties.
 *
 * Block context is not available during save serialization, so both the legacy
 * and current save resolve overlay context to its defaults — matching real
 * stored markup.
 *
 * @package
 */

const {
	registerBlockType,
	unregisterBlockType,
	getBlockType,
	createBlock,
	serialize,
	parse,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import metadata from '../../src/blocks/image-accordion-item/block.json';
import save from '../../src/blocks/image-accordion-item/save';
import deprecated from '../../src/blocks/image-accordion-item/deprecated';

const register = (saveFn, deprecations) =>
	registerBlockType(metadata.name, {
		...metadata,
		category: 'media',
		save: saveFn,
		...(deprecations ? { deprecated: deprecations } : {}),
	});

/**
 * Serialize an item the way the OLD save did (with the baked overlay vars).
 *
 * @return {string} Legacy block markup.
 */
function legacyMarkup() {
	register(deprecated[0].save);
	const block = createBlock(metadata.name);
	const markup = serialize(block);
	unregisterBlockType(metadata.name);
	return markup;
}

describe('Image Accordion Item - overlay-var deprecation', () => {
	afterEach(() => {
		// Only unregister when still registered — unregistering an absent
		// block warns, which trips the console matcher.
		if (getBlockType(metadata.name)) {
			unregisterBlockType(metadata.name);
		}
	});

	it('reproduces the old baked overlay custom property in legacy markup', () => {
		const markup = legacyMarkup();
		expect(markup).toContain('--dsgo-overlay-color');
	});

	it('parses legacy overlay-var content as valid (silent migration)', () => {
		const markup = legacyMarkup();
		register(save, deprecated);

		const [block] = parse(markup);

		expect(console).toHaveInformed();
		expect(block).toBeTruthy();
		expect(block.name).toBe(metadata.name);
		expect(block.isValid).toBe(true);
	});

	it('no longer serializes the baked overlay color var', () => {
		register(save, deprecated);

		const markup = serialize(createBlock(metadata.name));

		expect(markup).not.toContain('--dsgo-overlay-color');
	});
});
