/**
 * Modal Trigger Block - save() and deprecation tests
 *
 * Covers the justification-wrapper refactor: the block root is now a plain
 * block-level `.dsgo-justify` wrapper (capped at the content column by core's
 * constrained layout), with the visible button shrink-wrapped inside it and
 * carrying all visual supports (border/color/typography).
 *
 * Also guards the same Critical bug class already fixed on Pill/Icon/Icon
 * Button: a deprecation's `supports` object MUST declare the block's FULL
 * historical support set (color, border, spacing, typography), not just
 * `align`. WP re-runs the `blocks.registerBlockType` filter chain
 * (color.js/border.js/spacing.js/typography.js/align.js) against EACH
 * deprecation entry at registration time, and those filters only add
 * `backgroundColor` / `textColor` / `borderColor` / `fontSize` / `style` to a
 * deprecation's attribute schema when the matching support group is present
 * THERE. A `supports` object that omits a group makes `getBlockAttributes()`
 * silently strip those attributes *before* `migrate()` ever runs.
 *
 * @package
 */

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks;
// useBlockProps.save() (used by save.js and the deprecated entries' save())
// resolves block supports against THAT copy's registry, so registration and
// parsing must go through the same instance or save() silently collapses to
// a self-closing comment instead of throwing (see icon-button's
// deprecation-data-loss.test.js for the same pattern).
const {
	registerBlockType,
	unregisterBlockType,
	setCategories,
	parse,
	createBlock,
	serialize,
	getSaveContent,
	// eslint-disable-next-line import/no-unresolved
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import metadata from '../../../../src/blocks/modal-trigger/block.json';
import save from '../../../../src/blocks/modal-trigger/save';
import deprecated from '../../../../src/blocks/modal-trigger/deprecated';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

// deprecated.js exports newest-first: [v4, v3, v2, v1]. v4 is the pre-wrapper
// deprecation added for the justification-wrapper refactor; v2/v1 are the
// pre-v3 nested `<div><button>` structure.
const [v4, , v2] = deprecated;

/**
 * getSaveContent() renders EXACTLY the attributes object it is given —
 * unlike createBlock()/parse(), it does not fill in a deprecation's own
 * schema defaults for keys the caller omits. A real stored block always has
 * those defaults already applied, so fixtures built directly from
 * getSaveContent() must fill them in too.
 *
 * @param {Object} blockType Block type (or deprecation entry) with an
 *                           `attributes` schema.
 * @param {Object} attrs     Explicit attribute overrides.
 * @return {Object} Attributes with defaults applied, then overridden.
 */
function withDefaults(blockType, attrs) {
	const defaults = Object.fromEntries(
		Object.entries(blockType.attributes)
			.filter(([, schema]) => 'default' in schema)
			.map(([key, schema]) => [key, schema.default])
	);
	return { ...defaults, ...attrs };
}

describe('modal-trigger save', () => {
	beforeAll(() => {
		registerBlockType(metadata.name, { ...metadata, save });
	});

	afterAll(() => {
		unregisterBlockType(metadata.name);
	});

	it('wraps the button in a block-level justification wrapper', () => {
		const html = serialize(
			createBlock(metadata.name, {
				text: 'Open',
				targetModalId: 'm1',
				justification: 'right',
			})
		);

		expect(html).toMatch(
			/<div class="[^"]*wp-block-designsetgo-modal-trigger[^"]*dsgo-justify dsgo-justify--right/
		);
		expect(html).toMatch(/<button class="dsgo-modal-trigger/);
		expect(html).toContain('data-dsgo-modal-trigger="m1"');
	});

	it('puts border radius on the button, not the wrapper', () => {
		const html = serialize(
			createBlock(metadata.name, {
				text: 'Open',
				style: { border: { radius: '4px' } },
			})
		);

		expect(html).toMatch(/<button[^>]*style="[^"]*border-radius:4px/);
		expect(html).not.toMatch(/<div[^>]*style="[^"]*border-radius/);
	});

	it('stretches the button when fullWidth is set', () => {
		const html = serialize(
			createBlock(metadata.name, { text: 'Open', fullWidth: true })
		);
		expect(html).toContain('dsgo-modal-trigger--full-width');
	});

	it('defaults justification to left with no explicit class suffix', () => {
		const html = serialize(createBlock(metadata.name, { text: 'Open' }));
		expect(html).toMatch(/class="[^"]*dsgo-justify(?!--)/);
	});
});

