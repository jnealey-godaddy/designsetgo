/**
 * regenerate-patterns — inner-block preservation
 *
 * Regression coverage for a real content-destroying bug: the tool used to
 * register ONLY the block it is regenerating, so any OTHER block nested
 * inside the target (core/heading, core/paragraph, designsetgo/flip-card-face
 * …) was an unregistered type at parse time. WordPress's parser dropped those
 * blocks outright and serialize() wrote back an empty container — silently
 * deleting real authored content from 16 pattern files, with a diff that
 * looked like a clean CSS-constant removal.
 *
 * Two defences, both asserted here:
 *  1. a passthrough handler, so unknown inner blocks round-trip verbatim;
 *  2. assertNoContentLoss(), which throws if any block count changes.
 *
 * registerDesignSetGoBlock() now registers through the engine's full Jest
 * registry (registerForJest()) rather than just the target block, so every
 * real DesignSetGo and core block used below (flip-card-face, icon, heading,
 * paragraph) is genuinely registered, not "unregistered and caught by the
 * passthrough" — the passthrough mechanism they used to exercise no longer
 * applies to them. To keep testing the ACTUAL failure mode the passthrough
 * exists for (a block type the engine registry genuinely does not know
 * about — e.g. a third-party plugin block embedded in a pattern), the
 * fixture below also nests a synthetic, never-registered
 * `acme/unregistered-widget` block. That one block is what proves the
 * passthrough round-trips unknown content and what the "guard has teeth"
 * test drops to prove assertNoContentLoss still fires.
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
//
// `acme/unregistered-widget` is a synthetic block type that is NEVER
// registered anywhere in this file (unlike flip-card-face/icon/heading/
// paragraph, which registerDesignSetGoBlock()'s full registry now registers
// for real) — it stands in for a third-party plugin block embedded in a
// pattern, the one case the passthrough handler still has to cover.
const NESTED_FLIP_CARD = `<!-- wp:designsetgo/flip-card -->
<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-flip dsgo-flip-card--horizontal" style="--dsgo-flip-duration:0.6s;width:100%" data-flip-trigger="hover" data-flip-effect="flip" data-flip-direction="horizontal"><div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face {"side":"front"} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front"><!-- wp:designsetgo/icon {"icon":"chart","iconSize":56} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Real-time Analytics</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Hover to learn more</p>
<!-- /wp:paragraph -->

<!-- wp:acme/unregistered-widget {"x":1} --><div class="acme">kept</div><!-- /wp:acme/unregistered-widget --></div>
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
		// A genuinely unregistered block type — never registered anywhere in
		// this file, unlike the real DesignSetGo/core blocks above — must
		// still round-trip through the passthrough handler.
		expect(content).toContain('wp:acme/unregistered-widget');
	});

	test('nested authored copy survives verbatim', () => {
		const content = regenerateFixture();
		// The exact content that was destroyed in the real incident.
		expect(content).toContain('Real-time Analytics');
		expect(content).toContain('Hover to learn more');
		// The synthetic foreign block's own markup, verbatim.
		expect(content).toContain('<div class="acme">kept</div>');
	});

	test('no block is added or removed', () => {
		const content = regenerateFixture();
		const count = (s) => (s.match(/<!--\s*wp:/g) || []).length;
		expect(count(content)).toBe(count(NESTED_FLIP_CARD));
		// Explicitly: the foreign block's own comment count is unchanged, not
		// just the aggregate — it neither duplicates nor disappears.
		const acmeCount = (s) =>
			(s.match(/<!--\s*wp:acme\/unregistered-widget/g) || []).length;
		expect(acmeCount(content)).toBe(acmeCount(NESTED_FLIP_CARD));
		expect(acmeCount(NESTED_FLIP_CARD)).toBe(1);
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
		// Simulate the failure mode directly: no passthrough handler, so an
		// unregistered type has nowhere to go and the parser drops it.
		// flip-card-face/icon/heading/paragraph are unaffected by this — the
		// full registry (registerDesignSetGoBlock() -> registerForJest())
		// registers those for real, regardless of the passthrough handler —
		// so `acme/unregistered-widget`, which is never registered anywhere
		// in this file, is the only block this setup can actually drop.
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
