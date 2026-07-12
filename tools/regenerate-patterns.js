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
	parse,
	serialize,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
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
 * Registers a DesignSetGo block by slug, loading block.json/save.js/
 * deprecated.js from src/blocks/{slug}/.
 *
 * @param {string} blockName Full block name, e.g. "designsetgo/icon-button".
 */
function registerDesignSetGoBlock(blockName) {
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
 * Regenerates every occurrence of `blockName` inside a single file's
 * content. Parses+serializes each region in isolation (not the whole file —
 * these are PHP files with surrounding non-block PHP/HTML), so only the
 * matched substrings ever change.
 *
 * @param {string} content   Original file content.
 * @param {string} blockName Full block name.
 * @return {{content: string, changed: number}} New content + change count.
 */
export function regenerateBlockRegions(content, blockName) {
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
				blockName
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
	}
}
