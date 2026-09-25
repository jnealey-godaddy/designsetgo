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

/**
 * A value that differs from the attribute's default, so the attribute actually
 * lands in the block comment AND in save()'s output.
 *
 * Testing only `createBlock(name, {})` would miss any guard keyed on markup that
 * a DEFAULT block never emits — grid's `repeat(N, minmax(...))` track, for one,
 * which only appears once `columnMinWidth` is set. Those guards are precisely
 * the ones that can quietly start claiming current content.
 *
 * @param {Object} schema The attribute's block.json schema.
 * @return {*} A non-default value, or `undefined` to skip this attribute.
 */
function nonDefaultValue(schema) {
	if (schema.enum) {
		return schema.enum.find((v) => v !== schema.default);
	}

	switch (schema.type) {
		case 'boolean':
			return !schema.default;
		case 'number':
		case 'integer':
			return (schema.default ?? 0) + 1;
		case 'string':
			// A length + unit reads as valid CSS wherever a value is interpolated
			// into a style, and as an ordinary string everywhere else.
			return schema.default === '7px' ? '9px' : '7px';
		default:
			// Objects/arrays (style, lock, metadata, border…) have no meaningful
			// generic probe; the defaults case already covers them.
			return undefined;
	}
}

/**
 * One probe per attribute, each isolating a single non-default value, plus the
 * all-defaults case. `align` is driven off `supports.align` rather than the
 * attribute schema, since its legal values live there — and it is the attribute
 * three of the removed guards keyed on.
 *
 * @param {string} name Block name.
 * @return {Array<{label: string, attrs: Object}>} Probes.
 */
function probesFor(name) {
	const blockType = getBlockType(name);
	const probes = [{ label: 'defaults', attrs: {} }];

	const alignSupport = blockType.supports?.align;
	if (Array.isArray(alignSupport)) {
		alignSupport.forEach((align) =>
			probes.push({ label: `align=${align}`, attrs: { align } })
		);
	}

	Object.entries(blockType.attributes ?? {}).forEach(([attr, schema]) => {
		if (attr === 'align' || schema.source) {
			return;
		}
		const value = nonDefaultValue(schema);
		if (value === undefined) {
			return;
		}
		probes.push({
			label: `${attr}=${JSON.stringify(value)}`,
			attrs: { [attr]: value },
		});
	});

	return probes;
}

/**
 * Markup-sourced attributes that hold a translated fallback label read back
 * from the stored HTML, so the block stays valid when the post is opened in
 * another editor language. They are unset on a fresh block and take the
 * rendered default on the first reparse, which is the one change the
 * round-trip test accepts. List each one by name: a blanket allowance for
 * every sourced attribute would also hide a real content attribute that
 * started coming back with a value it was never given.
 */
const FROZEN_FALLBACKS = {
	'designsetgo/card': ['badgeAriaLabel', 'imageFallbackAlt'],
	'designsetgo/comparison-table': ['featuredBadgeText', 'savedCtaTexts'],
	'designsetgo/hotspot-item': ['markerAriaLabel'],
	'designsetgo/modal': ['savedModalLabel', 'savedCloseButtonLabel'],
};

describe('deprecations must not reclaim current content', () => {
	beforeAll(() => {
		blocksWithDeprecations.forEach(registerDesignSetGoBlock);
	});

	it('finds the blocks under test', () => {
		expect(blocksWithDeprecations.length).toBeGreaterThan(20);
	});

	describe.each(blocksWithDeprecations)('%s', (name) => {
		it('parses its own current save() output as valid', () => {
			const [block] = parse(serialize(createBlock(name, {})));
			expect(block.name).toBe(name);
			expect(block.isValid).toBe(true);
		});

		// Swept across every single-attribute variant, not just the defaults: a
		// guard only reachable via non-default markup would otherwise never be
		// exercised here.
		it('is not claimed by any of its own deprecations', () => {
			const { deprecated = [] } = getBlockType(name);
			const claimed = [];

			probesFor(name).forEach(({ label, attrs }) => {
				const html = serialize(createBlock(name, attrs));
				const [block] = parse(html);
				const [blockNode] = parseRaw(html);

				deprecated.forEach((dep, i) => {
					const eligible = Boolean(
						dep.isEligible?.(blockNode.attrs, block.innerBlocks, {
							blockNode,
							block,
						})
					);
					if (eligible) {
						claimed.push(`deprecated[${i}] claims ${label}`);
					}
				});
			});

			expect(claimed).toEqual([]);
		});

		it('round-trips its attributes without migration', () => {
			const lost = [];
			const frozen = FROZEN_FALLBACKS[name] ?? [];

			probesFor(name).forEach(({ label, attrs }) => {
				const block = createBlock(name, attrs);
				const [reparsed] = parse(serialize(block));
				// A frozen fallback label left unset is read back from the
				// saved HTML on the next parse (see FROZEN_FALLBACKS), so
				// unset → value is expected for those; any other change is
				// a loss.
				const expected = { ...block.attributes };
				frozen.forEach((attr) => {
					if (
						expected[attr] === undefined &&
						reparsed.attributes[attr] !== undefined
					) {
						expected[attr] = reparsed.attributes[attr];
					}
				});
				try {
					expect(reparsed.attributes).toEqual(expected);
				} catch {
					lost.push(label);
				}
			});

			expect(lost).toEqual([]);
		});
	});
});
