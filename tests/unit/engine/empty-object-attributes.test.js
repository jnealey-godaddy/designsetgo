/**
 * An agent (or a PHP round trip, where `{}` and `[]` are indistinguishable)
 * often sends an object attribute as an empty object: `style: {}`. Handed to
 * `createBlock()` as-is, `designsetgo/section` serialized markup that no
 * longer re-parsed identically, so the whole build failed. `assemble()` drops
 * empty-object attributes before `createBlock()`, letting the block's own
 * default apply.
 */
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { registerForJest } from '../../../src/engine/registry/sources-fs';
import { createEngine } from '../../../src/engine';
import { TREE_VERSION } from '../../../src/engine/tree';

registerForJest();

const engine = createEngine(blocksApi);

describe('empty object attributes', () => {
	test('designsetgo/section with style: {} assembles valid', () => {
		const result = engine.assemble({
			version: TREE_VERSION,
			blocks: [
				{
					name: 'designsetgo/section',
					attributes: { style: {} },
					innerBlocks: [
						{
							name: 'core/paragraph',
							attributes: { content: 'Inside' },
						},
					],
				},
			],
		});

		expect({ status: result.status, invalid: result.invalid }).toEqual({
			status: 'valid',
			invalid: [],
		});
	});
});
