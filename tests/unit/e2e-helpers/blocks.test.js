/**
 * Unit tests for the e2e block-discovery helper.
 *
 * Reads the real src/blocks/*\/block.json files, so it doubles as a guard that
 * the top-level block set the e2e sweep will cover stays sane.
 */
const { listTopLevelDesignSetGoBlocks } = require('../../e2e/helpers/blocks');

describe('listTopLevelDesignSetGoBlocks', () => {
	const blocks = listTopLevelDesignSetGoBlocks();

	it('returns a non-empty, sorted array of designsetgo blocks', () => {
		expect(Array.isArray(blocks)).toBe(true);
		expect(blocks.length).toBeGreaterThan(20);
		expect(blocks.every((n) => n.startsWith('designsetgo/'))).toBe(true);
		expect([...blocks]).toEqual([...blocks].sort());
	});

	it('includes representative top-level blocks', () => {
		expect(blocks).toContain('designsetgo/card');
		expect(blocks).toContain('designsetgo/accordion');
		expect(blocks).toContain('designsetgo/tabs');
	});

	it('excludes child blocks constrained by parent/ancestor', () => {
		expect(blocks).not.toContain('designsetgo/accordion-item');
		expect(blocks).not.toContain('designsetgo/tab');
		expect(blocks).not.toContain('designsetgo/form-text-field');
		expect(blocks).not.toContain('designsetgo/query-pagination');
	});

	it('excludes WooCommerce-gated product blocks', () => {
		expect(blocks).not.toContain('designsetgo/product-showcase-hero');
		expect(blocks).not.toContain('designsetgo/product-categories-grid');
	});
});
