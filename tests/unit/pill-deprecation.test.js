/**
 * Pill Block - static → dynamic deprecation
 *
 * Guards the `vStatic.migrate()` branch that PRESERVES a non-default alignment /
 * font size while dropping the old baked `center` / `small` defaults. That branch
 * (`if (align && 'center' !== align)` / `if (fontSize && 'small' !== fontSize)`)
 * is the one most likely to regress silently — a fresh insert and a
 * migrate-to-default case (covered by the e2e spec) would both still pass even if
 * explicit choices were accidentally discarded.
 *
 * Two layers:
 *  - migrate() called directly, asserting the attribute-level contract for each
 *    branch (default dropped, explicit kept);
 *  - the real @wordpress/blocks parser end-to-end, asserting an explicit
 *    `alignright` / `has-large-font-size` legacy pill migrates silently (no
 *    "Attempt Recovery") and re-serializes clean — keeping both attributes, with
 *    no baked classes in the dynamic output.
 *
 * @package
 */

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks.
// useBlockProps.save() (used by the deprecation's save()) resolves block supports
// against THAT copy's registry, so registration/parsing must go through the same
// instance.
const {
	registerBlockType,
	unregisterBlockType,
	parse,
	serialize,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import metadata from '../../src/blocks/pill/block.json';
import save from '../../src/blocks/pill/save';
import deprecated from '../../src/blocks/pill/deprecated';

// deprecated is [vAlign, vStatic, v1] — vAlign (newest) is the last DYNAMIC
// version positioned with `align`; vStatic is the last STATIC version.
const [, vStatic] = deprecated;

describe('Pill deprecation - vStatic.migrate()', () => {
	it('drops the old baked center/small defaults and lands on justification', () => {
		const migrated = vStatic.migrate({
			content: 'Test',
			align: 'center',
			fontSize: 'small',
		});

		expect(migrated).not.toHaveProperty('align');
		expect(migrated).not.toHaveProperty('fontSize');
		expect(migrated.justification).toBe('center');
		expect(migrated.content).toBe('Test');
	});

	it('preserves an explicit non-default font size and converts alignment to justification', () => {
		const migrated = vStatic.migrate({
			content: 'Test',
			align: 'right',
			fontSize: 'large',
		});

		expect(migrated).not.toHaveProperty('align');
		expect(migrated.justification).toBe('right');
		expect(migrated.fontSize).toBe('large');
		expect(migrated.content).toBe('Test');
	});

	it('converts an explicit alignment even when no font size is set', () => {
		const migrated = vStatic.migrate({
			content: 'Test',
			align: 'left',
		});

		expect(migrated).not.toHaveProperty('align');
		expect(migrated.justification).toBe('left');
		expect(migrated).not.toHaveProperty('fontSize');
	});
});

describe('Pill deprecation - explicit legacy markup migrates silently', () => {
	beforeAll(() => {
		registerBlockType(metadata.name, {
			...metadata,
			// The custom 'designsetgo' category isn't registered in the jest
			// environment; category is irrelevant to parse/validation, so use a
			// built-in one to avoid an unrelated invalid-category warning.
			category: 'design',
			save,
			deprecated,
		});
	});

	afterAll(() => {
		unregisterBlockType(metadata.name);
	});

	// Pre-conversion STATIC markup with EXPLICIT, non-default choices: the comment
	// carries align:right + fontSize:large (both non-default, so they were
	// serialized), and the wrapper baked `alignright` + `has-large-font-size`.
	const LEGACY_PILL_EXPLICIT = `<!-- wp:designsetgo/pill {"content":"Test","align":"right","fontSize":"large"} -->
<div class="wp-block-designsetgo-pill alignright dsgo-pill has-large-font-size"><span class="dsgo-pill__content">Test</span></div>
<!-- /wp:designsetgo/pill -->`;

	it('migrates without an "Attempt Recovery" warning and keeps both attributes', () => {
		const [block] = parse(LEGACY_PILL_EXPLICIT);

		// The parser logs an info message when a deprecated version's save matches
		// and the block is silently migrated — the behavior under test.
		expect(console).toHaveInformed();

		expect(block).toBeTruthy();
		expect(block.name).toBe(metadata.name);
		expect(block.isValid).toBe(true);
		expect(block.attributes.justification).toBe('right');
		expect(block.attributes.fontSize).toBe('large');
		expect(block.attributes.align).toBeUndefined();
	});

	it('re-serializes clean: attributes kept, no baked classes in dynamic output', () => {
		const [block] = parse(LEGACY_PILL_EXPLICIT);

		expect(console).toHaveInformed();

		const serialized = serialize(block);
		expect(serialized).toContain('"justification":"right"');
		expect(serialized).toContain('"fontSize":"large"');
		expect(serialized).not.toContain('"align"');
		// Dynamic block → self-closing comment, no stored HTML / baked classes.
		expect(serialized).not.toContain('has-large-font-size');
		expect(serialized).not.toContain('<div');
	});

	it('migrates a dynamic pill authored with align into justification', () => {
		const [block] = parse(
			'<!-- wp:designsetgo/pill {"content":"Hi","align":"left"} /-->'
		);

		expect(block.isValid).toBe(true);
		expect(block.attributes.justification).toBe('left');
		expect(block.attributes.align).toBeUndefined();
	});
});

/**
 * Regression coverage for a Critical data-loss bug: `vAlign.supports` was
 * previously written as `{ html: false, align: [...], alignWide: false }` —
 * declaring ONLY alignment support. WP re-runs the `blocks.registerBlockType`
 * filters (color.js, border.js, spacing.js, typography.js) against EACH
 * deprecation entry at registration time, and those filters only add
 * `backgroundColor` / `textColor` / `gradient` / `borderColor` / `fontSize` /
 * `style` to the deprecation's `attributes` schema when the matching support
 * is declared. With only `align` declared, none of those attributes were ever
 * registered for `vAlign`, so `getBlockAttributes()` silently stripped them
 * from `parsedAttributes` before `migrate()` ever ran — every styled,
 * align-positioned dynamic pill lost its background/text color, gradient,
 * border and font size the next time the page was opened and saved.
 *
 * These tests go through the real `parse()` pipeline (not a direct
 * `migrate()` call) because that is the only place this class of bug is
 * observable — a direct `migrate()` call is handed already-correct
 * attributes and cannot detect that the parser silently discarded them
 * first.
 */
describe('Pill deprecation - styled pill retains visual attributes through migration', () => {
	beforeAll(() => {
		registerBlockType(metadata.name, {
			...metadata,
			// See the beforeAll() in the describe block above for why 'design'
			// replaces the custom 'designsetgo' category here.
			category: 'design',
			save,
			deprecated,
		});
	});

	afterAll(() => {
		unregisterBlockType(metadata.name);
	});

	it('vAlign: a styled, align-positioned dynamic pill keeps color/border/fontSize', () => {
		const [block] = parse(
			'<!-- wp:designsetgo/pill {"content":"Hi","align":"right","backgroundColor":"accent-3","textColor":"base","fontSize":"large","style":{"border":{"radius":"4px"}}} /-->'
		);

		expect(block.isValid).toBe(true);
		expect(block.attributes.justification).toBe('right');
		expect(block.attributes.align).toBeUndefined();
		expect(block.attributes.backgroundColor).toBe('accent-3');
		expect(block.attributes.textColor).toBe('base');
		expect(block.attributes.fontSize).toBe('large');
		expect(block.attributes.style.border.radius).toBe('4px');
	});

	it('vStatic: a styled, aligned legacy static pill keeps custom color/border', () => {
		// Adapted from the real, previously-migrated fixture
		// tests/unit/__fixtures__/patterns/pill-old.html (exercised end-to-end in
		// tests/e2e/affected-blocks.spec.js) with an explicit `alignright` added,
		// so this matches vStatic's isEligible (dsgo-pill + an align class) rather
		// than v1's (dsgo-pill, no align class).
		const LEGACY_STATIC_ALIGNED = `<!-- wp:designsetgo/pill {"content":"Most Popular","align":"right","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|15"}},"border":{"radius":"50px"},"color":{"background":"#6366f1","text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-pill alignright dsgo-pill has-text-color has-background has-small-font-size" style="margin-bottom:var(--wp--preset--spacing--15)"><span class="dsgo-pill__content" style="background-color:#6366f1;color:#ffffff;border-radius:50px">Most Popular</span></div>
<!-- /wp:designsetgo/pill -->`;

		const [block] = parse(LEGACY_STATIC_ALIGNED);

		// The parser logs an info message when a deprecated version's save
		// matches and the block is silently migrated (see the identical pattern
		// in the describe block above).
		expect(console).toHaveInformed();

		expect(block.isValid).toBe(true);
		expect(block.attributes.justification).toBe('right');
		expect(block.attributes.align).toBeUndefined();
		expect(block.attributes.style.color.background).toBe('#6366f1');
		expect(block.attributes.style.color.text).toBe('#ffffff');
		expect(block.attributes.style.border.radius).toBe('50px');
	});

	it('v1: a styled legacy static pill (pre-align) keeps custom color/border', () => {
		// The real fixture: tests/unit/__fixtures__/patterns/pill-old.html.
		const LEGACY_STATIC_NO_ALIGN = `<!-- wp:designsetgo/pill {"content":"Most Popular","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|15"}},"border":{"radius":"50px"},"color":{"background":"#6366f1","text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-pill dsgo-pill has-text-color has-background has-small-font-size" style="margin-bottom:var(--wp--preset--spacing--15)"><span class="dsgo-pill__content" style="background-color:#6366f1;color:#ffffff;border-radius:50px">Most Popular</span></div>
<!-- /wp:designsetgo/pill -->`;

		const [block] = parse(LEGACY_STATIC_NO_ALIGN);

		expect(console).toHaveInformed();

		expect(block.isValid).toBe(true);
		expect(block.attributes.justification).toBe('center');
		expect(block.attributes.align).toBeUndefined();
		expect(block.attributes.style.color.background).toBe('#6366f1');
		expect(block.attributes.style.color.text).toBe('#ffffff');
		expect(block.attributes.style.border.radius).toBe('50px');
	});
});
