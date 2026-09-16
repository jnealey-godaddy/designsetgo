/**
 * Jest sources for `registerAll()`.
 *
 * CommonJS + `fs`: `require.context` is a webpack-only macro, unavailable
 * under Jest, so this discovers `src/blocks/*` and `src/extensions/*` from
 * the real filesystem instead. `sources-webpack.js` is the structurally
 * identical counterpart for the Node CLI build.
 */
const fs = require('fs');
const path = require('path');
const { registerAll } = require('./register-all');
const { withQuietConsole } = require('../quiet');

const BLOCKS_DIR = path.resolve(__dirname, '../../blocks');
const EXTENSIONS_DIR = path.resolve(__dirname, '../../extensions');

/**
 * @param {string} dir A directory of `<name>/<file>` subdirectories.
 * @return {string[]} Immediate subdirectory names, e.g. `['section', 'row']`.
 */
function subdirectories(dir) {
	return fs
		.readdirSync(dir, { withFileTypes: true })
		.filter((entry) => entry.isDirectory())
		.map((entry) => entry.name);
}

/**
 * Reads every `src/blocks/*\/block.json`, keyed by the metadata's own
 * `name` — never the directory name, which doesn't always match (e.g.
 * `src/blocks/form-textarea-field/` registers `designsetgo/form-textarea`).
 *
 * @return {Object[]} Parsed block.json contents.
 */
function readBlockJsons() {
	return subdirectories(BLOCKS_DIR)
		.map((name) => path.join(BLOCKS_DIR, name, 'block.json'))
		.filter((file) => fs.existsSync(file))
		.map((file) => JSON.parse(fs.readFileSync(file, 'utf8')));
}

/**
 * Thunks that `require()` each `<dir>/<name>/index.js`, skipping
 * subdirectories with no `index.js` (e.g. `src/blocks/shared`).
 *
 * @param {string} dir A directory of `<name>/index.js` registration files.
 * @return {[string, () => void][]} Pairs of directory name and a thunk that
 *   requires that file.
 */
function readModules(dir) {
	return subdirectories(dir)
		.map((name) => ({ name, indexPath: path.join(dir, name, 'index.js') }))
		.filter(({ indexPath }) => fs.existsSync(indexPath))
		.map(({ name, indexPath }) => [name, () => require(indexPath)]);
}

/**
 * Registers every DesignSetGo block, extension, and core block the way a
 * real editor page load does, against the `@wordpress/blocks` copy nested
 * under `@wordpress/block-editor` — the same instance its
 * `useBlockProps.save()` talks to (see
 * `tests/unit/deprecations-isEligible.test.js` for why that copy, not the
 * top-level package, is required here).
 *
 * @return {{ file: string, message: string }[]} Registration files that
 *   threw while loading.
 */
function registerForJest() {
	// `require('@wordpress/block-library')` alone (module evaluation, before
	// registerCoreBlocks() is ever called) is enough to register WordPress's
	// data stores against the TOP-LEVEL @wordpress/blocks copy, which logs
	// "already registered" once the nested copy above has registered the
	// same store names first. Muted with everything else these requires and
	// registerAll() touch.
	return withQuietConsole(() => {
		const blocksApi = require('@wordpress/block-editor/node_modules/@wordpress/blocks');
		const { registerCoreBlocks } = require('@wordpress/block-library');

		const { failures } = registerAll(blocksApi, {
			blockJsons: readBlockJsons(),
			extensionModules: readModules(EXTENSIONS_DIR),
			blockModules: readModules(BLOCKS_DIR),
			registerCoreBlocks,
		});

		return failures;
	});
}

module.exports = { registerForJest };