describe('modal-trigger deprecations - v4 styled trigger retains visual attributes through migration', () => {
	// A styled legacy trigger, exactly as it would have been stored before
	// this change: preset background + preset text colour + preset border
	// colour + preset font size + `align`. No `justification`/`fullWidth` yet
	// — those attributes did not exist in the pre-wrapper schema.
	const STYLED_LEGACY_COMMENT = {
		text: 'Open',
		targetModalId: 'm1',
		align: 'right',
		backgroundColor: 'contrast',
		textColor: 'base',
		borderColor: 'accent-1',
		fontSize: 'large',
		style: { border: { width: '2px', style: 'solid' } },
	};

	function styledLegacyMarkup(blockName = metadata.name) {
		// v4.save() with the same attributes reproduces exactly what the
		// pre-wrapper save() emitted for a styled, preset-colour trigger.
		const v4BlockType = { name: blockName, ...v4 };
		const attrs = withDefaults(v4BlockType, STYLED_LEGACY_COMMENT);
		const html = getSaveContent(v4BlockType, attrs);
		return `<!-- wp:${blockName} ${JSON.stringify(STYLED_LEGACY_COMMENT)} -->${html}<!-- /wp:${blockName} -->`;
	}

	beforeAll(() => {
		registerBlockType(metadata.name, { ...metadata, save, deprecated });
	});

	afterAll(() => {
		unregisterBlockType(metadata.name);
	});

	it('migrates a styled, align-positioned legacy trigger with every visual attribute intact', () => {
		const [block] = parse(styledLegacyMarkup());

		// Silent migration: invalid → valid via v4, so WP logs "Block
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

	it('v4.supports declares the full visual support set (color/border/typography), not just align', () => {
		expect(v4.supports.color).toBeTruthy();
		expect(v4.supports.color.background).toBe(true);
		expect(v4.supports.color.text).toBe(true);
		expect(v4.supports.__experimentalBorder).toBeTruthy();
		expect(v4.supports.typography).toBeTruthy();
		expect(v4.supports.typography.fontSize).toBe(true);
	});

	it('fails when v4.supports is stripped down to only `align` (proves the test above has teeth)', () => {
		// Reproduce the bug class directly: register a SEPARATE block name
		// whose PRIMARY (current) registration AND sole deprecation entry
		// both have supports stripped to `{ align: [...] }`. A styled legacy
		// trigger parsed against this fully-stripped registration must end up
		// with every visual attribute undefined, confirming the assertions
		// above would have caught the real bug had v4.supports been
		// incomplete.
		const strippedName = 'designsetgo/modal-trigger-test-stripped-supports';
		const strippedSupports = { align: ['left', 'center', 'right', 'full'] };
		const strippedV4 = { ...v4, supports: strippedSupports };

		registerBlockType(strippedName, {
			...metadata,
			name: strippedName,
			supports: strippedSupports,
			save,
			deprecated: [strippedV4],
		});

		try {
			const html = styledLegacyMarkup(strippedName);
			const [block] = parse(html);

			// The stripped registration cannot reproduce the stored
			// style/class output (missing color/border/typography support
			// entirely), so it stays invalid; WP logs the mismatch. That
			// noise is expected here.
			expect(console).toHaveWarned();
			expect(console).toHaveErrored();

			// Whatever the final validity, WP never registered
			// backgroundColor/textColor/borderColor/fontSize as attributes
			// for this block at all (primary or deprecation), so
			// getBlockAttributes() cannot source them from the comment JSON.
			expect(block.attributes.backgroundColor).toBeUndefined();
			expect(block.attributes.textColor).toBeUndefined();
			expect(block.attributes.borderColor).toBeUndefined();
			expect(block.attributes.fontSize).toBeUndefined();
		} finally {
			unregisterBlockType(strippedName);
		}
	});
});

describe('modal-trigger deprecations - legacy nested-div migration', () => {
	// v2's own save() (pre-v3, nested `<div class="…--width-full">` wrapping
	// a `<button class="…__button">`) is the byte-accurate source for this
	// fixture rather than hand-typed HTML, so the test cannot silently pass
	// against markup that never actually matches v2's real output.
	function legacyNestedDivMarkup() {
		const v2BlockType = { name: metadata.name, ...v2 };
		const comment = {
			text: 'Open',
			targetModalId: 'm1',
			width: 'full',
		};
		const attrs = withDefaults(v2BlockType, comment);
		const html = getSaveContent(v2BlockType, attrs);
		return `<!-- wp:designsetgo/modal-trigger ${JSON.stringify(comment)} -->${html}<!-- /wp:designsetgo/modal-trigger -->`;
	}

	beforeAll(() => {
		registerBlockType(metadata.name, { ...metadata, save, deprecated });
	});

	afterAll(() => {
		unregisterBlockType(metadata.name);
	});

	it('legacy markup fixture is genuinely the pre-v3 nested div/button shape', () => {
		const html = legacyNestedDivMarkup();
		expect(html).toMatch(/<div class="[^"]*dsgo-modal-trigger--width-full/);
		expect(html).toMatch(/<button class="dsgo-modal-trigger__button"/);
		expect(html).not.toMatch(/wp-block-button/);
	});

	it('migrates a legacy nested-div trigger (width:full) to fullWidth without recovery', () => {
		const [block] = parse(legacyNestedDivMarkup());

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.fullWidth).toBe(true);
		expect(block.attributes.justification).toBe('left');
		expect(block.attributes.align).toBeUndefined();
	});

	it('v4 does not intercept legacy nested-div markup', () => {
		// Prove the "Watch out" caveat empirically rather than assuming it:
		// v1/v2's legacy markup IS a `<div><button>` nest too, so a naive
		// isEligible of "starts with <div>" would match it. v4.save()
		// (the single-<button>-root shape) must NOT validate against this
		// nested markup, so WP falls through to v2/v1 regardless of what
		// isEligible reports.
		const html = legacyNestedDivMarkup();
		const innerHTML = html
			.replace(/^<!--[\s\S]*?-->/, '')
			.replace(/<!--[\s\S]*?-->$/, '');

		// isEligible itself opts out for this shape…
		expect(v4.isEligible({}, [], { innerHTML })).toBe(false);

		// …and even if it hadn't, the actual migrated block above did not
		// route through v4: v4's schema has no `width` attribute, so a
		// v4-migrated block could never produce `fullWidth: true` from a
		// `width:"full"` comment attribute the way v2's migrate() does.
		const [block] = parse(html);
		expect(console).toHaveInformed();
		expect(block.attributes.buttonStyle).toBe('fill');
	});
});
