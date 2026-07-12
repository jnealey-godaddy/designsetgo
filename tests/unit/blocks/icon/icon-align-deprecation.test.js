/**
 * Icon Block - `align` → `justification` deprecation
 *
 * Regression coverage for the same Critical data-loss bug class fixed on the
 * Pill block: `vAlign.supports` must declare the FULL support set (color,
 * border, spacing), not just `align`. WP re-runs the
 * `blocks.registerBlockType` filters (color.js, border.js, spacing.js,
 * align.js) against EACH deprecation entry at registration time, and those
 * filters only add `backgroundColor` / `textColor` / `gradient` /
 * `borderColor` / `style` to a deprecation's `attributes` schema when the
 * matching support is declared THERE. A `supports` block naively written as
 * only `{ align: [...] }` silently strips those attributes in
 * `getBlockAttributes()` before `migrate()` ever runs — every styled,
 * align-positioned dynamic icon would lose its background/text color,
 * gradient, and border the next time the page was opened and saved.
 *
 * These tests go through the real `parse()` pipeline (not a direct
 * `migrate()` call) because that is the only place this class of bug is
 * observable — a direct `migrate()` call is handed already-correct
 * attributes and cannot detect that the parser silently discarded them
 * first.
 *
 * Also guards that deprecations do NOT cascade: `vLazy`, `v2`, and `v1` each
 * convert `align` → `justification` themselves (only one deprecation entry
 * ever runs for a given stored block), so a legacy STATIC icon matching one
 * of those entries must still land on `justification`, not the intermediate
 * `align` schema.
 *
 * @package
 */

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks;
// useBlockProps.save() (used by the deprecated entries' save()) resolves
// block supports against THAT copy's registry, so registration/parsing must
// go through the same instance (mirrors icon-token-deprecation.test.js).
const {
	registerBlockType,
	unregisterBlockType,
	parse,
	serialize,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import metadata from '../../../../src/blocks/icon/block.json';
import save from '../../../../src/blocks/icon/save';
import deprecated from '../../../../src/blocks/icon/deprecated';

// deprecated is [vAlign, vLazy, v2, v1] — vAlign (newest) is the last
// DYNAMIC version positioned with `align`; vLazy is the last STATIC version.
const [vAlign, vLazy] = deprecated;

describe('Icon deprecation - vAlign.migrate()', () => {
	it('converts an explicit alignment to justification and drops align', () => {
		const migrated = vAlign.migrate({
			icon: 'star',
			align: 'right',
		});

		expect(migrated).not.toHaveProperty('align');
		expect(migrated.justification).toBe('right');
		expect(migrated.icon).toBe('star');
	});
});

describe('Icon deprecation - a dynamic icon authored with align migrates silently', () => {
	beforeAll(() => {
		registerBlockType(metadata.name, {
			...metadata,
			// The custom 'designsetgo' category isn't registered in the jest
			// environment; category is irrelevant to parse/validation, so use
			// a built-in one to avoid an unrelated invalid-category warning.
			category: 'design',
			save,
			deprecated,
		});
	});

	afterAll(() => {
		unregisterBlockType(metadata.name);
	});

	it('migrates align:left into justification without an "Attempt Recovery" warning', () => {
		// A dynamic icon (save() === null) with `align` set parses as VALID
		// under the current schema already (content is always '', so save()
		// output trivially matches regardless of attribute values) — the
		// vAlign entry migrates it via the `isEligible` opt-in path for an
		// already-valid block, which is silent (no console.info), unlike the
		// invalid → valid path exercised by the legacy STATIC fixtures below.
		const [block] = parse(
			'<!-- wp:designsetgo/icon {"icon":"star","align":"left"} /-->'
		);

		expect(block.isValid).toBe(true);
		expect(block.attributes.justification).toBe('left');
		expect(block.attributes.align).toBeUndefined();
	});

	it('leaves align:wide untouched (wide/full stay on align, not justification)', () => {
		const [block] = parse(
			'<!-- wp:designsetgo/icon {"icon":"star","align":"wide"} /-->'
		);

		expect(block.isValid).toBe(true);
		expect(block.attributes.align).toBe('wide');
		expect(block.attributes.justification).toBe('center');
	});

	it('re-serializes clean: justification kept, no stray align, still a self-closing comment', () => {
		const [block] = parse(
			'<!-- wp:designsetgo/icon {"icon":"star","align":"right"} /-->'
		);

		const serialized = serialize(block);
		expect(serialized).toContain('"justification":"right"');
		expect(serialized).not.toContain('"align"');
		expect(serialized).not.toContain('<div');
	});
});

/**
 * The Critical bug class described in the file docblock: a styled,
 * align-positioned dynamic icon (preset background + preset gradient +
 * border colour + explicit align) must migrate with NO visual attribute
 * dropped, and `justification` set. Before the fix, `vAlign.supports` only
 * declared `align`, so backgroundColor/gradient/borderColor were silently
 * stripped by getBlockAttributes() before migrate() ran (confirmed by
 * stashing the `color`/`__experimentalBorder` groups out of vAlign.supports
 * and re-running this test — it fails with all three attributes undefined).
 */
describe('Icon deprecation - styled icon retains visual attributes through migration', () => {
	beforeAll(() => {
		registerBlockType(metadata.name, {
			...metadata,
			category: 'design',
			save,
			deprecated,
		});
	});

	afterAll(() => {
		unregisterBlockType(metadata.name);
	});

	it('vAlign: a styled, align-positioned dynamic icon keeps background/gradient/border', () => {
		const [block] = parse(
			'<!-- wp:designsetgo/icon {"icon":"star","align":"right","backgroundColor":"accent-3","gradient":"purple-to-blue","borderColor":"contrast","style":{"border":{"radius":"4px"}}} /-->'
		);

		expect(block.isValid).toBe(true);
		expect(block.attributes.justification).toBe('right');
		expect(block.attributes.align).toBeUndefined();
		expect(block.attributes.backgroundColor).toBe('accent-3');
		expect(block.attributes.gradient).toBe('purple-to-blue');
		expect(block.attributes.borderColor).toBe('contrast');
		expect(block.attributes.style.border.radius).toBe('4px');
	});

	it('vAlign.supports declares the full visual support set (regression guard)', () => {
		// A `supports` object that only lists `align` is exactly the bug this
		// guards: WP would never register backgroundColor/gradient/borderColor
		// as attributes for this deprecation at all.
		expect(vAlign.supports.color).toBeTruthy();
		expect(vAlign.supports.color.background).toBe(true);
		expect(vAlign.supports.color.gradients).toBe(true);
		expect(vAlign.supports.__experimentalBorder).toBeTruthy();
		expect(vAlign.supports.spacing).toBeTruthy();
	});
});

describe('Icon deprecation - legacy static icons convert align without cascading', () => {
	beforeAll(() => {
		registerBlockType(metadata.name, {
			...metadata,
			category: 'design',
			save,
			deprecated,
		});
	});

	afterAll(() => {
		unregisterBlockType(metadata.name);
	});

	it('vLazy.migrate() converts align to justification directly (no cascade through vAlign)', () => {
		const migrated = vLazy.migrate({
			icon: 'star',
			align: 'left',
			backgroundColor: 'accent-3',
		});

		expect(migrated).not.toHaveProperty('align');
		expect(migrated.justification).toBe('left');
		expect(migrated.backgroundColor).toBe('accent-3');
	});

	it('vLazy.migrate() preserves wide/full on align instead of converting it', () => {
		const migrated = vLazy.migrate({
			icon: 'star',
			align: 'full',
		});

		expect(migrated.align).toBe('full');
		expect(migrated.justification).toBe('center');
	});
});
