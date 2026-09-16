/**
 * `findUnknownAttributes()`'s "known" set (see `src/engine/attributes.js`)
 * must cover everything a real editor page's block registry adds to
 * `getBlockType().attributes` — its own declared schema, extension-added
 * attributes, and every block-support attribute (`style`, `className`,
 * `anchor`, `backgroundColor`, `metadata`, `lock`, ...) — or a legitimate
 * agent-submitted attribute would be misreported as unknown. This file
 * exercises that under full registration (`registerForJest()`), separately
 * from `round-trip.test.js`'s generic per-attribute probe, so the specific
 * cases the brief calls out (an extension attribute, and each block-support
 * attribute) are directly traceable to a test.
 */
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { registerForJest } from '../../../src/engine/registry/sources-fs';
import { createEngine } from '../../../src/engine';
import { TREE_VERSION } from '../../../src/engine/tree';

registerForJest();

const engine = createEngine(blocksApi);

describe('unknown attributes: accepted under full registration', () => {
	it('accepts an extension-added attribute (dsgoAnimationEnabled on designsetgo/section)', () => {
		const result = engine.assemble({
			version: TREE_VERSION,
			blocks: [
				{
					name: 'designsetgo/section',
					attributes: { dsgoAnimationEnabled: true },
				},
			],
		});

		expect(result.status).toBe('valid');
		expect(result.invalid).toEqual([]);
	});

	it.each([
		['style', { spacing: { padding: { top: '10px' } } }],
		['className', 'my-custom-class'],
		['anchor', 'my-anchor'],
		['backgroundColor', 'primary'],
		['metadata', { name: 'My Section' }],
		['lock', { move: true, remove: false }],
	])('accepts the block-support attribute "%s"', (attribute, value) => {
		const result = engine.assemble({
			version: TREE_VERSION,
			blocks: [
				{
					name: 'designsetgo/section',
					attributes: { [attribute]: value },
				},
			],
		});

		expect(result.status).toBe('valid');
		expect(result.invalid).toEqual([]);
	});

	it('still rejects a genuinely unknown attribute on the same block', () => {
		const result = engine.assemble({
			version: TREE_VERSION,
			blocks: [
				{
					name: 'designsetgo/section',
					attributes: { backgroundColour: '#fff' },
				},
			],
		});

		expect(result.status).toBe('invalid');
		expect(result.invalid).toEqual([
			expect.objectContaining({
				code: 'designsetgo_unknown_attribute',
				reason: 'unknown attribute "backgroundColour" for designsetgo/section — did you mean "backgroundColor"?',
			}),
		]);
	});

	it('rejects a misspelled attribute on a core/* block with a suggestion', () => {
		// The browser engine's check has no PHP-side "fail open" carve-out —
		// every unknown name is caught here, on any registered block,
		// designsetgo/* or core/*, manifest-covered or not.
		const result = engine.assemble({
			version: TREE_VERSION,
			blocks: [
				{
					name: 'core/paragraph',
					attributes: { backgroundColour: '#fff' },
				},
			],
		});

		expect(result.status).toBe('invalid');
		expect(result.invalid).toEqual([
			expect.objectContaining({
				code: 'designsetgo_unknown_attribute',
				reason: 'unknown attribute "backgroundColour" for core/paragraph — did you mean "backgroundColor"?',
			}),
		]);
	});
});
