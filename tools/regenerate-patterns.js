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
 * designsetgo/icon) have no save() to `require()`. Their correct serialized
 * form is a bare self-closing comment (`<!-- wp:{block} {...} /-->`) — the
 * real `save: () => null` a plain block registered with no `save` key would
 * get from `@wordpress/blocks` — so registerDesignSetGoBlock() below falls
 * back to that when a block's directory has no save.js, instead of throwing
 * on the missing `require()`.
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
 * part of the target block's own schema. Below, this module imports whichever
 * extensions actually appear on `designsetgo/icon-button` in this repo's
 * patterns (verified via `grep -ohE '"dsgo[A-Za-z]+"'` scoped to that block's
 * comments) BEFORE registering the target block — skipping this would make
 * `getBlockAttributes()` silently drop those extra keys during parse, so
 * `serialize()` would regenerate markup missing data the original author
 * actually had. Same "supports must be complete" failure mode Rule 2 of this
 * task guards against, just for extension attributes instead of block-support
 * attributes. Regenerating a DIFFERENT block later: re-check which extension
 * attributes that block's real pattern occurrences use and add the matching
 * `import '../src/extensions/...'` line(s) below.
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
// Wires up universal editor extensions' `blocks.registerBlockType` /
// `blocks.getSaveContent.extraProps` filters as a side effect of import,
// exactly like the real editor bundle (src/index.js loads these — and only
// these, plus a global CSS import this script can't pull in under Jest —
// before any block registers). Without this, extension attributes present in
// real pattern content (e.g. `dsgoAnimationEnabled`) aren't part of the
// registered schema, so getBlockAttributes() drops them during parse and the
// regenerated markup silently loses them.
//
// NOTE: only block-animations is imported here because it's the only
// extension whose attributes actually appear on `designsetgo/icon-button` in
// this repo's patterns/ (verified via
// `grep -ohE '"dsgo[A-Za-z]+"' patterns/**/*.php` scoped to icon-button block
// comments). Regenerating a DIFFERENT block may need more of src/index.js's
// extension list imported here — check that block's real pattern usage first.
// eslint-disable-next-line import/no-unresolved
import '../src/extensions/block-animations';

const REPO_ROOT = path.resolve(__dirname, '..');

/**
 * Passthrough handler for every block type that is NOT the regeneration target.
 *
 * THIS IS LOAD-BEARING. Only the target block type gets registered below, so a
 * pattern that nests OTHER blocks inside the target — `core/heading` and
 * `core/paragraph` inside a `designsetgo/icon-list-item`, or
 * `designsetgo/flip-card-face` inside a `designsetgo/flip-card` — hits
 * unregistered block types when the region is parsed. Without a fallback,
 * WordPress's parser DROPS those blocks outright and serialize() writes back an
 * empty container: real headings, paragraphs and icons silently deleted from the
 * pattern file, with no error. (That is not hypothetical — it destroyed content
 * in 16 pattern files before this handler existed.)
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
 * Registers a DesignSetGo block by slug, loading block.json/save.js/
 * deprecated.js from src/blocks/{slug}/.
 *
 * @param {string} blockName Full block name, e.g. "designsetgo/icon-button".
 */
export function registerDesignSetGoBlock(blockName) {
	const slug = blockName.replace('designsetgo/', '');
	const blockDir = path.join(REPO_ROOT, 'src/blocks', slug);

	// eslint-disable-next-line global-require, import/no-dynamic-require
	const metadata = require(path.join(blockDir, 'block.json'));

	// Dynamic blocks (render.php) have no save.js — their correct serialized
	// form is a self-closing comment, i.e. `save: () => null`. Static blocks
	// require() their real save.js so the full markup is reproduced.
	const saveJsPath = path.join(blockDir, 'save.js');
	// eslint-disable-next-line global-require, import/no-dynamic-require
	const save = fs.existsSync(saveJsPath)
		? require(saveJsPath).default
		: () => null;

	let deprecated;
	const deprecatedPath = path.join(blockDir, 'deprecated.js');
	if (fs.existsSync(deprecatedPath)) {
		// eslint-disable-next-line global-require, import/no-dynamic-require
		deprecated = require(deprecatedPath).default;
	}

	setCategories([
		{ slug: 'designsetgo', title: 'DesignSetGo' },
		{ slug: 'design', title: 'Design' },
	]);

	registerBlockType(metadata.name, { ...metadata, save, deprecated });
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
	// Must come first: any block nested inside the target that is not the
	// target itself resolves through this handler and round-trips verbatim.
	// Without it the parser silently deletes them. See registerPassthroughHandler.
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
		unregisterBlockType(blockName);
		unregisterBlockType(PASSTHROUGH_BLOCK);
	}
}
