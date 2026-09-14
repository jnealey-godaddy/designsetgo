/**
 * Exposes the agent block engine in the block editor as
 * `window.designsetgoEngine`, so an in-editor assistant — or the "Agent
 * build" sidebar (see ./panel) — can assemble/lint trees against the
 * site's own registered blocks.
 *
 * The engine is built lazily: `createEngine(window.wp.blocks)` only runs
 * on the first `assemble`/`validate`/`lint` call, not at import time,
 * because blocks continue registering (core, DSGo, third-party) after
 * this script has already loaded.
 */
import { createEngine } from '../index';

// "Agent build" sidebar — reads/writes window.designsetgoEngine below.
import './panel';

let engine = null;

/**
 * @return {{assemble: Function, validate: Function, lint: Function}} The
 *   engine, created against `window.wp.blocks` on first call and reused
 *   after that.
 */
function getEngine() {
	if (!engine) {
		engine = createEngine(window.wp.blocks);
	}
	return engine;
}

if (!window.designsetgoEngine) {
	window.designsetgoEngine = {
		version: 1,
		assemble: (tree) => getEngine().assemble(tree),
		validate: (markup) => getEngine().validate(markup),
		lint: (tree, design) => getEngine().lint(tree, design),
	};
}
