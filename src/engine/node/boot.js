/**
 * Boots the Node engine: installs the DOM, registers every block, and binds
 * the engine to the resulting `@wordpress/blocks` instance.
 *
 * CommonJS, and `require('./dom')` is the first statement, so the DOM exists
 * before `sources-webpack.js` (or anything it pulls in) ever touches
 * `@wordpress/blocks` / `@wordpress/block-library`.
 */
'use strict';

require('./dom');

const { registerForNode } = require('../registry/sources-webpack');
const { createEngine } = require('../index');

/**
 * Registers every DesignSetGo block/extension/core block, then builds an
 * engine bound to the (now populated) `@wordpress/blocks` registry.
 *
 * `registerForNode()` only catches throws from an individual registration
 * file — a throw from block-tree bootstrap, category registration, or
 * `registerCoreBlocks()` itself propagates out of this function. Callers
 * (the CLI) must treat that as a boot failure, not attempt to run the
 * partially-booted engine.
 *
 * @return {{ engine: { assemble: Function, validate: Function }, failures: { file: string, message: string }[] }}
 *   The bound engine and any per-file registration failures.
 */
function bootEngine() {
	const failures = registerForNode();
	// Same cached module instance `registerForNode()` already required —
	// webpack's require cache guarantees one `@wordpress/blocks` copy.
	const blocksApi = require('@wordpress/blocks');
	const engine = createEngine(blocksApi);

	return { engine, failures };
}

module.exports = { bootEngine };
