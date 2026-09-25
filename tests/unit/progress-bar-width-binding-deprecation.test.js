/**
 * Progress Bar Block - width-formula deprecation (dsgoStyleBinding fill width)
 *
 * The fill's `width` used to be a literal `N%` (or `0%` while animateOnScroll
 * waits for view.js). It is now a `clamp(0%, calc(...), 100%)` formula that
 * reads `--dsgo-progress` / `--dsgo-progress-max` custom properties, so a
 * `dsgoStyleBinding` (e.g. bound to `designsetgo/woo-stock-quantity`) can drive
 * the bar from the frontend render_block filter. This is a pure markup change
 * — attribute schema is unchanged — so content saved before this PR is invalid
 * against the new save() and must silently migrate via deprecated.js's v2
 * entry, with no "Attempt Recovery".
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
 * Serialize a bar the way the pre-binding save() did (literal `%` width), to
 * reproduce real stored markup from before this PR.
 *
 * @param {Object} attrs Block attributes to override.
 * @return {string} Legacy block markup.
 */
function legacyMarkup(attrs = {}) {
	register(deprecated[deprecated.length - 1].save);
	const block = createBlock(metadata.name, attrs);
	const markup = serialize(block);
	unregisterBlockType(metadata.name);
	return markup;
}

describe('Progress Bar - width-formula (style binding) deprecation', () => {
	afterEach(() => {
		if (getBlockType(metadata.name)) {
			unregisterBlockType(metadata.name);
		}
	});

	it('reproduces the old literal-percentage width in legacy markup', () => {
		const markup = legacyMarkup({
			animateOnScroll: false,
			percentage: 42,
		});
		expect(markup).toContain('width:42%');
		expect(markup).not.toContain('--dsgo-progress');
	});

	it('parses legacy literal-width content as valid (silent migration)', () => {
		const markup = legacyMarkup({
			animateOnScroll: false,
			percentage: 42,
		});
		register(save, deprecated);

		const [block] = parse(markup);

		// The parser logs an info message when a deprecation's save matches
		// and the block is silently migrated — proving no "Attempt Recovery".
		expect(console).toHaveInformed();
		expect(block).toBeTruthy();
		expect(block.name).toBe(metadata.name);
		expect(block.isValid).toBe(true);
		expect(block.attributes.percentage).toBe(42);
		expect(block.attributes.animateOnScroll).toBe(false);
	});

	it('current save() emits a --dsgo-progress calc() formula instead of a literal percentage', () => {
		register(save, deprecated);

		const markup = serialize(
			createBlock(metadata.name, {
				animateOnScroll: false,
				percentage: 42,
			})
		);

		expect(markup).toContain(
			'clamp(0%, calc(100% * var(--dsgo-progress, calc(42 / 100 * max(1, var(--dsgo-progress-max, 100)))) / max(1, var(--dsgo-progress-max, 100))), 100%)'
		);
		expect(markup).not.toContain('width:42%;');
	});

	it("floors the denominator so a --dsgo-progress-max binding resolving to 0 can't divide by zero", () => {
		register(save, deprecated);

		// animateOnScroll: false — the calc() formula only appears in the
		// non-animated branch; the animated branch is always a literal `0%`
		// regardless of this attribute (see the next test).
		const markup = serialize(
			createBlock(metadata.name, { animateOnScroll: false })
		);

		// max(1, ...) guards the denominator: if --dsgo-progress-max resolves
		// to 0 (or a negative number) at the CSS layer, calc() dividing by
		// zero would invalidate the WHOLE width declaration rather than just
		// that term, dropping the fill's width entirely.
		expect(markup).toContain('max(1, var(--dsgo-progress-max, 100))');
		expect(markup).not.toMatch(/\/\s*var\(--dsgo-progress-max/);
	});

	it('animateOnScroll still starts the fill at a literal 0% (unaffected by the binding formula)', () => {
		register(save, deprecated);

		const markup = serialize(
			createBlock(metadata.name, { animateOnScroll: true })
		);

		expect(markup).toContain('width:0%');
	});

	it('round-trips every attribute through parse → serialize losslessly', () => {
		register(save, deprecated);

		const original = createBlock(metadata.name, {
			percentage: 60,
			barColor: '#123456',
			animateOnScroll: false,
			showPercentage: true,
			labelPosition: 'inside',
		});
		const markup = serialize(original);
		const [reparsed] = parse(markup);

		expect(reparsed.isValid).toBe(true);
		expect(reparsed.attributes).toEqual(original.attributes);
	});
});
