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

// vStatic is the first (newest) deprecation — the last static version.
const [vStatic] = deprecated;

describe('Pill deprecation - vStatic.migrate()', () => {
	it('drops the old baked center/small defaults', () => {
		const migrated = vStatic.migrate({
			content: 'Test',
			align: 'center',
			fontSize: 'small',
		});

		expect(migrated).not.toHaveProperty('align');
		expect(migrated).not.toHaveProperty('fontSize');
		expect(migrated.content).toBe('Test');
	});

	it('preserves an explicit non-default alignment and font size', () => {
		const migrated = vStatic.migrate({
			content: 'Test',
			align: 'right',
			fontSize: 'large',
		});

		expect(migrated.align).toBe('right');
		expect(migrated.fontSize).toBe('large');
		expect(migrated.content).toBe('Test');
	});

	it('preserves an explicit alignment even when no font size is set', () => {
		const migrated = vStatic.migrate({
			content: 'Test',
			align: 'left',
		});

		expect(migrated.align).toBe('left');
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
		expect(block.attributes.align).toBe('right');
		expect(block.attributes.fontSize).toBe('large');
	});

	it('re-serializes clean: attributes kept, no baked classes in dynamic output', () => {
		const [block] = parse(LEGACY_PILL_EXPLICIT);

		expect(console).toHaveInformed();

		const serialized = serialize(block);
		expect(serialized).toContain('"align":"right"');
		expect(serialized).toContain('"fontSize":"large"');
		// Dynamic block → self-closing comment, no stored HTML / baked classes.
		expect(serialized).not.toContain('has-large-font-size');
		expect(serialized).not.toContain('<div');
	});
});
