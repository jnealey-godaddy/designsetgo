/**
 * `assemble()` builds an agent-submitted JSON block tree into real markup
 * via `createBlock`/`serialize`, then runs it back through `validate()`.
 *
 * See `validate.test.js` / `src/blocks/section/test/save.test.js` for why
 * these tests register through block-editor's nested `@wordpress/blocks`
 * copy rather than the top-level package.
 */
import {
	InnerBlocks,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor';
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { assemble } from '../assemble';
import { TREE_VERSION } from '../tree';

const { registerBlockType, unregisterBlockType } = blocksApi;

const staticBlockSettings = {
	title: 'Test Static',
	category: 'text',
	attributes: {
		text: { type: 'string', source: 'html', selector: 'p' },
	},
	save({ attributes }) {
		return <p>{attributes.text}</p>;
	},
};

const wrapBlockSettings = {
	title: 'Test Wrap',
	category: 'text',
	save() {
		return (
			<div>
				<InnerBlocks.Content />
			</div>
		);
	},
};

const throwingBlockSettings = {
	title: 'Test Throws',
	category: 'text',
	save() {
		throw new Error('boom');
	},
};

const objectAttributeBlockSettings = {
	title: 'Test Object Attribute',
	category: 'text',
	attributes: {
		settings: { type: 'object', default: { size: 'm' } },
	},
	save({ attributes }) {
		return <p data-size={attributes.settings.size}>Sized</p>;
	},
};

beforeAll(() => {
	registerBlockType('test/static', staticBlockSettings);
	registerBlockType('test/wrap', wrapBlockSettings);
	registerBlockType('test/throws', throwingBlockSettings);
	registerBlockType('test/object-attribute', objectAttributeBlockSettings);
});

afterAll(() => {
	unregisterBlockType('test/static');
	unregisterBlockType('test/wrap');
	unregisterBlockType('test/throws');
	unregisterBlockType('test/object-attribute');
});

describe('assemble: unknown attributes', () => {
	test('a misspelled attribute blocks the build and reports a suggestion', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [{ name: 'test/static', attributes: { txet: 'Hi' } }],
		};

		const result = assemble(blocksApi, tree);

		expect(result.status).toBe('invalid');
		expect(result.markup).toBe('');
		expect(result.invalid).toEqual([
			{
				path: 'blocks[0]',
				block: 'test/static',
				reason: 'unknown attribute "txet" for test/static — did you mean "text"?',
				code: 'designsetgo_unknown_attribute',
			},
		]);
	});

	test('an unknown attribute nested inside a valid parent is reported at its own path', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{
					name: 'test/wrap',
					innerBlocks: [
						{
							name: 'test/static',
							attributes: { text: 'Hi', bogusAttribute: 1 },
						},
					],
				},
			],
		};

		const result = assemble(blocksApi, tree);

		expect(result.status).toBe('invalid');
		expect(result.markup).toBe('');
		expect(result.invalid).toEqual([
			expect.objectContaining({
				path: 'blocks[0].innerBlocks[0]',
				block: 'test/static',
				code: 'designsetgo_unknown_attribute',
			}),
		]);
	});
});

