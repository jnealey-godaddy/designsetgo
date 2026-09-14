/**
 * Public entry point for the agent block engine.
 */
import { assemble } from './assemble';
import { validate } from './validate';

/**
 * Binds `assemble`/`validate` to a single `blocksApi`, so callers pass it
 * once instead of on every call. `blocksApi` is an object exposing
 * `createBlock, serialize, parse, getBlockType` — both `@wordpress/blocks`
 * and `window.wp.blocks` satisfy it.
 *
 * @param {Object} blocksApi Object exposing `createBlock, serialize, parse, getBlockType`.
 * @return {{ assemble: (tree: object) => object, validate: (markup: string) => object }}
 *         Engine bound to `blocksApi`.
 */
export function createEngine(blocksApi) {
	return {
		assemble: (tree) => assemble(blocksApi, tree),
		validate: (markup) => validate(blocksApi, markup),
	};
}
