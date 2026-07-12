/**
 * Icon Button Block - v9 deprecation data-loss regression test
 *
 * Regression coverage for the same Critical bug class already fixed on Pill
 * (see `reference_wp_deprecation_iseligible_mechanics` / `feedback_wp_native_first`
 * memory) and Icon (`icon-align-deprecation.test.js`): a deprecation's
 * `supports` object MUST declare the block's FULL historical support set
 * (color, border, spacing, typography), not just `align`. WP re-runs the
 * `blocks.registerBlockType` filter chain (color.js/border.js/spacing.js/
 * typography.js/align.js) against EACH deprecation entry at registration
 * time, and those filters only add `backgroundColor` / `textColor` /
 * `borderColor` / `fontSize` / `style` to a deprecation's attribute schema
 * when the matching support group is present THERE. A `supports` object
 * that omits a group makes `getBlockAttributes()` silently strip those
 * attributes *before* `migrate()` ever runs — every styled, `align`-authored
 * icon button would lose its background/text/border colour and font size
 * the next time the page was opened in the editor and saved.
 *
 * This test goes through the real `parse()` pipeline (not a direct
 * `migrate()` call) because that is the only place this class of bug is
 * observable — a direct `migrate()` call is handed already-correct
 * attributes and cannot detect that the parser silently discarded them
 * first.
 *
 * @package
 */

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks;
// useBlockProps.save() (used by the deprecated entries' save()) resolves
// block supports against THAT copy's registry, so registration/parsing must
// go through the same instance (mirrors icon-align-deprecation.test.js).
const {
	registerBlockType,
	unregisterBlockType,
	setCategories,
	parse,
	getSaveContent,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import metadata from '../../../../src/blocks/icon-button/block.json';
import save from '../../../../src/blocks/icon-button/save';
import deprecated from '../../../../src/blocks/icon-button/deprecated';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

// deprecated.js exports newest-first: [v9, v8, ...]. v9 is the pre-wrapper
// deprecation added for the justification-wrapper refactor.
const [v9] = deprecated;

// A styled legacy icon button, exactly as it would have been stored before
// this change: preset background + preset text colour + preset border
// colour + preset font size + `align`. No `justification`/`fullWidth` yet —
// those attributes did not exist in the pre-wrapper schema.
const STYLED_LEGACY_COMMENT =
	'{"text":"Go","url":"#","align":"right","backgroundColor":"contrast","textColor":"base","borderColor":"accent-1","fontSize":"large","style":{"border":{"width":"2px","style":"solid"}}}';

function styledLegacyMarkup() {
	// v9.save() with the same attributes reproduces exactly what the
	// pre-wrapper save() emitted for a styled, preset-colour button (byte
	// parity confirmed by tests/unit/blocks/icon-button and
	// src/blocks/icon-button/test/deprecated.test.js).
	const v9BlockType = { name: metadata.name, ...v9 };
	const attrs = {
		...Object.fromEntries(
			Object.entries(v9.attributes)
				.filter(([, schema]) => 'default' in schema)
				.map(([key, schema]) => [key, schema.default])
		),
		text: 'Go',
		url: '#',
		align: 'right',
		backgroundColor: 'contrast',
		textColor: 'base',
		borderColor: 'accent-1',
		fontSize: 'large',
		style: { border: { width: '2px', style: 'solid' } },
	};
	const html = getSaveContent(v9BlockType, attrs);
	return `<!-- wp:designsetgo/icon-button ${STYLED_LEGACY_COMMENT} -->${html}<!-- /wp:designsetgo/icon-button -->`;
}

describe('icon-button v9 deprecation - styled button retains visual attributes through migration', () => {
	beforeAll(() => {
		registerBlockType(metadata.name, { ...metadata, save, deprecated });
	});

	afterAll(() => {
		unregisterBlockType(metadata.name);
	});

	it('migrates a styled, align-positioned legacy button with every visual attribute intact', () => {
		const [block] = parse(styledLegacyMarkup());

		// Silent migration: invalid → valid via v9, so WP logs "Block
		// successfully updated" once.
		expect(console).toHaveInformed();

		expect(block.isValid).toBe(true);

		// The bug this guards: these must NOT be stripped/undefined.
		expect(block.attributes.backgroundColor).toBe('contrast');
		expect(block.attributes.textColor).toBe('base');
		expect(block.attributes.borderColor).toBe('accent-1');
		expect(block.attributes.fontSize).toBe('large');
		expect(block.attributes.style.border.width).toBe('2px');
		expect(block.attributes.style.border.style).toBe('solid');

		// And the actual point of THIS task: align → justification/fullWidth.
		expect(block.attributes.align).toBeUndefined();
		expect(block.attributes.justification).toBe('right');
		expect(block.attributes.fullWidth).toBe(false);
	});
});

describe('icon-button v9 deprecation - supports regression guard', () => {
	it('v9.supports declares the full visual support set (color/border/typography), not just align', () => {
		// A `supports` object that only lists `align` is exactly the bug this
		// guards against: WP would never register backgroundColor/textColor/
		// borderColor/fontSize as attributes for this deprecation at all, so
		// getBlockAttributes() would silently drop them before migrate() runs.
		expect(v9.supports.color).toBeTruthy();
		expect(v9.supports.color.background).toBe(true);
		expect(v9.supports.color.text).toBe(true);
		expect(v9.supports.__experimentalBorder).toBeTruthy();
		expect(v9.supports.typography).toBeTruthy();
		expect(v9.supports.typography.fontSize).toBe(true);
	});

	it('fails when v9.supports is stripped down to only `align` (proves the test above has teeth)', () => {
		// Reproduce the bug class directly: register a SEPARATE block name
		// whose PRIMARY (current) registration AND sole deprecation entry both
		// have supports stripped to `{ align: [...] }`. Stripping the primary
		// registration too is required for the test to observe the effect
		// cleanly — otherwise WP's "best effort" invalid-block fallback would
		// still source backgroundColor/etc. straight from the comment JSON
		// against the (unstripped) primary schema, masking whether the
		// deprecation itself lost them. A styled legacy button parsed against
		// this fully-stripped registration must end up with every visual
		// attribute undefined, confirming the assertions in the test above
		// would have caught the real bug had v9.supports been incomplete.
		const strippedName = 'designsetgo/icon-button-test-stripped-supports';
		const strippedSupports = { align: ['left', 'center', 'right', 'full'] };
		const strippedV9 = { ...v9, supports: strippedSupports };

		registerBlockType(strippedName, {
			...metadata,
			name: strippedName,
			supports: strippedSupports,
			save,
			deprecated: [strippedV9],
		});

		try {
			const html = styledLegacyMarkup().replace(
				/designsetgo\/icon-button/g,
				strippedName
			);
			const [block] = parse(html);

			// The stripped registration cannot reproduce the stored style/class
			// output (missing color/border/typography support entirely), so it
			// stays invalid; WP logs the mismatch. That noise is expected here.
			expect(console).toHaveWarned();
			expect(console).toHaveErrored();

			// Whatever the final validity, WP never registered
			// backgroundColor/textColor/borderColor/fontSize as attributes for
			// this block at all (primary or deprecation), so
			// getBlockAttributes() cannot source them from the comment JSON —
			// the opposite of what the real (unstripped) v9 does above.
			expect(block.attributes.backgroundColor).toBeUndefined();
			expect(block.attributes.textColor).toBeUndefined();
			expect(block.attributes.borderColor).toBeUndefined();
			expect(block.attributes.fontSize).toBeUndefined();
		} finally {
			unregisterBlockType(strippedName);
		}
	});
});
