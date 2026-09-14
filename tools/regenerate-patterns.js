/**
 * Regenerate saved block markup inside pattern PHP files.
 *
 * Icon Button (and any other STATIC DesignSetGo block) bakes its full save()
 * output into patterns/ (recursively, one PHP file per pattern) — unlike
 * dynamic blocks, a comment-only find/replace on an attribute name would
 * leave the HTML stale, silently tripping the block's deprecation ("Attempt
 * Recovery") the next time an author inserts that pattern and WordPress
 * tries to validate it.
 *
 * DYNAMIC DesignSetGo blocks (render.php, no save.js — e.g. designsetgo/pill,
 * designsetgo/icon) have no save() at all. Their correct serialized form is
 * a bare self-closing comment (`<!-- wp:{block} {...} /-->`) — the real
 * `save: () => null` a plain block registered with no `save` key gets from
 * `@wordpress/blocks`. registerDesignSetGoBlock() below registers every
 * block through the engine's real registration path (each block's own
 * index.js), so this falls out naturally: a dynamic block's index.js simply
 * never calls `registerBlockType` with a `save` key.
 *
 * This module finds every `<!-- wp:{block} ... -->...<!-- /wp:{block} -->`
 * region for the given block name inside the patterns directory, parses it
 * with the real `@wordpress/blocks` parser (running the block's registered
 * deprecations, exactly like opening the pattern in the editor would), and
 * re-serializes it with the block's CURRENT save() — then splices the
 * regenerated markup back into the original file text. Everything else in
 * the file (PHP glue, translation calls, other blocks, whitespace) is left
 * byte-identical.
 *
 * Why this runs through Jest instead of plain `node`: `@wordpress/block-editor`
 * (needed for `useBlockProps.save()`/`RichText.Content` inside save.js/
 * deprecated.js) references browser globals (`window`, etc.) at module load
 * time, and this repo has no `@babel/register` to transform ESM on the fly —
 * Jest's jsdom test environment + existing babel-jest transform pipeline
 * already solves both problems and is exactly what the project's own
 * src/blocks/*_/test/deprecated.test.js files rely on. There is no
 * `tools/`-shaped script that can `node tools/regenerate-patterns.js` and
 * `require()` a block's save.js directly in this repo, so this module is
 * meant to be invoked from a small, disposable Jest test placed at
 * tests/unit/tools/run-regenerate-<block>-patterns.test.js — call
 * regeneratePatterns() from a single `test()`, run it with `npx jest
 * <that path>`, then delete the runner once the regenerated pattern files
 * are committed. Keep this module itself as the reusable part.
 *
 * Extension attributes: pattern content commonly carries attributes from
 * universal editor extensions (block-animations' `dsgoAnimationEnabled`/
 * `dsgoEntranceAnimation`, visibility's `dsgoVisibility`, etc.) that are NOT
 * part of the target block's own schema. Skipping their registration would
 * make `getBlockAttributes()` silently drop those extra keys during parse, so
 * `serialize()` would regenerate markup missing data the original author
 * actually had — the same "supports must be complete" failure mode Rule 2 of
 * this task guards against, just for extension attributes instead of
 * block-support attributes. This used to require hand-maintaining an
 * `import '../src/extensions/...'` list here, kept in sync with whichever
 * extensions the target block's real pattern occurrences happened to use.
 * That list is now obsolete: registerDesignSetGoBlock() registers through
 * the engine's shared Jest registry (`registerForJest()`), which loads EVERY
 * extension under `src/extensions/` before any block registers — mirroring
 * the real editor's script-load order — so no per-block extension list needs
 * maintaining here at all.
 *
 * Usage (from a disposable Jest test file, sync — no await needed):
 *   import { regeneratePatterns } from '../../tools/regenerate-patterns';
 *   const result = regeneratePatterns({ blockName: 'designsetgo/icon-button' });
 */

