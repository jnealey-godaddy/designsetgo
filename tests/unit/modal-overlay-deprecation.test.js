/**
 * Modal - baked backdrop color deprecation
 *
 * Regression test proving that modals saved before the backdrop color stopped
 * being baked into save() output (`background-color:#000000` inline on
 * `.dsgo-modal__backdrop`, from the old `overlayColor` attribute default)
 * still parse as valid — silent migration, no "Attempt Recovery".
 *
 * Also asserts the current save() no longer serializes the backdrop color when
 * the author never set one; the scrim now cascades from the stylesheet default
 * (--wp--custom--designsetgo--modal--overlay-color → #000), and an explicit
 * author color is preserved through migration untouched.
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

import metadata from '../../src/blocks/modal/block.json';
import save from '../../src/blocks/modal/save';
import deprecated from '../../src/blocks/modal/deprecated';

const register = (saveFn, options = {}) =>
	registerBlockType(metadata.name, {
		...metadata,
		// The custom "designsetgo" category is not registered in the test
		// environment; registering with it warns and trips the console matcher.
		category: 'design',
		save: saveFn,
		...options,
	});

/**
 * Serialize a modal the way the OLD save did (baked backdrop color).
 *
 * @param {Object} attributes Attributes to serialize with.
 * @return {string} Legacy block markup.
 */
function legacyMarkup(attributes = {}) {
	register(deprecated[0].save, { attributes: deprecated[0].attributes });
	const markup = serialize(createBlock(metadata.name, attributes));
	unregisterBlockType(metadata.name);
	return markup;
}

describe('Modal - baked backdrop color deprecation', () => {
	afterEach(() => {
		// Only unregister when still registered — unregistering an absent
		// block warns, which trips the console matcher.
		if (getBlockType(metadata.name)) {
			unregisterBlockType(metadata.name);
		}
	});

	it('reproduces the old baked backdrop color in legacy markup', () => {
		const markup = legacyMarkup({ overlayOpacity: 90 });
		expect(markup).toContain('background-color:#000000;opacity:0.9');
	});

	it('parses legacy default-color content as valid and drops the color (silent migration)', () => {
		const markup = legacyMarkup({ overlayOpacity: 90 });
		register(save, { deprecated });

		const [block] = parse(markup);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.name).toBe(metadata.name);
		expect(block.attributes.overlayColor).toBeUndefined();
		// The v1 schema's anchor support sources `anchor` from the wrapper id;
		// migrate() must strip it since current block.json dropped anchor.
		expect(block.attributes.anchor).toBeUndefined();
	});

	it('preserves an explicitly customised backdrop color through migration', () => {
		const markup = legacyMarkup({ overlayColor: '#ff0000' });
		register(save, { deprecated });

		const [block] = parse(markup);

		expect(block.isValid).toBe(true);
		expect(block.attributes.overlayColor).toBe('#ff0000');
	});

	it('no longer serializes the backdrop color when the author never set one', () => {
		register(save, { deprecated });

		const markup = serialize(createBlock(metadata.name));

		expect(markup).not.toContain('background-color');
		expect(markup).toContain('opacity:0.8');
	});
});
