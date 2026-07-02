/**
 * Icon Block - Theme Token Deprecation
 *
 * Integration test proving that icons saved before the theme-configurable
 * size/style tokens (when iconSize/iconStyle had concrete defaults 48/"filled"
 * baked into the saved HTML) still parse as valid — no "Attempt Recovery"
 * notice — via the v2 deprecated entry, and that an implicit-default icon
 * migrates to `undefined` so it inherits the theme token.
 *
 * Uses the real @wordpress/blocks parser/validator (not mocked) since the
 * behavior under test IS the parser's deprecation-matching.
 *
 * @package
 */

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks;
// useBlockProps.save() resolves supports against THAT registry, so we must
// register/parse through the same instance (mirrors svg-patterns test).
const {
	registerBlockType,
	unregisterBlockType,
	parse,
	getSaveContent,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import metadata from '../../../../src/blocks/icon/block.json';
import save from '../../../../src/blocks/icon/save';
import deprecated from '../../../../src/blocks/icon/deprecated';

const BLOCK_NAME = metadata.name;

describe('Icon block theme-token deprecation', () => {
	beforeAll(() => {
		registerBlockType(BLOCK_NAME, {
			...metadata,
			// Use a core category — the custom "designsetgo" category isn't
			// registered in the unit-test env and would emit a warning.
			category: 'design',
			save,
			deprecated,
		});
	});

	afterAll(() => {
		unregisterBlockType(BLOCK_NAME);
	});

	// getSaveContent() renders with the exact attributes passed — it does NOT
	// fill schema defaults the way parse() does. So every call must supply the
	// output-affecting defaults (rotation, strokeWidth) the parser would add.
	const RESOLVED_DEFAULTS = {
		icon: 'star',
		rotation: 0,
		strokeWidth: 1.5,
		linkUrl: '',
		linkTarget: '_self',
		linkRel: '',
		ariaLabel: '',
		isDecorative: false,
	};

	/**
	 * Reproduce the pre-token save output (unconditional width/height +
	 * data-icon-style) by rendering the v2 deprecation's own save().
	 *
	 * @param {Object} attrs Attributes to render with.
	 * @return {string} Legacy inner HTML.
	 */
	function legacySaveContent(attrs) {
		const v2 = deprecated[0];
		const TEMP = 'designsetgo/icon-legacy-temp';
		registerBlockType(TEMP, {
			...metadata,
			name: TEMP,
			category: 'design',
			attributes: v2.attributes,
			save: v2.save,
		});
		const html = getSaveContent(TEMP, { ...RESOLVED_DEFAULTS, ...attrs });
		unregisterBlockType(TEMP);
		// useBlockProps.save() baked the temp block's derived wrapper class
		// (wp-block-designsetgo-icon-legacy-temp) into the markup. Normalize it
		// back to the real block's class so the string is byte-accurate legacy
		// content for designsetgo/icon.
		return html.replace(
			/designsetgo-icon-legacy-temp/g,
			'designsetgo-icon'
		);
	}

	it('parses an implicit-default (48px/filled) icon as valid and migrates it to inherit', () => {
		// Pre-token content: iconSize/iconStyle equalled the old defaults so
		// they were NOT serialized into the comment, but the saved HTML baked
		// width:48px;height:48px and data-icon-style="filled". rotation:0 is
		// passed explicitly because getSaveContent does not fill schema
		// defaults the way the parser does.
		const legacyHtml = legacySaveContent({ icon: 'star', rotation: 0 });
		expect(legacyHtml).toMatch(/width:\s*48px/);

		const raw = `<!-- wp:designsetgo/icon {"icon":"star"} -->\n${legacyHtml}\n<!-- /wp:designsetgo/icon -->`;
		const [block] = parse(raw);

		// isEligible() short-circuits save validation, so migration is silent
		// (no "Attempt Recovery" warning) -- only a success info message.
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		// Migrated to inherit: no explicit size/style baked as an override.
		expect(block.attributes.iconSize).toBeUndefined();
		expect(block.attributes.iconStyle).toBeUndefined();
	});

	it('parses an explicit-size (64px) icon as valid and preserves it as an override', () => {
		const legacyHtml = legacySaveContent({
			icon: 'star',
			iconSize: 64,
			rotation: 0,
		});
		expect(legacyHtml).toMatch(/width:\s*64px/);

		const raw = `<!-- wp:designsetgo/icon {"icon":"star","iconSize":64} -->\n${legacyHtml}\n<!-- /wp:designsetgo/icon -->`;
		const [block] = parse(raw);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.iconSize).toBe(64);
	});

	it('parses new-format inherit content (no inline size) as valid without migration', () => {
		// Current save() with unset size/style omits width/height and
		// data-icon-style entirely. rotation:0 passed explicitly (getSaveContent
		// does not fill schema defaults).
		const currentHtml = getSaveContent(BLOCK_NAME, {
			...RESOLVED_DEFAULTS,
		});
		expect(currentHtml).not.toMatch(/width:\s*\d+px;\s*height:\s*\d+px/);

		const raw = `<!-- wp:designsetgo/icon {"icon":"star"} -->\n${currentHtml}\n<!-- /wp:designsetgo/icon -->`;
		const [block] = parse(raw);

		expect(block.isValid).toBe(true);
		expect(block.attributes.iconSize).toBeUndefined();
	});
});
