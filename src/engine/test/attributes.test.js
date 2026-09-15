/**
 * `findUnknownAttributes()` reports agent-submitted attribute names a block
 * type doesn't register, with a "did you mean" suggestion when a known name
 * is a plausible typo away. See `assemble.test.js` for why these tests
 * register through block-editor's nested `@wordpress/blocks` copy.
 */
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import {
	attributeDistance,
	closestAttributeName,
	findUnknownAttributes,
} from '../attributes';
import { TREE_VERSION } from '../tree';

const { registerBlockType, unregisterBlockType } = blocksApi;

const testBlockSettings = {
	title: 'Test Block',
	category: 'text',
	attributes: {
		backgroundColor: { type: 'string' },
		text: { type: 'string' },
	},
	save() {
		return null;
	},
};

const wrapBlockSettings = {
	title: 'Test Wrap',
	category: 'text',
	attributes: {
		heading: { type: 'string' },
	},
	save() {
		return null;
	},
};

beforeAll(() => {
	registerBlockType('test/attr-block', testBlockSettings);
	registerBlockType('test/attr-wrap', wrapBlockSettings);
});

afterAll(() => {
	unregisterBlockType('test/attr-block');
	unregisterBlockType('test/attr-wrap');
});

describe('attributeDistance', () => {
	test('identical strings are distance 0', () => {
		expect(attributeDistance('backgroundColor', 'backgroundColor')).toBe(0);
	});

	test('a case-only difference is distance 0', () => {
		expect(attributeDistance('backgroundcolor', 'backgroundColor')).toBe(0);
	});

	test('a single substitution is distance 1', () => {
		expect(attributeDistance('backgroundColour', 'backgroundColor')).toBe(
			1
		);
	});

	test('an adjacent transposition is distance 1', () => {
		expect(attributeDistance('taxt', 'text')).toBe(1);
	});

	test('unrelated strings are far apart', () => {
		expect(attributeDistance('backgroundColor', 'zzz')).toBeGreaterThan(3);
	});
});

describe('closestAttributeName', () => {
	test('finds a close match within the short-name threshold', () => {
		expect(closestAttributeName('taxt', ['text', 'heading'])).toBe('text');
	});

	test('returns null when nothing is close enough', () => {
		expect(closestAttributeName('zzz', ['text', 'heading'])).toBeNull();
	});

	test('allows a wider distance for long names', () => {
		// 3 edits away from a 16-character name — too far for the short-name
		// threshold (2), allowed for a name >= 10 characters (3).
		expect(
			closestAttributeName('bakgroundColur', ['backgroundColor'])
		).toBe('backgroundColor');
	});
});

describe('findUnknownAttributes', () => {
	test('an invented attribute with no close match is reported without a suggestion', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{ name: 'test/attr-block', attributes: { madeUpThing: true } },
			],
		};

		expect(findUnknownAttributes(blocksApi, tree)).toEqual([
			{
				path: 'blocks[0]',
				block: 'test/attr-block',
				reason: 'unknown attribute "madeUpThing" for test/attr-block',
				code: 'designsetgo_unknown_attribute',
			},
		]);
	});

	test('a misspelled attribute is reported with a suggestion', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{
					name: 'test/attr-block',
					attributes: { backgroundColour: '#fff' },
				},
			],
		};

		expect(findUnknownAttributes(blocksApi, tree)).toEqual([
			{
				path: 'blocks[0]',
				block: 'test/attr-block',
				reason: 'unknown attribute "backgroundColour" for test/attr-block — did you mean "backgroundColor"?',
				code: 'designsetgo_unknown_attribute',
			},
		]);
	});

	test('a case-only mismatch suggests the registered casing', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{
					name: 'test/attr-block',
					attributes: { backgroundcolor: '#fff' },
				},
			],
		};

		expect(findUnknownAttributes(blocksApi, tree)[0].reason).toBe(
			'unknown attribute "backgroundcolor" for test/attr-block — did you mean "backgroundColor"?'
		);
	});

	test('a known attribute is not reported', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [{ name: 'test/attr-block', attributes: { text: 'hi' } }],
		};

		expect(findUnknownAttributes(blocksApi, tree)).toEqual([]);
	});

	test('a nested node reports its own path', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{
					name: 'test/attr-wrap',
					innerBlocks: [
						{
							name: 'test/attr-block',
							attributes: { madeUpThing: 1 },
						},
					],
				},
			],
		};

		expect(findUnknownAttributes(blocksApi, tree)).toEqual([
			{
				path: 'blocks[0].innerBlocks[0]',
				block: 'test/attr-block',
				reason: 'unknown attribute "madeUpThing" for test/attr-block',
				code: 'designsetgo_unknown_attribute',
			},
		]);
	});

	test('a node whose block type is unregistered is skipped (reported elsewhere)', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{
					name: 'test/does-not-exist',
					attributes: { anything: true },
				},
			],
		};

		expect(findUnknownAttributes(blocksApi, tree)).toEqual([]);
	});

	test('multiple unknown attributes on one node are all reported', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{
					name: 'test/attr-block',
					attributes: { madeUpOne: 1, madeUpTwo: 2 },
				},
			],
		};

		expect(findUnknownAttributes(blocksApi, tree)).toHaveLength(2);
	});
});
