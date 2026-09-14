/**
 * Public entry point for the agent block engine.
 */
import { assemble } from './assemble';
import { validate } from './validate';
import { lint } from './lint/index';

/**
 * Binds `assemble`/`validate`/`lint` to a single `blocksApi`, so callers
 * pass it once instead of on every call. `blocksApi` is an object exposing
 * `createBlock, serialize, parse, getBlockType` — both `@wordpress/blocks`
 * and `window.wp.blocks` satisfy it. `lint()` doesn't itself need
 * `blocksApi` (it's a pure tree walk — see `./lint/index.js`), but lives
 * here too so every engine surface (CLI, editor extension) reaches it the
 * same way it reaches `assemble`/`validate`.
 *
 * @param {Object} blocksApi Object exposing `createBlock, serialize, parse, getBlockType`.
 * @return {{
 *   assemble: (tree: object) => object,
 *   validate: (markup: string) => object,
 *   lint: (tree: object, design?: object) => object[],
 * }} Engine bound to `blocksApi`.
 */
export function createEngine(blocksApi) {
	return {
		assemble: (tree) => assemble(blocksApi, tree),
		validate: (markup) => validate(blocksApi, markup),
		lint: (tree, design) => lint(tree, design),
	};
}