import fs from 'fs';
import path from 'path';
import { globSync } from 'glob';
// @wordpress/block-editor ships its own nested copy of @wordpress/blocks;
// useBlockProps.save() (used by save.js/deprecated.js) resolves block
// supports against THAT copy's registry, so registration/parsing must go
// through the same instance — see the project's own Jest deprecation tests
// (src/blocks/*/test/deprecated.test.js) for the identical requirement.
// eslint-disable-next-line import/no-unresolved
import {
	registerBlockType,
	unregisterBlockType,
	setCategories,
	setUnregisteredTypeHandlerName,
	parse,
	serialize,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { createElement, RawHTML } from '@wordpress/element';
import { registerForJest } from '../src/engine/registry/sources-fs';

const REPO_ROOT = path.resolve(__dirname, '..');

/**
 * Passthrough handler for any block type the engine registry does not cover.
 *
 * THIS IS LOAD-BEARING. registerDesignSetGoBlock() now registers every
 * DesignSetGo block, every extension, and every core block (see its doc
 * comment), so a pattern that nests `core/heading`/`core/paragraph` inside a
 * `designsetgo/icon-list-item`, or `designsetgo/flip-card-face` inside a
 * `designsetgo/flip-card`, resolves those normally. This handler remains the
 * fallback for anything the engine registry genuinely does NOT know about —
 * e.g. a third-party plugin's block embedded in a pattern. Without it,
 * WordPress's parser DROPS unregistered blocks outright and serialize()
 * writes back an empty container: real content silently deleted from the
 * pattern file, with no error. (That is not hypothetical — it destroyed
 * content in 16 pattern files before this handler existed, back when only
 * the single target block was registered.)
 *
 * Registering a fallback makes the parser route unknown blocks through
 * createMissingBlockType(), which preserves their full delimited markup —
 * nested children included — in `originalContent`. serialize() then short-
 * circuits for the fallback type (see serializeBlock in @wordpress/blocks) and
 * emits that stored markup verbatim, so inner blocks round-trip byte-for-byte
 * while the TARGET block still re-renders through its real save().
 *
 * @see assertNoContentLoss below, which fails loudly if this ever regresses.
 */
const PASSTHROUGH_BLOCK = 'designsetgo/regenerate-passthrough';

export function registerPassthroughHandler() {
	// Categories must exist before any registerBlockType call, or WordPress warns
	// "registered with an invalid category" — this runs before
	// registerDesignSetGoBlock(), which sets them too (setCategories is idempotent).
	setCategories([
		{ slug: 'designsetgo', title: 'DesignSetGo' },
		{ slug: 'design', title: 'Design' },
	]);

	registerBlockType(PASSTHROUGH_BLOCK, {
		title: 'Regenerate passthrough',
		category: 'designsetgo',
		// EVERY support must be off. `className` in particular: with it on,
		// WordPress's getSaveContent.extraProps filter puts a
		// `wp-block-designsetgo-regenerate-passthrough` class on the save
		// element, and RawHTML renders a real <div> wrapper as soon as it
		// receives any prop (it only emits raw markup when propless). That
		// silently injects a bogus <div> around the preserved inner blocks in
		// every regenerated pattern — see assertNoPassthroughLeak below.
		supports: {
			className: false,
			customClassName: false,
			anchor: false,
			html: false,
			inserter: false,
			reusable: false,
			lock: false,
		},
		attributes: {
			originalName: { type: 'string' },
			originalContent: { type: 'string' },
			originalUndelimitedContent: { type: 'string' },
		},
		save: ({ attributes }) =>
			createElement(RawHTML, null, attributes.originalContent || ''),
	});
	setUnregisteredTypeHandlerName(PASSTHROUGH_BLOCK);
}

/**
 * Counts every block-comment opener, keyed by block name.
 *
 * @param {string} markup Serialized block markup.
 * @return {Object<string, number>} name → occurrences.
 */
function countBlockComments(markup) {
	const counts = {};
	const re = /<!--\s*wp:([a-z0-9-]+\/[a-z0-9-]+|[a-z0-9-]+)/gi;
	let m;
	// eslint-disable-next-line no-cond-assign
	while ((m = re.exec(markup)) !== null) {
		const name = m[1].includes('/') ? m[1] : `core/${m[1]}`;
		counts[name] = (counts[name] || 0) + 1;
	}
	return counts;
}

/**
 * Hard guard: regeneration must only ever rewrite the TARGET block's own
 * markup. It must never add or remove a block. Silent block loss is the single
 * most damaging way this tool can fail — it deletes real authored content from
 * pattern files and looks like a clean diff — so it is an exception, not a
 * warning.
 *
 * @param {string} before   Region markup before regeneration.
 * @param {string} after    Region markup after regeneration.
 * @param {string} filePath File being rewritten (for the error message).
 * @throws {Error} If any block name's occurrence count changed.
 */
function assertNoContentLoss(before, after, filePath) {
	const b = countBlockComments(before);
	const a = countBlockComments(after);
	const names = new Set([...Object.keys(b), ...Object.keys(a)]);

	const drift = [...names]
		.filter((name) => (b[name] || 0) !== (a[name] || 0))
		.map((name) => `${name}: ${b[name] || 0} -> ${a[name] || 0}`);

	if (drift.length > 0) {
		throw new Error(
			`Block count changed while regenerating ${filePath} — refusing to write.\n` +
				`This means inner blocks were dropped (or duplicated) instead of round-tripping.\n` +
				drift.map((d) => `  ${d}`).join('\n')
		);
	}
}

/**
 * Registers every DesignSetGo block, extension, and core block through the
 * engine's shared Jest registry (`registerForJest()` — see
 * `src/engine/registry/sources-fs.js`), the same real `index.js` + extension
 * load path `tests/unit/engine/round-trip.test.js` exercises. Registration
 * is global and idempotent (an already-registered `designsetgo/section`
 * short-circuits), so this is safe to call once per block name, per test, or
 * not at all before `regeneratePatterns()` — the effect is identical either
 * way.
 *
 * `blockName` is no longer used to scope what gets registered — the engine
 * registers everything, not just one block — but the parameter is kept so
 * every existing call site (`registerDesignSetGoBlock('grid')`,
 * `blocksWithDeprecations.forEach(registerDesignSetGoBlock)`, etc.) keeps
 * working unchanged.
 *
 * @param {string} [blockName] Ignored. Retained for call-site compatibility.
 */
// eslint-disable-next-line no-unused-vars
export function registerDesignSetGoBlock(blockName) {
	registerForJest();
}

/**
 * Finds every `<!-- wp:{blockName} ... --> ... <!-- /wp:{blockName} -->`
 * region in a string (handles both the self-closing and paired forms).
 *
 * @param {string} content   File content to search.
 * @param {string} blockName Full block name, e.g. "designsetgo/icon-button".
 * @return {Array<{start: number, end: number, markup: string}>} Matches.
 */
export function findBlockRegions(content, blockName) {
	const escaped = blockName.replace(/[/\\^$.*+?()[\]{}|]/g, '\\$&');
	const openTagRe = new RegExp(
		`<!--\\s*wp:${escaped}(?:\\s+\\{[\\s\\S]*?\\})?\\s*(/)?-->`,
		'g'
	);
	const closeTag = `<!-- /wp:${blockName} -->`;

	const regions = [];
	let match;
	// eslint-disable-next-line no-cond-assign
	while ((match = openTagRe.exec(content)) !== null) {
		const start = match.index;
		const isSelfClosing = !!match[1];

		if (isSelfClosing) {
			regions.push({
				start,
				end: start + match[0].length,
				markup: match[0],
			});
			continue;
		}

		const closeIdx = content.indexOf(closeTag, openTagRe.lastIndex);
		if (closeIdx === -1) {
			throw new Error(
				`Unclosed ${blockName} block starting at offset ${start}`
			);
		}
		const end = closeIdx + closeTag.length;
		regions.push({ start, end, markup: content.slice(start, end) });

		// Resume scanning after this block so nested/adjacent matches of the
		// SAME block name are found correctly.
		openTagRe.lastIndex = end;
	}

	return regions;
}

/**
 * The passthrough block is an internal scaffold. Not one byte of it may reach a
 * pattern file — if its name or class shows up in the output, the handler
 * rendered a wrapper instead of raw markup (see registerPassthroughHandler).
 *
 * @param {string} after    Regenerated region markup.
 * @param {string} filePath File being rewritten (for the error message).
 * @throws {Error} If the passthrough leaked into the output.
 */
function assertNoPassthroughLeak(after, filePath) {
	if (after.includes('regenerate-passthrough')) {
		throw new Error(
			`The passthrough handler leaked into ${filePath} — refusing to write.\n` +
				`It must render inner blocks as raw markup, not wrap them. Check that ALL\n` +
				`supports (className especially) are disabled on ${PASSTHROUGH_BLOCK}.`
		);
	}
}

/**
 * Regenerates every occurrence of `blockName` inside a single file's
 * content. Parses+serializes each region in isolation (not the whole file —
 * these are PHP files with surrounding non-block PHP/HTML), so only the
 * matched substrings ever change.
 *
 * @param {string} content   Original file content.
 * @param {string} blockName Full block name.
 * @param {string} filePath  File being rewritten, used in guard error messages.
 * @return {{content: string, changed: number}} New content + change count.
 */
export function regenerateBlockRegions(
	content,
	blockName,
	filePath = '(inline)'
) {
	const regions = findBlockRegions(content, blockName);
	if (regions.length === 0) {
		return { content, changed: 0 };
	}

	let changed = 0;
	let result = '';
	let cursor = 0;

	for (const region of regions) {
		const [block] = parse(region.markup);
		if (!block || block.name !== blockName) {
			throw new Error(
				`Failed to parse a ${blockName} region: ${region.markup.slice(0, 120)}...`
			);
		}
		const regenerated = serialize(block);

		// Never write a region that gained or lost a block. See
		// assertNoContentLoss — silent inner-block deletion is this tool's
		// worst failure mode and must be fatal, not silent.
		assertNoContentLoss(region.markup, regenerated, filePath);
		// ...and never write one where the passthrough handler leaked its own
		// markup into the output. The block-count check above cannot see this:
		// a stray wrapper <div> adds no block comment.
		assertNoPassthroughLeak(regenerated, filePath);

		result += content.slice(cursor, region.start);
		result += regenerated;
		cursor = region.end;

		if (regenerated !== region.markup) {
			changed += 1;
		}
	}
	result += content.slice(cursor);

	return { content: result, changed };
}

/**
 * Regenerates every occurrence of `blockName` across all files in the
 * patterns directory, writing changed files back to disk.
 *
 * @param {Object}  options
 * @param {string}  options.blockName Full block name to regenerate.
 * @param {boolean} [options.dryRun]  When true, computes changes but does not write files.
 * @return {{filesChanged: string[], regionsChanged: number}} Summary.
 */
export function regeneratePatterns({ blockName, dryRun = false }) {
	// Must come first: any block nested inside the target that the engine
	// registry does not cover resolves through this handler and round-trips
	// verbatim. Without it the parser silently deletes them. See
	// registerPassthroughHandler.
	registerPassthroughHandler();
	registerDesignSetGoBlock(blockName);

	try {
		const files = globSync('patterns/**/*.php', {
			cwd: REPO_ROOT,
			absolute: true,
		});

		const filesChanged = [];
		let regionsChanged = 0;

		for (const file of files) {
			const original = fs.readFileSync(file, 'utf8');
			const { content, changed } = regenerateBlockRegions(
				original,
				blockName,
				path.relative(REPO_ROOT, file)
			);

			if (changed > 0) {
				if (!dryRun) {
					fs.writeFileSync(file, content, 'utf8');
				}
				filesChanged.push(path.relative(REPO_ROOT, file));
				regionsChanged += changed;
			}
		}

		return { filesChanged, regionsChanged };
	} finally {
		// blockName itself is NOT unregistered here: registerDesignSetGoBlock()
		// now registers it (and everything else) through the shared,
		// process-wide engine registry, which is idempotent and guarded on
		// `designsetgo/section` already being registered (see
		// sources-fs.js). Unregistering just the target would desync that
		// guard from reality — a later regeneratePatterns() call for a
		// DIFFERENT block in the same process would see the guard block
		// already satisfied, short-circuit, and never re-register the block
		// this call just tore down. Only the scratch passthrough handler,
		// which is local scaffolding rather than a real registration, gets
		// torn down.
		unregisterBlockType(PASSTHROUGH_BLOCK);
	}
}
