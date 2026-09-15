/**
 * Pure helpers `finishBuild()` (and `./index.js`'s real wiring) lean on:
 * turning an assembled tree into the blocks to apply, shaping engine
 * output into the exact REST report keys, waiting for the block registry
 * to settle, and watching for the next successful save. None of these
 * import `@wordpress/data` or any WordPress store — that access stays in
 * `./index.js`, which is what keeps this file (and `finish-build.js`, which
 * calls into it) unit-testable with plain fakes in Jest, where
 * `@wordpress/editor` and `@wordpress/notices` are stubbed.
 */

const DEFAULT_INTERVAL_MS = 100;
const DEFAULT_STABLE_CHECKS = 3;
const DEFAULT_TIMEOUT_MS = 10000;

/**
 * Polls `getBlockTypesLength()` until its value is unchanged across
 * `stableChecks` consecutive reads `intervalMs` apart AND `getPostId()`
 * returns a truthy id, or gives up after `timeoutMs`. Ticks by iteration
 * count (`timeoutMs / intervalMs`), not wall-clock time, so it advances
 * cleanly under Jest fake timers.
 *
 * @param {Object}   options
 * @param {Function} options.getBlockTypesLength `() => number`, e.g. `wp.blocks.getBlockTypes().length`.
 * @param {Function} options.getPostId           `() => number|null|undefined`, e.g. the `core/editor` post id selector.
 * @param {Function} [options.wait]              `(ms) => Promise<void>`, injectable for tests.
 * @param {number}   [options.intervalMs]        Gap between reads.
 * @param {number}   [options.stableChecks]      Consecutive equal reads required.
 * @param {number}   [options.timeoutMs]         Overall cap.
 * @return {Promise<{settled: boolean, postId: (number|null)}>} Whether it settled in time, and the last post id seen.
 */
export async function waitForBlockRegistration({
	getBlockTypesLength,
	getPostId,
	wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms)),
	intervalMs = DEFAULT_INTERVAL_MS,
	stableChecks = DEFAULT_STABLE_CHECKS,
	timeoutMs = DEFAULT_TIMEOUT_MS,
}) {
	const maxTicks = Math.max(1, Math.ceil(timeoutMs / intervalMs));
	let lastLength = getBlockTypesLength();
	let stableCount = 1;

	for (let tick = 0; tick < maxTicks; tick += 1) {
		// eslint-disable-next-line no-await-in-loop
		await wait(intervalMs);

		const length = getBlockTypesLength();
		stableCount = length === lastLength ? stableCount + 1 : 1;
		lastLength = length;

		const postId = getPostId();

		if (stableCount >= stableChecks && postId) {
			return { settled: true, postId };
		}
	}

	return { settled: false, postId: getPostId() || null };
}

/**
 * Registers a one-shot `onSuccess` for the next save that transitions from
 * saving to a finished, successful save that was neither an autosave nor a
 * preview, then unsubscribes. Generic over `subscribe`/the `core/editor`
 * selectors it needs, so it takes no `@wordpress/data` dependency itself.
 *
 * Core's `isAutosavingPost()`/`isPreviewingPost()` are only true while
 * `isSavingPost()` is, so they read false at the very moment a save ends.
 * They are sampled while the save is in flight instead, once per save.
 *
 * @param {Object}   options
 * @param {Function} options.subscribe                 `(listener: Function) => (unsubscribe: Function)`.
 * @param {Function} options.isSavingPost              `() => boolean`.
 * @param {Function} options.didPostSaveRequestSucceed `() => boolean`.
 * @param {Function} options.isAutosavingPost          `() => boolean`.
 * @param {Function} options.isPreviewingPost          `() => boolean`.
 * @param {Function} options.onSuccess                 Called once, after unsubscribing.
 * @return {Function} `unsubscribe`.
 */
