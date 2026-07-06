/**
 * Progress Bar Block - color-default deprecation
 *
 * Regression test proving that progress bars saved before the bar/track colors
 * stopped baking hex defaults into the markup (`#2563eb` fill / `#e5e7eb`
 * track) still parse as valid — silent migration, no "Attempt Recovery".
 *
 * Also asserts the current save() no longer serializes those hex literals when
 * the author has not chosen a color, so the bar inherits the FSE-overridable
 * CSS default instead.
 *
 * Deliberately uses the real @wordpress/blocks parser/validator (not mocked)
 * since the thing under test IS the parser's deprecation-matching behavior.
 *
 * @package
 */

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks.
// useBlockProps.save() resolves block supports against THAT copy's registry,
// so registration/parsing here must go through the same instance.
const {
	registerBlockType,
	unregisterBlockType,
	getBlockType,
	createBlock,
	serialize,
	parse,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import metadata from '../../src/blocks/progress-bar/block.json';
import save from '../../src/blocks/progress-bar/save';
import deprecated from '../../src/blocks/progress-bar/deprecated';

// The custom 'designsetgo' category isn't registered in jest; category is
// irrelevant to parse/validation, so use a built-in one to avoid an unrelated
// invalid-category warning that would trip the console matcher.
const register = (saveFn, deprecations) =>
	registerBlockType(metadata.name, {
		...metadata,
		category: 'media',
		save: saveFn,
		...(deprecations ? { deprecated: deprecations } : {}),
	});

/**
 * Serialize a default-color progress bar the way the OLD save did (with the
 * baked hex fallbacks), to reproduce real stored markup.
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

describe('Progress Bar - color-default deprecation', () => {
	afterEach(() => {
		// Only unregister when still registered — unregistering an absent
		// block warns, which trips the console matcher.
		if (getBlockType(metadata.name)) {
			unregisterBlockType(metadata.name);
		}
	});

	it('reproduces the old baked hex defaults in legacy markup', () => {
		const markup = legacyMarkup();
		expect(markup).toContain('#2563eb');
		expect(markup).toContain('#e5e7eb');
	});

	it('parses legacy hex-default content as valid (silent migration)', () => {
		const markup = legacyMarkup();
		register(save, deprecated);

		const [block] = parse(markup);

		// The parser logs an info message when a deprecation's save matches and
		// the block is silently migrated — proving no "Attempt Recovery".
		expect(console).toHaveInformed();
		expect(block).toBeTruthy();
		expect(block.name).toBe(metadata.name);
		expect(block.isValid).toBe(true);
		// Attributes are untouched by the passthrough migrate.
		expect(block.attributes.barColor).toBe('');
		expect(block.attributes.barBackgroundColor).toBe('');
	});

	it('no longer serializes hex color defaults for a default block', () => {
		register(save, deprecated);

		const markup = serialize(createBlock(metadata.name));

		expect(markup).not.toContain('#2563eb');
		expect(markup).not.toContain('#e5e7eb');
	});
});
