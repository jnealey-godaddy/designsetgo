/**
 * The Abilities API's PHP serializer must produce markup the editor accepts.
 *
 * Block markup is written twice in this project: once by each block's save() in
 * JavaScript, and once by Block_Inserter's PHP mirror of it, which is what the
 * Abilities API stores when an agent inserts a block. Nothing checked that the
 * two agreed, and every divergence showed up only as "This block contains
 * unexpected or invalid content" after an agent had already written a page.
 *
 * Divergences this pins, all of which shipped:
 *
 * - designsetgo/fifty-fifty serialized to a bare self-closing comment because
 *   the PHP had no mirror for it, while its save() emits media and content
 *   wrappers.
 * - A section's `backgroundColor: "base"` reached the block comment but none of
 *   the `has-base-background-color has-background` classes reached the markup.
 * - The same for a paragraph's textColor and an Icon Button's colours, the
 *   latter routed onto the inner <a> by save().
 *
 * PHP cannot run save() and JavaScript cannot run the PHP serializer, so the
 * two meet through a fixture. tests/phpunit/abilities-generated-markup-fixture-test.php
 * regenerates it and fails when the PHP output drifts; this file parses it with
 * the real block registrations and asserts the editor finds every block valid.
 */
// Import from the copy nested under @wordpress/block-editor — the SAME instance
// its useBlockProps.save() talks to. See deprecations-isEligible.test.js for
// why the top-level @wordpress/blocks copy cannot be used here.
import {
	parse,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import fs from 'fs';
import path from 'path';

import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

const FIXTURE = path.join(
	__dirname,
	'__fixtures__/ability-generated-markup.json'
);

const BLOCKS_DIR = path.join(__dirname, '../../src/blocks');

/**
 * Collect every block name appearing in a markup string, at any depth.
 *
 * @param {string} markup Serialized block markup.
 * @return {string[]} Unique block names.
 */
function blockNamesIn(markup) {
	const names = new Set();
	const pattern = /<!--\s+wp:([a-z][a-z0-9-]*\/[a-z][a-z0-9-]*)/g;
	let match = pattern.exec(markup);

	while (match !== null) {
		names.add(match[1]);
		match = pattern.exec(markup);
	}

	return [...names];
}

/**
 * Walk a parsed block tree, collecting every block that failed validation.
 *
 * @param {Object[]} blocks Parsed blocks.
 * @param {string}   trail  Ancestry for the failure message.
 * @return {string[]} Descriptions of invalid blocks.
 */
function collectInvalid(blocks, trail = '') {
	const invalid = [];

	blocks.forEach((block, index) => {
		const where = trail
			? `${trail} > [${index}] ${block.name}`
			: `[${index}] ${block.name}`;

		if (block.name === 'core/missing') {
			invalid.push(`${where}: block type is not registered in this test`);
			return;
		}

		if (block.isValid === false) {
			invalid.push(
				`${where}\n    expected (save): ${block.validationIssues
					?.map((issue) => issue.args?.join(' | '))
					.join('\n      ')}`
			);
		}

		if (block.innerBlocks?.length) {
			invalid.push(...collectInvalid(block.innerBlocks, where));
		}
	});

	return invalid;
}

describe('Abilities-generated markup validates against save()', () => {
	let fixture;

	beforeAll(() => {
		expect(fs.existsSync(FIXTURE)).toBe(true);
		fixture = JSON.parse(fs.readFileSync(FIXTURE, 'utf8'));

		// Register every DesignSetGo block the fixture references. Core blocks
		// register themselves through the block-editor import.
		const needed = new Set();
		Object.values(fixture).forEach((markup) =>
			blockNamesIn(markup).forEach((name) => needed.add(name))
		);

		[...needed]
			.filter((name) => name.startsWith('designsetgo/'))
			.filter((name) =>
				fs.existsSync(
					path.join(BLOCKS_DIR, name.replace('designsetgo/', ''))
				)
			)
			.forEach(registerDesignSetGoBlock);
	});

	it('has payloads to check', () => {
		expect(Object.keys(fixture).length).toBeGreaterThan(0);
	});

	it('covers the reported failure case', () => {
		const markup =
			fixture['section-pill-paragraph-fifty-fifty-icon-button'];
		expect(markup).toBeDefined();
		// Fifty Fifty must carry real markup, not a self-closing comment.
		expect(markup).not.toMatch(
			/<!--\s+wp:designsetgo\/fifty-fifty[^>]*\/-->/
		);
		expect(markup).toContain('dsgo-fifty-fifty__content-inner');
	});

	it('validates every payload', () => {
		const failures = [];

		Object.entries(fixture).forEach(([label, markup]) => {
			const invalid = collectInvalid(parse(markup));
			if (invalid.length) {
				failures.push(`${label}:\n  ${invalid.join('\n  ')}`);
			}
		});

		expect(failures).toEqual([]);
	});
});
