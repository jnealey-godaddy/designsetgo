/**
 * Guard: a deprecation's isEligible() must never claim CURRENT content.
 *
 * WordPress calls isEligible as:
 *
 *   isEligible( attributes, innerBlocks, { blockNode, block } )
 *
 * (see @wordpress/blocks → api/parser/apply-block-deprecated-versions.js). There
 * is NO `innerHTML` key on that third argument. Every DSGo deprecation used to
 * destructure `{ innerHTML }`, so the value was always `undefined` and — because
 * each guard begins with a truthiness check — isEligible always returned false.
 *
 * That was invisible, because isEligible only matters for a block that is
 * otherwise VALID:
 *
 *   if ( block.isValid && ! isEligible( ... ) ) continue;
 *
 * For an INVALID block the `block.isValid &&` short-circuits, isEligible is
 * skipped entirely, and WordPress instead picks the deprecation whose save()
 * reproduces the stored HTML. That path carried every migration we had, which is
 * why nothing appeared broken.
 *
 * Now that the guards actually receive the markup, they can fire — and firing on
 * a VALID block opts it into a needless re-migration. This test pins the
 * invariant: for each block's own canonical, current save() output, no
 * deprecation may claim it, and a parse/serialize round trip must be lossless.
 */
// Import the block API from the copy nested under @wordpress/block-editor — the
// SAME instance its useBlockProps.save() talks to. Jest resolves two copies of
// @wordpress/blocks, and getSaveElement() hands the block type + attributes to
// useBlockProps via a module-scoped provider; across instances that provider
// reads back empty and the support filters throw on `undefined` attributes. The
// rest of the suite (e.g. src/blocks/blobs/test/deprecated.test.js) already
// imports from this path for the same reason.
import {
	parse,
	serialize,
	createBlock,
	getBlockType,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { parse as parseRaw } from '@wordpress/block-serialization-default-parser';
import fs from 'fs';
import path from 'path';

import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

const BLOCKS_DIR = path.join(__dirname, '../../src/blocks');

const blocksWithDeprecations = fs
	.readdirSync(BLOCKS_DIR)
	.filter((slug) =>
		fs.existsSync(path.join(BLOCKS_DIR, slug, 'deprecated.js'))
	)
	.map((slug) => `designsetgo/${slug}`);

describe('deprecations must not reclaim current content', () => {
	beforeAll(() => {
		blocksWithDeprecations.forEach(registerDesignSetGoBlock);
	});

	it('finds the blocks under test', () => {
		expect(blocksWithDeprecations.length).toBeGreaterThan(20);
	});

	describe.each(blocksWithDeprecations)('%s', (name) => {
		// Canonical current markup: what save() produces for this block today.
		const markup = () => serialize(createBlock(name, {}));

		it('parses its own current save() output as valid', () => {
			const [block] = parse(markup());
			expect(block.name).toBe(name);
			expect(block.isValid).toBe(true);
		});

		it('is not claimed by any of its own deprecations', () => {
			const html = markup();
			const [block] = parse(html);
			const [blockNode] = parseRaw(html);
			const { deprecated = [] } = getBlockType(name);

			const claimed = deprecated
				.map((dep, i) => ({
					i,
					eligible: Boolean(
						dep.isEligible?.(blockNode.attrs, block.innerBlocks, {
							blockNode,
							block,
						})
					),
				}))
				.filter((d) => d.eligible)
				.map((d) => `deprecated[${d.i}]`);

			expect(claimed).toEqual([]);
		});

		it('round-trips its attributes without migration', () => {
			const block = createBlock(name, {});
			const [reparsed] = parse(serialize(block));
			expect(reparsed.attributes).toEqual(block.attributes);
		});
	});
});