describe('assemble', () => {
	test('valid nested tree assembles to markup containing both block comments', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{
					name: 'test/wrap',
					innerBlocks: [
						{ name: 'test/static', attributes: { text: 'Hi' } },
					],
				},
			],
		};

		const result = assemble(blocksApi, tree);

		expect(result.status).toBe('valid');
		expect(result.invalid).toEqual([]);
		expect(result.markup).toContain('<!-- wp:test/wrap');
		expect(result.markup).toContain('<!-- wp:test/static');
		expect(typeof result.treeHash).toBe('string');
		expect(result.treeHash).toHaveLength(64);
	});

	test('omitted attributes default to {}', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [{ name: 'test/wrap' }],
		};

		const result = assemble(blocksApi, tree);

		expect(result.status).toBe('valid');
		expect(result.markup).toContain('<!-- wp:test/wrap');
	});

	test('an unknown block name is reported at its exact nested path', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{
					name: 'test/wrap',
					innerBlocks: [
						{ name: 'test/static', attributes: { text: 'Hi' } },
						{ name: 'test/does-not-exist' },
					],
				},
			],
		};

		const result = assemble(blocksApi, tree);

		expect(result.status).toBe('invalid');
		expect(result.markup).toBe('');
		expect(result.invalid).toEqual([
			expect.objectContaining({
				path: 'blocks[0].innerBlocks[1]',
				block: 'test/does-not-exist',
				code: 'designsetgo_unknown_block',
			}),
		]);
	});

	test('an unsupported tree version is rejected before any block is built', () => {
		const tree = {
			version: 999,
			blocks: [{ name: 'test/static', attributes: { text: 'Hi' } }],
		};

		const result = assemble(blocksApi, tree);

		expect(result.status).toBe('invalid');
		expect(result.markup).toBe('');
		expect(result.invalid).toEqual([
			expect.objectContaining({
				path: 'version',
				code: 'designsetgo_unsupported_tree_version',
			}),
		]);
	});

	test('a malformed block definition is reported with the offending block name', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [{ name: 'test/static', attributes: 'not-an-object' }],
		};

		const result = assemble(blocksApi, tree);

		expect(result.status).toBe('invalid');
		expect(result.invalid).toEqual([
			expect.objectContaining({
				path: 'blocks[0]',
				block: 'test/static',
				code: 'designsetgo_invalid_block_definition',
			}),
		]);
	});

	test('a completely malformed tree is reported with no block name', () => {
		const result = assemble(blocksApi, { blocks: 'nope' });

		expect(result.status).toBe('invalid');
		expect(result.invalid).toEqual([
			expect.objectContaining({ path: 'blocks', block: '' }),
		]);
	});

	test('children a save() never renders are reported as dropped, not silently lost', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{
					name: 'test/wrap',
					innerBlocks: [
						{
							name: 'test/static',
							attributes: { text: 'Leaf' },
							innerBlocks: [
								{
									name: 'test/static',
									attributes: { text: 'Lost' },
								},
							],
						},
					],
				},
			],
		};

		const result = assemble(blocksApi, tree);

		expect(result.status).toBe('invalid');
		expect(result.invalid).toEqual([
			expect.objectContaining({
				path: 'blocks[0].innerBlocks[0]',
				block: 'test/static',
				code: 'designsetgo_dropped_inner_blocks',
			}),
		]);
	});

	test('a save() that throws is reported as invalid at the failing block, never thrown', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{ name: 'test/static', attributes: { text: 'Fine' } },
				{
					name: 'test/wrap',
					innerBlocks: [{ name: 'test/throws' }],
				},
			],
		};

		let result;
		expect(() => {
			result = assemble(blocksApi, tree);
		}).not.toThrow();

		expect(result.status).toBe('invalid');
		expect(result.markup).toBe('');
		expect(result.invalid).toEqual([
			{
				path: 'blocks[1].innerBlocks[0]',
				block: 'test/throws',
				reason: 'assemble failed: boom',
				code: 'designsetgo_assemble_error',
			},
		]);
		expect(result.treeHash).toHaveLength(64);
	});

	test('a top-level save() that throws is reported invalid, never thrown', () => {
		let result;
		expect(() => {
			result = assemble(blocksApi, {
				version: TREE_VERSION,
				blocks: [{ name: 'test/throws' }],
			});
		}).not.toThrow();

		expect(result.status).toBe('invalid');
		expect(result.invalid).toEqual([
			expect.objectContaining({
				path: 'blocks[0]',
				block: 'test/throws',
			}),
		]);
	});

	test('an empty object attribute is dropped so the block default applies', () => {
		const result = assemble(blocksApi, {
			version: TREE_VERSION,
			blocks: [
				{ name: 'test/object-attribute', attributes: { settings: {} } },
			],
		});

		expect(result.status).toBe('valid');
		expect(result.markup).toContain('data-size="m"');
	});

	test('identical trees yield identical treeHash', () => {
		const tree = {
			version: TREE_VERSION,
			blocks: [{ name: 'test/static', attributes: { text: 'Same' } }],
		};

		const first = assemble(blocksApi, tree);
		const second = assemble(blocksApi, JSON.parse(JSON.stringify(tree)));

		expect(first.treeHash).toBe(second.treeHash);
	});

	test('different trees yield different treeHash', () => {
		const treeA = {
			version: TREE_VERSION,
			blocks: [{ name: 'test/static', attributes: { text: 'A' } }],
		};
		const treeB = {
			version: TREE_VERSION,
			blocks: [{ name: 'test/static', attributes: { text: 'B' } }],
		};

		expect(assemble(blocksApi, treeA).treeHash).not.toBe(
			assemble(blocksApi, treeB).treeHash
		);
	});
});
