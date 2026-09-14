/**
 * Register every block the way the block editor does.
 *
 * Order matters and mirrors a real editor page load:
 * 1. block.json definitions, as PHP bootstraps them — 14 blocks pass only
 *    `metadata.name` to `registerBlockType` and are refused without this.
 * 2. the designsetgo category.
 * 3. core blocks.
 * 4. extensions (attribute + save-prop filters must exist before blocks
 *    register, since `blocks.registerBlockType` filters only run once, at
 *    registration time).
 * 5. each block's real `index.js`.
 *
 * `sources` abstracts away *how* those files are discovered — `fs` under
 * Jest (`sources-fs.js`), `require.context` under webpack
 * (`sources-webpack.js`) — so this module stays plain and testable.
 */
import { withQuietConsole } from '../quiet';

/**
 * Requires every `[name, load]` pair, recording (not throwing) a module that
 * fails to load — an agent-facing engine should still work for the blocks
 * that DID register, rather than dying on the first bad file.
 *
 * @param {[string, () => void][]}              modules  Pairs of a
 *                                                       label (an extension or block directory name) and a thunk that requires
 *                                                       its real registration file.
 * @param {{ file: string, message: string }[]} failures Accumulator;
 *                                                       failures are pushed onto it.
 */
function loadModules(modules, failures) {
	modules.forEach(([file, load]) => {
		withQuietConsole(() => {
			try {
				load();
			} catch (error) {
				failures.push({ file, message: error.message });
			}
		});
	});
}

/**
 * @param {Object} blocksApi @wordpress/blocks (the copy block-editor uses).
 * @param {Object} sources   `{ blockJsons: Object[], extensionModules:
 *                           [name, () => void][], blockModules: [name, () => void][],
 *                           registerCoreBlocks: () => void }`.
 * @return {{ failures: { file: string, message: string }[] }} Registration
 *   files that threw while loading.
 */
export function registerAll(blocksApi, sources) {
	// Registration is one-shot and global (WordPress's block registry is a
	// module-scoped data store). Re-running it against an already-registered
	// designsetgo/section would throw on every subsequent
	// registerBlockType() call, so later test files (or callers) that share
	// this module's cache short-circuit here instead.
	if (blocksApi.getBlockType('designsetgo/section')) {
		return { failures: [] };
	}

	const { blockJsons, extensionModules, blockModules, registerCoreBlocks } =
		sources;

	// Step 1: block.json definitions, as PHP's register_block_type_from_metadata()
	// bootstraps them server-side. Several blocks' index.js calls
	// registerBlockType(metadata.name, { edit, save }) with no attributes/
	// supports of its own, relying entirely on this bootstrap.
	withQuietConsole(() => {
		blocksApi.unstable__bootstrapServerSideBlockDefinitions(
			Object.fromEntries(
				blockJsons.map((metadata) => [metadata.name, metadata])
			)
		);
	});

	// Step 2: the designsetgo category, so registerBlockType() doesn't warn
	// about an unrecognized category for every DSGo block.
	withQuietConsole(() => {
		blocksApi.setCategories([
			...blocksApi.getCategories(),
			{ slug: 'designsetgo', title: 'DesignSetGo' },
		]);
	});

	// Step 3: core blocks, before extensions — extension filters key off
	// `blockType.name`/category, not off core being present, but a real
	// editor page load always has core registered first.
	withQuietConsole(() => {
		registerCoreBlocks();
	});

	const failures = [];

	// Step 4: extensions. Must run before blocks register (step 5) — a
	// `blocks.registerBlockType` filter added after a block has already
	// registered never sees that block.
	loadModules(extensionModules, failures);

	// Step 5: each block's real index.js.
	loadModules(blockModules, failures);

	return { failures };
}
