/**
 * Records which DesignSetGo blocks emit markup from save().
 *
 * The Abilities API's PHP serializer skips any block it considers dynamic, on
 * the grounds that a server-rendered block has no save() output to reproduce.
 * That is true of most of them — but a hybrid block has BOTH a render.php and a
 * save.js, and its stored markup still has to carry the wrapper its save()
 * emits. `designsetgo/scroll-slide` was misclassified exactly that way: its
 * children were stored as bare block comments around their content, the
 * `.dsgo-scroll-slide` wrapper never existed, and the frontend found zero
 * panels to initialise.
 *
 * PHP cannot run save() to find out, and the "has a render callback" test it
 * uses instead cannot distinguish the two cases. So this file asks the real
 * block registrations and writes the answer to a fixture;
 * tests/phpunit/abilities-generated-markup-fixture-test.php asserts that every
 * block named in it has a serializer.
 *
 * Regenerate with:
 *
 *   DSGO_UPDATE_FIXTURES=1 npx wp-scripts test-unit-js tests/unit/blocks-with-save-output.test.js
 */
import {
	getBlockType,
	getSaveContent,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import fs from 'fs';
import path from 'path';

import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

const BLOCKS_DIR = path.join(__dirname, '../../src/blocks');
const FIXTURE = path.join(
	__dirname,
	'__fixtures__/blocks-with-save-output.json'
);

const slugs = fs
	.readdirSync(BLOCKS_DIR)
	.filter((slug) => fs.existsSync(path.join(BLOCKS_DIR, slug, 'block.json')));

/**
 * Whether a block's save() contributes markup of its own.
 *
 * Server-rendered blocks save nothing, or save only an inner-blocks
 * passthrough with no wrapper. Either way there is nothing for the PHP
 * serializer to reproduce.
 *
 * @param {string} name Block name.
 * @return {boolean} Whether save() emits markup.
 */
function emitsSaveMarkup(name) {
	const blockType = getBlockType(name);

	if (!blockType) {
		return false;
	}

	let saved;
	try {
		saved = getSaveContent(blockType, {});
	} catch {
		// A save() that throws on default attributes tells us nothing useful
		// here; the markup fixture test covers those blocks directly.
		return false;
	}

	return typeof saved === 'string' && saved.trim() !== '';
}

describe('blocks whose save() emits markup', () => {
	let emitting;

	beforeAll(() => {
		slugs.forEach((slug) => {
			try {
				registerDesignSetGoBlock(`designsetgo/${slug}`);
			} catch {
				// Blocks that cannot register in this environment are simply
				// not classified here.
			}
		});

		emitting = slugs
			.map((slug) => `designsetgo/${slug}`)
			.filter(emitsSaveMarkup)
			.sort();
	});

	it('finds the blocks under test', () => {
		expect(slugs.length).toBeGreaterThan(40);
	});

	it('matches the committed fixture', () => {
		if (process.env.DSGO_UPDATE_FIXTURES) {
			fs.writeFileSync(
				FIXTURE,
				`${JSON.stringify(emitting, null, '\t')}\n`
			);
			return;
		}

		expect(fs.existsSync(FIXTURE)).toBe(true);

		const recorded = JSON.parse(fs.readFileSync(FIXTURE, 'utf8'));

		expect(emitting).toEqual(recorded);
	});

	it('includes the hybrid blocks that regressed', () => {
		// Both have a render.php AND a save.js. The child is the one that was
		// misclassified as purely dynamic.
		expect(emitting).toContain('designsetgo/scroll-slides');
		expect(emitting).toContain('designsetgo/scroll-slide');
	});
});
