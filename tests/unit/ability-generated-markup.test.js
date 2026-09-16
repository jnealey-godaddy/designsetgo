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
 *
 * "The real block registrations" means the DesignSetGo ones. Core blocks cannot
 * be registered here — see the note in beforeAll — and an unregistered block is
 * dropped by parse() rather than reported invalid, so core payloads pass this
 * file without being checked. The 'pins which block names go unvalidated' test
 * fixes the size of that hole so it cannot widen unnoticed.
 */
// Import from the copy nested under @wordpress/block-editor — the SAME instance
// its useBlockProps.save() talks to. See deprecations-isEligible.test.js for
// why the top-level @wordpress/blocks copy cannot be used here.
import {
	getBlockType,
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
 * A core block serializes WITHOUT its namespace — `<!-- wp:heading -->`, never
 * `<!-- wp:core/heading -->` — so the namespace is restored here. Requiring the
 * slash instead silently skipped every core block in the fixture, which is a
 * large part of why they went unnoticed as unregistered: nothing could even
 * name them to ask.
 *
 * @param {string} markup Serialized block markup.
 * @return {string[]} Unique block names.
 */
function blockNamesIn(markup) {
	const names = new Set();
	const pattern = /<!--\s+wp:([a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)?)/g;
	let match = pattern.exec(markup);

	while (match !== null) {
		names.add(match[1].includes('/') ? match[1] : `core/${match[1]}`);
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

		// Register every DesignSetGo block the fixture references.
		//
		// Core blocks are NOT registered, and cannot be from here. Registering
		// them needs @wordpress/block-library, which resolves @wordpress/blocks
		// to its own nested copy — a third registry, distinct from both the
		// top-level copy and the block-editor one this file imports. A block
		// registered there is invisible to the parse() below.
		//
		// The consequence is load-bearing: parse() DROPS an unregistered block
		// rather than returning it as invalid, so a core payload contributes no
		// blocks and cannot fail. `unvalidatedNames` below pins that gap so it
		// stays visible and cannot quietly grow.
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

	// A class attribute built by concatenating a possibly-empty class between
	// two others leaves a DOUBLE space: trim() only strips the ends. WordPress
	// compares class attributes as a set, so the validation below still passes
	// and the malformed markup is persisted on every insert. Two blocks shipped
	// that way.
	//
	// Only double spaces are checked. A single leading or trailing space is
	// allowed, because a save() template can legitimately produce one - card's
	// does, via `dsgo-card__content ${cond ? cls : ''}` - and the serializer's
	// job is to match save(), not to be tidier than it.
	it('emits no double-spaced class attributes', () => {
		const offenders = [];

		Object.entries(fixture).forEach(([label, markup]) => {
			const pattern = /class="([^"]*)"/g;
			let match = pattern.exec(markup);

			while (match !== null) {
				if (/\s\s/.test(match[1])) {
					offenders.push(`${label}: "${match[1]}"`);
				}
				match = pattern.exec(markup);
			}
		});

		expect(offenders).toEqual([]);
	});

	// Exactly which block names this suite cannot speak for.
	//
	// parse() drops a block whose type is not registered, so a payload made
	// only of unregistered blocks parses to [] and sails through the validation
	// below having proved nothing. That is the state every core block is in.
	//
	// Pinning the list keeps the gap honest in two directions. A DesignSetGo
	// block that stops registering — a bad block.json, a renamed directory —
	// currently turns into silent green; here it fails and names itself. And a
	// seventh core block added to the inserter shows up as a new entry, forcing
	// whoever adds it to see that the JS suite will not check it.
	//
	// The core blocks' save() parity is pinned instead by
	// tests/phpunit/block-inserter-core-image-test.php and
	// block-inserter-core-list-quote-test.php, which assert against markup
	// transcribed by hand from wp.blocks.serialize(). That is a weaker guard:
	// it compares against one WordPress release, frozen at the moment it was
	// written, so it cannot notice core changing its save() in an upgrade.
	it('pins which block names go unvalidated', () => {
		const referenced = new Set();
		Object.values(fixture).forEach((markup) =>
			blockNamesIn(markup).forEach((name) => referenced.add(name))
		);

		const unvalidated = [...referenced]
			.filter((name) => !getBlockType(name))
			.sort();

		expect(unvalidated).toEqual([
			'core/heading',
			'core/image',
			'core/list',
			'core/list-item',
			'core/paragraph',
			'core/quote',
		]);

		// And the consequence, stated outright: a payload built only from
		// unregistered blocks parses to nothing, so 'validates every payload'
		// walks an empty list and cannot fail for it.
		expect(parse(fixture['core-list-ordered'])).toEqual([]);
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
