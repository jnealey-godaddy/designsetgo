/**
 * `validate()` parses markup with the real `@wordpress/blocks` parser and
 * reports which blocks in the tree failed validation.
 *
 * `save.js` (and this module) reads block support metadata via
 * `getBlockType()` from block-editor's OWN nested copy of `@wordpress/blocks`
 * (block-editor requires ^14.15.0; the top-level package resolves to an
 * older version). Registering through the top-level `@wordpress/blocks`
 * import would leave that nested registry empty, so tests register through
 * the nested path — see `src/blocks/section/test/save.test.js` for the full
 * explanation of why this repo's dependency tree forces that.
 */
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { validate } from '../validate';

const {
	createBlock,
	serialize,
	registerBlockType,
	unregisterBlockType,
	setUnregisteredTypeHandlerName,
} = blocksApi;

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

// `core/missing` is what WordPress core registers as the "unregistered
// block" fallback type; the Jest environment doesn't register core blocks,
// so validate.test.js registers a minimal stand-in and points the parser at
// it via `setUnregisteredTypeHandlerName`.
const missingBlockSettings = {
	title: 'Missing',
	category: 'text',
	attributes: {
		originalName: { type: 'string' },
		originalContent: { type: 'string', source: 'html' },
		originalUndelimitedContent: { type: 'string' },
	},
	save({ attributes }) {
		return attributes.originalContent;
	},
};

beforeAll(() => {
	registerBlockType('test/static', staticBlockSettings);
	registerBlockType('core/missing', missingBlockSettings);
	setUnregisteredTypeHandlerName('core/missing');
});

afterAll(() => {
	unregisterBlockType('test/static');
	unregisterBlockType('core/missing');
	setUnregisteredTypeHandlerName(undefined);
});

describe('validate', () => {
	test('valid markup reports status "valid" with no invalid entries', () => {
		const markup = serialize(createBlock('test/static', { text: 'Hi' }));

		expect(validate(blocksApi, markup)).toEqual({
			status: 'valid',
			invalid: [],
		});
	});

	test('markup whose tag was hand-edited away from save() is invalid at blocks[0]', () => {
		const validMarkup = serialize(
			createBlock('test/static', { text: 'Hello' })
		);
		// Swap the <p> tag for <span> without touching the block comment —
		// this desyncs the parsed `text` attribute (sourced via a `<p>`
		// selector) from what save() would reproduce.
		const tamperedMarkup = validMarkup
			.replace('<p>', '<span>')
			.replace('</p>', '</span>');

		const result = validate(blocksApi, tamperedMarkup);

		expect(result.status).toBe('invalid');
		expect(result.invalid).toHaveLength(1);
		expect(result.invalid[0].path).toBe('blocks[0]');
		expect(result.invalid[0].block).toBe('test/static');
		expect(typeof result.invalid[0].reason).toBe('string');
		expect(result.invalid[0].reason.length).toBeGreaterThan(0);
	});

	test('an unregistered block comment is reported as core/missing', () => {
		const markup =
			'<!-- wp:test/unregistered-thing {"foo":"bar"} -->' +
			'<div>whatever</div>' +
			'<!-- /wp:test/unregistered-thing -->';

		const result = validate(blocksApi, markup);

		expect(result.status).toBe('invalid');
		expect(result.invalid).toEqual([
			{
				path: 'blocks[0]',
				block: 'core/missing',
				reason: 'block type is not registered',
			},
		]);
	});
});
