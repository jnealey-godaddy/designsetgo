/**
 * The autosave lock held while an agent build sits unsaved in the canvas
 * for review. Core autosave on a draft the opener authored calls
 * `wp_update_post()` on the post itself, so without the lock another
 * user's build would be saved under the opener's capabilities ~60 s after
 * it was applied, with nobody clicking Save.
 */
import { finishBuild } from '../finish-build';
import { watchNextSave } from '../apply';
import { AUTOSAVE_LOCK_NAME } from '../review';

const TREE = { version: 1, blocks: [{ name: 'core/paragraph' }] };
const BUILD_ID = 'build-1';

/**
 * @return {Promise<void>} Resolves after queued microtasks have run.
 */
function flushMicrotasks() {
	return new Promise((resolve) => setTimeout(resolve, 0));
}

/**
 * A fake `@wordpress/data` store: records listeners, fires them on demand,
 * and exposes mutable save-state flags for `watchNextSave`.
 *
 * @return {Object} `{ state, fire, onNextSave }`.
 */
function createFakeEditor() {
	const listeners = [];
	const state = { isSaving: false, succeeded: true, isAutosave: false };
	const subscribe = (listener) => {
		listeners.push(listener);
		return () => {
			const index = listeners.indexOf(listener);
			if (index !== -1) {
				listeners.splice(index, 1);
			}
		};
	};
	return {
		state,
		fire: () => [...listeners].forEach((listener) => listener()),
		onNextSave: (callback) =>
			watchNextSave({
				subscribe,
				isSavingPost: () => state.isSaving,
				didPostSaveRequestSucceed: () => state.succeeded,
				isAutosavingPost: () => state.isAutosave,
				onSuccess: callback,
			}),
	};
}

/**
 * @param {Object} overrides Per-test dep overrides.
 * @return {Object} `finishBuild()` deps for another user's draft build, with
 *   a shared `calls` log recording lock/unlock/replace order.
 */
function createDeps(overrides = {}) {
	const calls = [];
	return {
		calls,
		fetchPending: jest.fn().mockResolvedValue({
			pending: true,
			buildId: BUILD_ID,
			conflict: false,
			isSubmitter: false,
			submitterUnfiltered: true,
			tree: TREE,
			mode: 'replace',
		}),
		postReport: jest.fn().mockResolvedValue(undefined),
		engine: {
			assemble: jest.fn().mockReturnValue({
				status: 'valid',
				markup: '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
				invalid: [],
			}),
			lint: jest.fn().mockReturnValue([]),
		},
		parse: jest
			.fn()
			.mockReturnValue([
				{ name: 'core/paragraph', isValid: true, innerBlocks: [] },
			]),
		getEditorBlocks: jest.fn().mockReturnValue([{ name: 'core/heading' }]),
		replaceBlocks: jest.fn(() => calls.push('replaceBlocks')),
		savePost: jest.fn().mockResolvedValue(true),
		isPublished: jest.fn().mockReturnValue(false),
		notify: jest.fn(),
		markDocument: jest.fn(),
		onNextSave: jest.fn(),
		lockAutosave: jest.fn((name) => calls.push(`lock:${name}`)),
		unlockAutosave: jest.fn((name) => calls.push(`unlock:${name}`)),
		...overrides,
	};
}

/**
 * @param {Object} deps Deps passed to `finishBuild()`.
 * @return {Function} The review notice's Discard `onClick`.
 */
function discardAction(deps) {
	const call = deps.notify.mock.calls.find(
		([, , options]) => options?.actions?.length
	);
	return call[2].actions[0].onClick;
}

describe('autosave lock during agent build review', () => {
	test('the lock name is stable', () => {
		expect(AUTOSAVE_LOCK_NAME).toBe('designsetgo-agent-build');
	});

	test('locks autosave before the build is put into the canvas', async () => {
		const deps = createDeps();

		await finishBuild(1, deps);

		expect(deps.calls).toEqual([
			'lock:designsetgo-agent-build',
			'replaceBlocks',
		]);
		expect(deps.unlockAutosave).not.toHaveBeenCalled();
	});

	test('a published post under review is locked too', async () => {
		const deps = createDeps({ isPublished: jest.fn(() => true) });
		deps.fetchPending.mockResolvedValue({
			pending: true,
			buildId: BUILD_ID,
			isSubmitter: true,
			submitterUnfiltered: true,
			tree: TREE,
			mode: 'replace',
		});

		await finishBuild(1, deps);

		expect(deps.calls[0]).toBe('lock:designsetgo-agent-build');
	});

	test('Discard unlocks autosave', async () => {
		const deps = createDeps();

		await finishBuild(1, deps);
		discardAction(deps)();
		await flushMicrotasks();

		expect(deps.unlockAutosave).toHaveBeenCalledWith(
			'designsetgo-agent-build'
		);
		expect(deps.calls).toEqual([
			'lock:designsetgo-agent-build',
			'replaceBlocks',
			'replaceBlocks',
			'unlock:designsetgo-agent-build',
		]);
	});

	test('a successful manual save unlocks autosave', async () => {
		const editor = createFakeEditor();
		const deps = createDeps({ onNextSave: editor.onNextSave });

		await finishBuild(1, deps);

		editor.state.isSaving = true;
		editor.fire();
		editor.state.isSaving = false;
		editor.fire();
		await flushMicrotasks();

		expect(deps.unlockAutosave).toHaveBeenCalledTimes(1);
		expect(deps.unlockAutosave).toHaveBeenCalledWith(
			'designsetgo-agent-build'
		);
	});

	test('an autosave never unlocks autosave', async () => {
		const editor = createFakeEditor();
		const deps = createDeps({ onNextSave: editor.onNextSave });

		await finishBuild(1, deps);

		editor.state.isAutosave = true;
		editor.state.isSaving = true;
		editor.fire();
		editor.state.isSaving = false;
		editor.fire();
		await flushMicrotasks();

		expect(deps.unlockAutosave).not.toHaveBeenCalled();
	});

	test('a failed manual save keeps autosave locked', async () => {
		const editor = createFakeEditor();
		const deps = createDeps({ onNextSave: editor.onNextSave });

		await finishBuild(1, deps);

		editor.state.succeeded = false;
		editor.state.isSaving = true;
		editor.fire();
		editor.state.isSaving = false;
		editor.fire();
		await flushMicrotasks();

		expect(deps.unlockAutosave).not.toHaveBeenCalled();
	});

	test('the submitter auto-save branch never locks autosave', async () => {
		const deps = createDeps();
		deps.fetchPending.mockResolvedValue({
			pending: true,
			buildId: BUILD_ID,
			isSubmitter: true,
			submitterUnfiltered: true,
			tree: TREE,
			mode: 'replace',
		});

		await finishBuild(1, deps);

		expect(deps.savePost).toHaveBeenCalled();
		expect(deps.lockAutosave).not.toHaveBeenCalled();
	});
});
