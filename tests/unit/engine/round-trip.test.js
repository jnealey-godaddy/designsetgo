/**
 * Round-trips every attribute of every registered DesignSetGo block through
 * the engine: assemble() from a JSON tree, then re-parse the resulting
 * markup and confirm the attribute survived unchanged. This is the engine's
 * core promise — whatever an agent sets, the editor gets back.
 */
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { registerForJest } from '../../../src/engine/registry/sources-fs';
import { createEngine } from '../../../src/engine';
import { TREE_VERSION } from '../../../src/engine/tree';
import { nonDefaultValue } from '../helpers/non-default-value';

// Registration is idempotent (an already-registered `designsetgo/section`
// short-circuits — see sources-fs.js) and must happen before `describe.each`
// below reads real attribute schemas off `getBlockType()`, which runs while
// Jest is still collecting this file's tests — before any `beforeAll` would
// fire. Muted internally via `withQuietConsole`.
registerForJest();

const engine = createEngine(blocksApi);

/**
 * Attributes a round trip legitimately cannot preserve, never a save() bug.
 * Keyed by `blockName::attribute`, valued with the reason. A `KNOWN_LOSSY`
 * case still asserts `assemble()` produces valid markup; only the
 * value-preservation assertion is skipped.
 *
 * @type {Object<string, string>}
 */
const KNOWN_LOSSY = {
	// Both blocks still declare `align` in their attribute schema (so old
	// stored content keeps validating), but the "justification, not align"
	// migration (see CLAUDE.md "Horizontal positioning: justification, not
	// align") intercepts ANY block carrying an `align` value at parse time
	// and converts it to `justification`, dropping `align` entirely — by
	// design, not a save() bug. See src/blocks/icon/deprecated.js `vAlign`
	// (`isEligible` matches align in left|center|right) and
	// src/blocks/pill/deprecated.js `vAlign` (`isEligible` matches align's
	// mere presence via `hasOwnProperty`).
	'designsetgo/icon::align':
		'align is intentionally migrated to justification and dropped by the vAlign deprecation (icon/deprecated.js)',
	'designsetgo/pill::align':
		'align is intentionally migrated to justification and dropped by the vAlign deprecation (pill/deprecated.js)',
};

/**
 * Whether an attribute is worth probing at all.
 *
 * `role: 'local'` attributes are explicitly editor-only and never intended
 * to serialize. `__experimental*` names are unstable by WordPress
 * convention. A `source` other than `html`/`text` reads from markup
 * structure the engine doesn't control (e.g. an `attribute`/`query` source
 * reflects a DOM node this probe's single-attribute tree may not render at
 * all), so it is not a fair probe of the engine's own round trip.
 *
 * @param {string} attr   Attribute name.
 * @param {Object} schema Attribute schema.
 * @return {boolean} Whether to probe this attribute.
 */
function isProbeable(attr, schema) {
	if (schema.role === 'local') {
		return false;
	}
	if (attr.startsWith('__experimental')) {
		return false;
	}
	if (schema.source && schema.source !== 'html' && schema.source !== 'text') {
		return false;
	}
	return true;
}

/**
 * One non-default-value probe per probeable attribute on a registered block.
 *
 * @param {string} name Registered block name.
 * @return {{attr: string, value: *}[]} Probes.
 */
function probesFor(name) {
	const blockType = blocksApi.getBlockType(name);
	return Object.entries(blockType.attributes ?? {})
		.filter(([attr, schema]) => isProbeable(attr, schema))
		.map(([attr, schema]) => ({ attr, value: nonDefaultValue(schema) }))
		.filter(({ value }) => value !== undefined);
}

const names = blocksApi
	.getBlockTypes()
	.map((blockType) => blockType.name)
	.filter((name) => name.startsWith('designsetgo/'))
	.sort();

describe('engine round trip', () => {
	it('finds the blocks under test', () => {
		expect(names.length).toBeGreaterThan(50);
	});

	describe.each(names)('%s', (name) => {
		it('assembles with default attributes', () => {
			const result = engine.assemble({
				version: TREE_VERSION,
				blocks: [{ name }],
			});

			expect({
				status: result.status,
				invalid: result.invalid,
			}).toEqual({ status: 'valid', invalid: [] });
		});

		test.each(probesFor(name))('round-trips $attr', ({ attr, value }) => {
			const result = engine.assemble({
				version: TREE_VERSION,
				blocks: [{ name, attributes: { [attr]: value } }],
			});

			expect({
				status: result.status,
				invalid: result.invalid,
			}).toEqual({ status: 'valid', invalid: [] });

			if (KNOWN_LOSSY[`${name}::${attr}`]) {
				return;
			}

			const [parsed] = blocksApi.parse(result.markup);
			expect(parsed.attributes[attr]).toEqual(value);
		});
	});
});
