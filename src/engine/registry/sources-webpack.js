/**
 * Node-CLI-build sources for `registerAll()`.
 *
 * Structurally identical to `sources-fs.js`, but discovers
 * `src/blocks/*` and `src/extensions/*` via `require.context` — a
 * webpack-only macro statically resolved at bundle time — instead of `fs`.
 * `require.context` doesn't exist under Jest, so this module isn't (can't
 * be) exercised by `tests/unit/engine/registry.test.js`; it's exercised by
 * running `npm run build:engine` and then the built CLI (Task 5).
 */
const { registerAll } = require('./register-all');
const { withQuietConsole } = require('../quiet');

/** Matches the directory name out of a require.context key, e.g. `./section/block.json` → `section`. */
const NAME_RE = /^\.\/([^/]+)\//;

/**
 * @param {string} key A require.context key, e.g. `./section/block.json`.
 * @return {string} The leading directory name.
 */
function nameFromKey(key) {
	const match = key.match(NAME_RE);
	return match ? match[1] : key;
}

/**
 * Every `src/blocks/*\/block.json`, keyed by the metadata's own `name` —
 * never the directory name, which doesn't always match.
 *
 * @return {Object[]} Parsed block.json contents.
 */
function getBlockJsons() {
	const context = require.context(
		'../../blocks',
		true,
		/^\.\/[^/]+\/block\.json$/
	);
	return context.keys().map((key) => context(key));
}

/**
 * Thunks that require each `src/extensions/*\/index.js`.
 *
 * @return {[string, () => void][]} Pairs of directory name and a thunk that
 *   requires that file.
 */
function getExtensionModules() {
	const context = require.context(
		'../../extensions',
		true,
		/^\.\/[^/]+\/index\.js$/
	);
	return context.keys().map((key) => [nameFromKey(key), () => context(key)]);
}

/**
 * Thunks that require each `src/blocks/*\/index.js`, naturally skipping
 * subdirectories with none (e.g. `src/blocks/shared`) since
 * `require.context`'s pattern only matches files that exist.
 *
 * @return {[string, () => void][]} Pairs of directory name and a thunk that
 *   requires that file.
 */
function getBlockModules() {
	const context = require.context(
		'../../blocks',
		true,
		/^\.\/[^/]+\/index\.js$/
	);
	return context.keys().map((key) => [nameFromKey(key), () => context(key)]);
}

/**
 * Registers every DesignSetGo block, extension, and core block the way a
 * real editor page load does. Node/webpack counterpart to `sources-fs.js`'s
 * `registerForJest()`.
 *
 * @return {{ file: string, message: string }[]} Registration files that
 *   threw while loading.
 */
function registerForNode() {
	return withQuietConsole(() => {
		// `@wordpress/blocks` resolves through webpack.engine.config.js's
		// externals to the single nested copy @wordpress/block-editor uses.
		const blocksApi = require('@wordpress/blocks');
		const { registerCoreBlocks } = require('@wordpress/block-library');

		const { failures } = registerAll(blocksApi, {
			blockJsons: getBlockJsons(),
			extensionModules: getExtensionModules(),
			blockModules: getBlockModules(),
			registerCoreBlocks,
		});

		return failures;
	});
}

module.exports = { registerForNode };
