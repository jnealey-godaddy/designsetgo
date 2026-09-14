/**
 * `buildFixtureCases()` is the JS half of Task 8's drift guard: one
 * per-attribute probe per `designsetgo/*` block, handed to PHP so
 * `Block_Inserter` (the Abilities API's frozen PHP mirror of every block's
 * save()) can be exercised against real attribute values instead of only
 * each block's defaults.
 */
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { registerForJest } from '../../../src/engine/registry/sources-fs';
import { buildFixtureCases } from '../../../src/engine/node/fixture-cases';

// Registration is idempotent and must happen before any test reads real
// attribute schemas off the registry (mirrors round-trip.test.js).
registerForJest();

describe('buildFixtureCases', () => {
	let cases;

	beforeAll(() => {
		cases = buildFixtureCases(blocksApi);
	});

	it('finds cases for real blocks', () => {
		expect(Object.keys(cases).length).toBeGreaterThan(20);
	});

	it('only includes designsetgo/* blocks', () => {
		Object.keys(cases).forEach((name) => {
			expect(name).toMatch(/^designsetgo\//);
		});
	});

	it('excludes a block restricted to a parent', () => {
		// designsetgo/tab declares "parent": ["designsetgo/tabs"] in its
		// block.json — it can never be inserted as a bare top-level payload.
		expect(blocksApi.getBlockType('designsetgo/tab')).toBeDefined();
		expect(cases['designsetgo/tab']).toBeUndefined();
	});

	it('excludes a block restricted to an ancestor', () => {
		// designsetgo/query-filter declares "ancestor": ["designsetgo/query"].
		expect(
			blocksApi.getBlockType('designsetgo/query-filter')
		).toBeDefined();
		expect(cases['designsetgo/query-filter']).toBeUndefined();
	});

	it('includes one probe case per probeable attribute, keyed by attribute name', () => {
		const pillCases = cases['designsetgo/pill'];
		expect(pillCases).toBeDefined();
		expect(pillCases.content).toEqual({
			block_name: 'designsetgo/pill',
			attributes: { content: '7px' },
			inner_blocks: [],
		});
	});

	it('skips attributes with no meaningful non-default probe (object/array types)', () => {
		// designsetgo/advanced-heading's animatedHeadline is type object with
		// no enum — nonDefaultValue() returns undefined for it.
		const advancedHeadingCases = cases['designsetgo/advanced-heading'];
		expect(advancedHeadingCases).toBeDefined();
		expect(advancedHeadingCases.animatedHeadline).toBeUndefined();
	});

	it('sorts block names and, within each block, attribute names', () => {
		const blockNames = Object.keys(cases);
		expect(blockNames).toEqual([...blockNames].sort());

		blockNames.forEach((name) => {
			const attributeNames = Object.keys(cases[name]);
			expect(attributeNames).toEqual([...attributeNames].sort());
		});
	});

	it('every case is JSON-round-trippable to the documented shape', () => {
		const roundTripped = JSON.parse(JSON.stringify(cases));
		Object.entries(roundTripped).forEach(([blockName, attributeCases]) => {
			Object.entries(attributeCases).forEach(([attr, testCase]) => {
				expect(testCase.block_name).toBe(blockName);
				expect(testCase.inner_blocks).toEqual([]);
				expect(Object.keys(testCase.attributes)).toEqual([attr]);
			});
		});
	});
});
