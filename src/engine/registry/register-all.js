/**
 * Register every block the way the block editor does.
 *
 * Order matters and mirrors a real editor page load:
 * 1. block.json definitions, as PHP bootstraps them — 14 blocks pass only
 *    `metadata.name` to `registerBlockType` and are refused without this.
 * 2. the designsetgo category.
 * 3. extensions (attribute + save-prop filters must exist before ANY block
 *    registers, since `blocks.registerBlockType` filters only run once, at
 *    registration time — including core blocks. On a real editor page,
 *    plugin editor scripts are enqueued on `enqueue_block_editor_assets`
 *    and run before edit-post's `initializeEditor()` calls
 *    `registerCoreBlocks()`, so DesignSetGo's extensions DO apply to core
 *    blocks — e.g. a `core/group` in an agent tree gets `dsgoMaxWidth`,
 *    `dsgoAnimationEnabled`, etc., same as it would in the browser).
 * 4. core blocks.
 * 5. each DesignSetGo block's real `index.js`.
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

	const failures = [];

	// Step 3: extensions. Must run before ANY block registers (steps 4 and
	// 5) — a `blocks.registerBlockType` filter added after a block has
	// already registered never sees that block. This is why core blocks
	// register AFTER extensions here, even though `@wordpress/block-library`
	// is "core": it mirrors the real editor's script load order, not the
	// package's namespace.
	loadModules(extensionModules, failures);

	// Step 4: core blocks, now that every extension's
	// `blocks.registerBlockType` filter is in place to apply to them.
	withQuietConsole(() => {
		registerCoreBlocks();
	});

	// Step 5: each DesignSetGo block's real index.js.
	loadModules(blockModules, failures);

	return { failures };
}
