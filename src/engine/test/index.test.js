/**
 * `createEngine(blocksApi)` binds `assemble`/`validate` to a `blocksApi`
 * once, so callers don't thread it through every call.
 */
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { createEngine } from '../index';
import { TREE_VERSION } from '../tree';

const { registerBlockType, unregisterBlockType, createBlock, serialize } =
	blocksApi;

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

beforeAll(() => {
	registerBlockType('test/static', staticBlockSettings);
});

afterAll(() => {
	unregisterBlockType('test/static');
});

describe('createEngine', () => {
	test('assemble() builds valid markup without a blocksApi argument', () => {
		const engine = createEngine(blocksApi);
		const tree = {
			version: TREE_VERSION,
			blocks: [{ name: 'test/static', attributes: { text: 'Hi' } }],
		};

		const result = engine.assemble(tree);

		expect(result.status).toBe('valid');
		expect(result.markup).toContain('<!-- wp:test/static');
	});

	test('validate() checks markup without a blocksApi argument', () => {
		const engine = createEngine(blocksApi);
		const markup = serialize(createBlock('test/static', { text: 'Hi' }));

		expect(engine.validate(markup)).toEqual({
			status: 'valid',
			invalid: [],
		});
	});
});
