/**
 * Runs `finishBuild()` once per editor load: waits for the block registry
 * to settle, then reads the pending agent build for the current post over
 * REST, assembles it against the site's real registered blocks, applies it
 * (saving drafts, leaving published posts for review), and reports the
 * outcome back. Runs on every top-window post-editor load with a real post
 * (see `./context.js` — never in the Site Editor, widgets editor, or canvas
 * iframe) — `?dsgo-finish=1` (see `finish_url` in `class-build-page.php`)
 * is only a signal for headless automation polling `data-dsgo-finish`, not
 * a gate on this running at all.
 *
 * All `@wordpress/data`/store access lives here rather than in
 * `./finish-build.js` or `./apply.js` — `@wordpress/editor` and
 * `@wordpress/notices` are stubbed in Jest (see `jest.config.js`), so
 * keeping store access out of those files is what keeps them
 * unit-testable with fakes. This file is excluded from coverage
 * accordingly (see `jest.config.js`'s `collectCoverageFrom`).
 */
import apiFetch from '@wordpress/api-fetch';
import { select, dispatch, subscribe } from '@wordpress/data';
import { finishBuild, finishAfterRegistrationTimeout } from './finish-build';
import { isTopWindow, isFinishableContext } from './context';
import { waitForBlockRegistration, watchNextSave } from './apply';

/** Post statuses treated as "live" — never saved over automatically. */
const PUBLISHED_STATUSES = ['publish', 'future', 'private'];

/**
 * @param {number} postId
 * @return {string} The agent-build REST route for this post.
 */
function routeFor(postId) {
	return `/designsetgo/v1/agent-build/${postId}`;
}

/**
 * Builds the real `finishBuild()` deps, bound to a single post id.
 *
 * @param {number} postId
 * @return {Object} See `finish-build.js`'s JSDoc for the shape.
 */
function createDeps(postId) {
	return {
		fetchPending: () => apiFetch({ path: routeFor(postId) }),
		postReport: (body) =>
			apiFetch({ path: routeFor(postId), method: 'POST', data: body }),
		sanitizeMarkup: (body) =>
			apiFetch({
				path: `${routeFor(postId)}/sanitize`,
				method: 'POST',
				data: body,
			}),
		engine: window.designsetgoEngine,
		parse: (markup) => window.wp.blocks.parse(markup),
		validateBlock: (block) => window.wp.blocks.validateBlock(block),
		getEditorBlocks: () => select('core/block-editor').getBlocks(),
		replaceBlocks: (blocks) =>
			dispatch('core/block-editor').resetBlocks(blocks),
		// Present since @wordpress/editor 12 (WP 5.9); this plugin needs 6.7.
		lockAutosave: (lockName) =>
			dispatch('core/editor').lockPostAutosaving(lockName),
		unlockAutosave: (lockName) =>
			dispatch('core/editor').unlockPostAutosaving(lockName),
		savePost: async () => {
			await dispatch('core/editor').savePost();
			return select('core/editor').didPostSaveRequestSucceed();
		},
		isPublished: () =>
			PUBLISHED_STATUSES.includes(
				select('core/editor').getEditedPostAttribute('status')
			),
		notify: (status, message, options) =>
			dispatch('core/notices').createNotice(status, message, options),
		markDocument: (state) => {
			document.documentElement.dataset.dsgoFinish = state;
		},
		onNextSave: (callback) =>
			watchNextSave({
				subscribe,
				isSavingPost: () => select('core/editor').isSavingPost(),
				didPostSaveRequestSucceed: () =>
					select('core/editor').didPostSaveRequestSucceed(),
				isAutosavingPost: () =>
					select('core/editor').isAutosavingPost(),
				isPreviewingPost: () =>
					select('core/editor').isPreviewingPost(),
				onSuccess: callback,
			}),
	};
}

let hasRun = false;

/**
 * Entry point: waits for the block registry to settle, then runs
 * `finishBuild()` exactly once for the current post.
 *
 * @return {Promise<void>}
 */
export async function runFinishOnce() {
	// The canvas iframe loads this bundle too; only the top window may act.
	if (hasRun || !isTopWindow(window)) {
		return;
	}
	hasRun = true;

	const { settled, postId } = await waitForBlockRegistration({
		getBlockTypesLength: () => window.wp.blocks.getBlockTypes().length,
		// Optional chaining: some editors (e.g. widgets) have no core/editor
		// store at all.
		getPostId: () => select('core/editor')?.getCurrentPostId?.(),
	});

	// Site Editor (template id strings, wp_* types), widgets (no post):
	// nothing to finish, and nothing to tell anyone about.
	const finishable = isFinishableContext({
		isTop: true,
		postId,
		postType: select('core/editor')?.getCurrentPostType?.(),
	});
	if (!finishable) {
		return;
	}

	const deps = createDeps(postId);

	if (!settled) {
		await finishAfterRegistrationTimeout(deps);
		return;
	}

	await finishBuild(postId, deps);
}

// Only self-run in a real block-editor context: `window.wp.blocks` and
// `window.wp.data` are both script dependencies of this bundle in
// production, so their absence means this module has been imported
// somewhere that isn't an actual editor page (e.g. a unit test importing
// `../index.js` for the engine's own bootstrap) — never read `window.wp`
// synchronously beyond this guard, mirroring `../index.js`'s own lazy
// `getEngine()` pattern.
if (
	typeof window !== 'undefined' &&
	window.wp &&
	window.wp.blocks &&
	window.wp.data
) {
	runFinishOnce();
}
