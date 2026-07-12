/**
 * regenerate-patterns — inner-block preservation
 *
 * Regression coverage for a real content-destroying bug: the tool registers ONLY
 * the block it is regenerating, so any OTHER block nested inside the target
 * (core/heading, core/paragraph, designsetgo/flip-card-face …) was an
 * unregistered type at parse time. WordPress's parser dropped those blocks
 * outright and serialize() wrote back an empty container — silently deleting
 * real authored content from 16 pattern files, with a diff that looked like a
 * clean CSS-constant removal.
 *
 * Two defences, both asserted here:
 *  1. a passthrough handler, so unknown inner blocks round-trip verbatim;
 *  2. assertNoContentLoss(), which throws if any block count changes.
 */

import {
	regenerateBlockRegions,
	registerPassthroughHandler,
	registerDesignSetGoBlock,
	// eslint-disable-next-line import/no-unresolved
} from '../../../tools/regenerate-patterns';
// eslint-disable-next-line import/no-unresolved
import { unregisterBlockType } from '@wordpress/block-editor/node_modules/@wordpress/blocks';

const FLIP_CARD = 'designsetgo/flip-card';

// A flip card as it is really stored in patterns/: a container whose inner
// blocks are OTHER block types (flip-card-face → icon + heading + paragraph).
// The old markup also carried the `width:100%` constant the refactor removed.
const NESTED_FLIP_CARD = `<!-- wp:designsetgo/flip-card -->
<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-flip dsgo-flip-card--horizontal" style="--dsgo-flip-duration:0.6s;width:100%" data-flip-trigger="hover" data-flip-effect="flip" data-flip-direction="horizontal"><div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face {"side":"front"} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front"><!-- wp:designsetgo/icon {"icon":"chart","iconSize":56} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Real-time Analytics</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Hover to learn more</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face --></div></div>
<!-- /wp:designsetgo/flip-card -->`;

beforeAll(() => {
	registerPassthroughHandler();
	registerDesignSetGoBlock(FLIP_CARD);
});

afterAll(() => {
	unregisterBlockType(FLIP_CARD);
	unregisterBlockType('designsetgo/regenerate-passthrough');
});

/**
 * Regenerate the fixture and absorb the expected console noise.
 *
 * Parsing pre-refactor markup is noisy by design: WordPress warns + errors that
 * the stored HTML doesn't match the CURRENT save(), then the deprecation matches
 * and it informs "Block successfully updated". That sequence IS the migration
 * working. @wordpress/jest-console resets its console spies per test, so this
 * has to run inside each test rather than in a beforeAll.
 *
 * @return {string} Regenerated markup.
 */
function regenerateFixture() {
	const { content } = regenerateBlockRegions(
		NESTED_FLIP_CARD,
		FLIP_CARD,
		'fixture.php'
	);
	// The deprecation matches the stored markup, so WordPress migrates silently
	// and logs "Block successfully updated" at info level. That IS the migration
	// working — no warning/error, because no block failed to resolve.
	expect(console).toHaveInformed();
	return content;
}

describe('regenerateBlockRegions - nested blocks must survive', () => {
	test('the targeted constant IS removed from the container', () => {
		const content = regenerateFixture();
		expect(NESTED_FLIP_CARD).toContain('width:100%');
		expect(content).not.toContain('width:100%');
		// ...but the attribute-driven value stays.
		expect(content).toContain('--dsgo-flip-duration:0.6s');
	});

	test('nested block types are NOT dropped', () => {
		const content = regenerateFixture();
		expect(content).toContain('wp:designsetgo/flip-card-face');
		expect(content).toContain('wp:designsetgo/icon');
		expect(content).toContain('wp:heading');
		expect(content).toContain('wp:paragraph');
	});

	test('nested authored copy survives verbatim', () => {
		const content = regenerateFixture();
		// The exact content that was destroyed in the real incident.
		expect(content).toContain('Real-time Analytics');
		expect(content).toContain('Hover to learn more');
	});

	test('no block is added or removed', () => {
		const content = regenerateFixture();
		const count = (s) => (s.match(/<!--\s*wp:/g) || []).length;
		expect(count(content)).toBe(count(NESTED_FLIP_CARD));
	});

	test('the passthrough scaffold leaves no trace in the output', () => {
		const content = regenerateFixture();
		// RawHTML renders a real <div> wrapper the moment it receives a prop, so
		// a single enabled support (className) silently injects
		// `<div class="wp-block-designsetgo-regenerate-passthrough">` around
		// every preserved inner block. The block-count guard cannot catch that —
		// a wrapper div adds no block comment.
		expect(content).not.toContain('regenerate-passthrough');
		// The inner blocks must sit directly inside the container, exactly as
		// they were authored.
		expect(content).toContain(
			'<div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face'
		);
	});
});

describe('assertNoContentLoss - the guard has teeth', () => {
	test('throws (rather than silently writing) if inner blocks are dropped', () => {
		// Simulate the failure mode directly: no passthrough handler, so the
		// nested types are unregistered and the parser drops them.
		setUnregisteredHandlerToNothing();

		expect(() =>
			regenerateBlockRegions(NESTED_FLIP_CARD, FLIP_CARD, 'fixture.php')
		).toThrow(/Block count changed/);

		// The parse still migrates the container itself before the guard fires.
		expect(console).toHaveInformed();

		// Restore for any later test in this file.
		registerPassthroughHandler();
	});
});

/**
 * Point the unregistered-type handler at a name that isn't registered, which is
 * exactly the state the tool was in before the fix: unknown inner blocks have
 * nowhere to go and the parser discards them.
 */
function setUnregisteredHandlerToNothing() {
	// eslint-disable-next-line global-require, import/no-unresolved
	const {
		setUnregisteredTypeHandlerName,
	} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');
	unregisterBlockType('designsetgo/regenerate-passthrough');
	setUnregisteredTypeHandlerName(undefined);
}