export function watchNextSave({
	subscribe,
	isSavingPost,
	didPostSaveRequestSucceed,
	isAutosavingPost,
	isPreviewingPost,
	onSuccess,
}) {
	const isAutomatic = () => isAutosavingPost() || isPreviewingPost();
	let wasSaving = isSavingPost();
	let wasAutomatic = wasSaving && isAutomatic();

	const unsubscribe = subscribe(() => {
		const isSaving = isSavingPost();

		if (isSaving) {
			// A new save starts fresh; an in-flight one stays automatic once
			// it has been seen as automatic.
			wasAutomatic = (wasSaving && wasAutomatic) || isAutomatic();
			wasSaving = true;
			return;
		}

		if (!wasSaving) {
			return;
		}

		const skip = wasAutomatic;
		wasSaving = false;
		wasAutomatic = false;

		if (!skip && didPostSaveRequestSucceed()) {
			unsubscribe();
			onSuccess();
		}
	});

	return unsubscribe;
}

/**
 * Assembles a pending tree into the blocks `finishBuild()` should apply.
 * Always runs `lint()` (findings matter on both the valid and invalid
 * path); only builds `blocks` when `assemble()` reports `valid`.
 *
 * @param {Object}   options
 * @param {Object}   options.engine          `{ assemble, lint }` bound to the site's block registry.
 * @param {Function} options.parse           `wp.blocks.parse`.
 * @param {Object}   options.tree            Pending tree, `{ version, blocks }`.
 * @param {Object}   [options.designContext] Design context for `lint()`.
 * @param {string}   options.mode            `'append'` or `'replace'`.
 * @param {Array}    options.currentBlocks   Blocks currently in the editor (used for append mode).
 * @return {{ok: true, blocks: Array, markup: string, parsedBlocks: Array, findings: Array}|{ok: false, invalid: Array, findings: Array}} Blocks to apply (plus the assembled markup and the blocks parsed from it alone), or why assembly failed.
 */
export function assembleTree({
	engine,
	parse,
	tree,
	designContext,
	mode,
	currentBlocks,
}) {
	const assembled = engine.assemble(tree);
	const findings = mapFindings(engine.lint(tree, designContext));

	if (assembled.status !== 'valid') {
		return { ok: false, invalid: mapInvalid(assembled.invalid), findings };
	}

	const parsedBlocks = parse(assembled.markup);
	const blocks = composeBlocks(mode, currentBlocks, parsedBlocks);

	return {
		ok: true,
		blocks,
		markup: assembled.markup,
		parsedBlocks,
		findings,
	};
}

/**
 * @param {string} mode          `'append'` or `'replace'`.
 * @param {Array}  currentBlocks Blocks currently in the editor.
 * @param {Array}  buildBlocks   Blocks the build contributes.
 * @return {Array} The editor's blocks once the build is applied.
 */
export function composeBlocks(mode, currentBlocks, buildBlocks) {
	return mode === 'append' ? [...currentBlocks, ...buildBlocks] : buildBlocks;
}

/**
 * Picks only the REST-schema-allowed keys from each `invalid` entry:
 * `{ path, block, reason, code? }` (`Report_Schema::invalid_item_schema()`).
 *
 * @param {Array<Object>} [list] Raw engine `invalid` entries.
 * @return {Array<Object>} Entries trimmed to the allowed keys.
 */
export function mapInvalid(list) {
	return (list || []).map((item) => {
		const mapped = {
			path: item.path,
			block: item.block,
			reason: item.reason,
		};
		if (item.code) {
			mapped.code = item.code;
		}
		return mapped;
	});
}

/**
 * Picks only the REST-schema-allowed keys from each `findings` entry:
 * `{ rule, severity, path, message, suggestion? }` (`Report_Schema::findings_item_schema()`).
 *
 * @param {Array<Object>} [list] Raw engine `findings` entries.
 * @return {Array<Object>} Entries trimmed to the allowed keys.
 */
export function mapFindings(list) {
	return (list || []).map((item) => {
		const mapped = {
			rule: item.rule,
			severity: item.severity,
			path: item.path,
			message: item.message,
		};
		if (item.suggestion) {
			mapped.suggestion = item.suggestion;
		}
		return mapped;
	});
}
