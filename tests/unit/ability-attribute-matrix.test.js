/**
 * Every attribute, flipped alone, must still serialize to markup save() accepts.
 *
 * The sibling suite (ability-generated-markup.test.js) proves the PHP mirror
 * matches save() for each block at its DEFAULT attributes, plus a set of
 * hand-authored scenarios. That is coverage per BLOCK, and it cannot see a
 * drift that only appears once an attribute moves off its default - which is
 * the shape of every parity bug PR #565 fixed. Adding an attribute changes
 * save() while the defaults payload stays byte-identical, so nothing goes red.
 *
 * This suite closes that gap. tests/phpunit/abilities-attribute-matrix-fixture-test.php
 * enumerates WP_Block_Type_Registry (which carries extension attributes too,
 * injected via register_block_type_args), flips one attribute at a time to a
 * probe value, serializes each with the PHP mirror, and writes the fixture.
 * This file parses that fixture with the REAL block registrations and asserts
 * the editor would find every block valid.
 *
 * What this does NOT prove, stated because a green tick is otherwise
 * misleading: one attribute at a time is not every combination. An attribute
 * that only affects save() alongside another (a hover colour while hover is
 * off) round-trips without exercising anything. Combinations remain the job of
 * the hand-authored scenarios in the sibling suite.
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

// Every extension that alters save() output, imported for its side effect of
// registering a `blocks.getSaveContent.extraProps` filter.
//
// This is load-bearing, not tidiness. tools/regenerate-patterns imports ONLY
// block-animations, deliberately and with a comment saying so - that was all
// its pattern-regeneration job needed. Without the rest, save() in this test
// cannot emit grid span, mobile order, max width or custom CSS, while the PHP
// mirror does, and 138 payloads across 46 blocks fail for a harness reason
// rather than a real one. A matrix that reports drift the product does not
// have is worse than no matrix: it trains people to ignore it.
import '../../src/extensions/background-video';
import '../../src/extensions/block-animations/editor';
import '../../src/extensions/clickable-group';
import '../../src/extensions/custom-css';
import '../../src/extensions/expanding-background/editor';
import '../../src/extensions/grid-mobile-order';
import '../../src/extensions/grid-span';
import '../../src/extensions/hover-effects';
import '../../src/extensions/interactions/save-props';
import '../../src/extensions/max-width';
import '../../src/extensions/responsive';
import '../../src/extensions/reveal-control';
import '../../src/extensions/sticky-header-controls';
import '../../src/extensions/svg-patterns/attributes';
import '../../src/extensions/svg-patterns/editor';
import '../../src/extensions/text-reveal/editor';
import '../../src/extensions/vertical-scroll-parallax/editor';

const FIXTURE = path.join(
	__dirname,
	'__fixtures__/ability-attribute-matrix.json'
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

describe('Every attribute round-trips through the PHP mirror', () => {
	let fixture;

	beforeAll(() => {
		expect(fs.existsSync(FIXTURE)).toBe(true);
		fixture = JSON.parse(fs.readFileSync(FIXTURE, 'utf8'));

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

	it('covers the registry rather than a handful of blocks', () => {
		// A generator bug that produced an almost-empty matrix would make the
		// assertion below pass. Pin the magnitude, loosely enough that adding
		// blocks and attributes never fails the build.
		expect(Object.keys(fixture).length).toBeGreaterThan(700);
	});

	it('emits no double-spaced class attributes', () => {
		// A class built by concatenating a possibly-empty class between two
		// others leaves a DOUBLE space: trim() only strips the ends. WordPress
		// compares class attributes as a set, so validation still passes and
		// the malformed markup is persisted on every insert.
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
