/**
 * Generates the per-attribute probe cases the `fixture-cases` CLI command
 * writes to `tests/unit/__fixtures__/ability-generated-cases.json`.
 *
 * Those cases are the JS half of Task 8's drift guard for the Abilities
 * API's PHP `Block_Inserter`: PHP has no way to enumerate "every attribute
 * of every block" on its own (only the JS block registry knows each
 * attribute's schema/default), so this module does that enumeration once,
 * and `tests/phpunit/abilities-generated-markup-fixture-test.php` serializes
 * each case through `Block_Inserter` and hands the result to
 * `tests/unit/ability-generated-markup.test.js` to validate against the
 * real save().
 *
 * CommonJS, matching every other `src/engine/node/*` file: this module is
 * `require()`d from `run.js`, both under the webpack Node bundle and under
 * plain Jest.
 */
'use strict';

const { nonDefaultValue } = require('../testing/non-default-value');

/**
 * Mirrors `tests/unit/engine/round-trip.test.js`'s `isProbeable()`: skip
 * editor-only (`role: 'local'`) attributes, unstable `__experimental*`
 * names, and any attribute whose `source` reads from rendered markup
 * (`attribute`/`query`) rather than the block comment. None of those are a
 * fair single-attribute probe of what a writer puts INTO the comment, which
 * is the only thing `Block_Inserter` (or any writer) controls.
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
 * Whether a block belongs in the generated cases at all.
 *
 * `parent`/`ancestor`-restricted blocks (e.g. `designsetgo/tab`, which only
 * makes sense inside `designsetgo/tabs`) are excluded: a bare top-level
 * payload for one would fail for a reason that has nothing to do with
 * `Block_Inserter`'s output. Any OTHER reason a block can't be serialized is
 * `Block_Inserter`'s own concern — this module only knows the JS registry,
 * never PHP — so the PHP payload builder is where that skip belongs (see
 * `generated_payloads()` in the PHPUnit fixture test).
 *
 * @param {Object} blockType A registered block type from `getBlockTypes()`.
 * @return {boolean} Whether this block should get generated cases.
 */
function isEligibleBlock(blockType) {
	return (
		blockType.name.startsWith('designsetgo/') &&
		!blockType.parent &&
		!blockType.ancestor
	);
}

/**
 * @param {Object} blocksApi A `@wordpress/blocks`-shaped module, already
 *                           populated by `registerForNode()`/`registerForJest()`.
 * @return {Object<string, Object<string, {block_name: string, attributes: Object, inner_blocks: Array}>>}
 *   Cases nested by block name then attribute name, both sorted so the
 *   written JSON is diff-friendly and regenerating it is a no-op when
 *   nothing actually changed.
 */
function buildFixtureCases(blocksApi) {
	const cases = {};

	const eligibleNames = blocksApi
		.getBlockTypes()
		.filter(isEligibleBlock)
		.map((blockType) => blockType.name)
		.sort();

	eligibleNames.forEach((name) => {
		const blockType = blocksApi.getBlockType(name);
		const attributeCases = {};

		Object.keys(blockType.attributes ?? {})
			.sort()
			.forEach((attr) => {
				const schema = blockType.attributes[attr];
				if (!isProbeable(attr, schema)) {
					return;
				}
				const value = nonDefaultValue(schema);
				if (value === undefined) {
					return;
				}
				attributeCases[attr] = {
					block_name: name,
					attributes: { [attr]: value },
					inner_blocks: [],
				};
			});

		if (Object.keys(attributeCases).length) {
			cases[name] = attributeCases;
		}
	});

	return cases;
}

module.exports = { buildFixtureCases };
