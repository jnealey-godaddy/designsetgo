/**
 * What holds automatic saves off while an agent build sits unsaved in the
 * canvas for review:
 *
 * - the `core/editor` autosave lock. Core autosave on a draft the opener
 *   authored calls `wp_update_post()` on the post itself, so without it
 *   another user's build would be saved under the opener's capabilities
 *   about a minute after it was applied, with nobody clicking Save;
 * - an `editor.preSavePost` filter that aborts preview and autosave saves.
 *   Preview skips the lock when the page has classic meta boxes
 *   (`forceIsAutosaveable`), and for a draft saves the post itself.
 */
import { finishBuild } from '../finish-build';
import { createFakeEditorStore } from './helpers/fake-editor-store';
import {
	AUTOSAVE_LOCK_NAME,
	PRE_SAVE_HOOK,
	REVIEW_FILTER_NAMESPACE,
	FINISH_NOTICE_ID,
	applyForReview,
} from '../review';

const TREE = { version: 1, blocks: [{ name: 'core/paragraph' }] };
const BUILD_ID = 'build-1';
const LOCK = 'lock:designsetgo-agent-build';
const UNLOCK = 'unlock:designsetgo-agent-build';
const ADD_FILTER =
	'addFilter:editor.preSavePost:designsetgo/agent-build-review';
const REMOVE_FILTER =
	'removeFilter:editor.preSavePost:designsetgo/agent-build-review';

/**
 * @return {Promise<void>} Resolves after queued microtasks have run.
 */
function flushMicrotasks() {
	return new Promise((resolve) => setTimeout(resolve, 0));
}

/**
 * @param {Object} overrides Per-test dep overrides.
 * @return {Object} `finishBuild()` deps for another user's draft build, with
 *   a shared `calls` log recording lock/filter/replace order.
 */
function createDeps(overrides = {}) {
	const calls = [];
	const filters = {};
	return {
		calls,
		filters,
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
		removeNotice: jest.fn(),
		openSidebar: jest.fn(),
		setReport: jest.fn(),
		markDocument: jest.fn(),
		onNextSave: jest.fn(),
		lockAutosave: jest.fn((name) => calls.push(`lock:${name}`)),
		unlockAutosave: jest.fn((name) => calls.push(`unlock:${name}`)),
		addFilter: jest.fn((hook, namespace, callback) => {
			calls.push(`addFilter:${hook}:${namespace}`);
			filters[`${hook}:${namespace}`] = callback;
		}),
		removeFilter: jest.fn((hook, namespace) => {
			calls.push(`removeFilter:${hook}:${namespace}`);
			delete filters[`${hook}:${namespace}`];
		}),
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

describe('automatic saves are held off during agent build review', () => {
	test('the lock, hook and namespace are stable', () => {
		expect(AUTOSAVE_LOCK_NAME).toBe('designsetgo-agent-build');
		expect(PRE_SAVE_HOOK).toBe('editor.preSavePost');
		expect(REVIEW_FILTER_NAMESPACE).toBe('designsetgo/agent-build-review');
	});

	test('locks autosave and adds the pre-save filter before the build is put into the canvas', async () => {
		const deps = createDeps();

		await finishBuild(1, deps);

		expect(deps.calls).toEqual([LOCK, ADD_FILTER, 'replaceBlocks']);
		expect(deps.unlockAutosave).not.toHaveBeenCalled();
		expect(deps.removeFilter).not.toHaveBeenCalled();
	});

	test('the pre-save filter aborts preview and autosave saves and passes manual saves through', async () => {
		const deps = createDeps();

		await finishBuild(1, deps);
		const filter =
			deps.filters[`${PRE_SAVE_HOOK}:${REVIEW_FILTER_NAMESPACE}`];
		const edits = { id: 1, content: 'x' };

		expect(() => filter(edits, { isPreview: true })).toThrow(
			'Save or discard the agent build before previewing.'
		);
		expect(() => filter(edits, { isAutosave: true })).toThrow();
		expect(() =>
			filter(edits, { isAutosave: true, isPreview: true })
		).toThrow();
		expect(filter(edits, {})).toBe(edits);
		expect(filter(edits, undefined)).toBe(edits);
	});

	test('a published post under review is held too', async () => {
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

		expect(deps.calls.slice(0, 2)).toEqual([LOCK, ADD_FILTER]);
	});

	test('Discard restores the blocks, then removes the filter and unlocks', async () => {
		const deps = createDeps();

		await finishBuild(1, deps);
		discardAction(deps)();
		await flushMicrotasks();

		expect(deps.calls).toEqual([
			LOCK,
			ADD_FILTER,
			'replaceBlocks',
			'replaceBlocks',
			REMOVE_FILTER,
			UNLOCK,
		]);
	});

	test('a successful manual save removes the filter and unlocks', async () => {
		const editor = createFakeEditorStore();
		const deps = createDeps({ onNextSave: editor.watch });

		await finishBuild(1, deps);
		editor.save();
		await flushMicrotasks();

		expect(deps.calls.slice(-2)).toEqual([REMOVE_FILTER, UNLOCK]);
		expect(deps.unlockAutosave).toHaveBeenCalledTimes(1);
		expect(deps.removeFilter).toHaveBeenCalledTimes(1);
	});

	test('an autosave or a preview save never removes the filter or unlocks', async () => {
		const editor = createFakeEditorStore();
		const deps = createDeps({ onNextSave: editor.watch });

		await finishBuild(1, deps);
		editor.save({ isAutosave: true });
		editor.save({ isPreview: true });
		await flushMicrotasks();

		expect(deps.unlockAutosave).not.toHaveBeenCalled();
		expect(deps.removeFilter).not.toHaveBeenCalled();
		expect(deps.postReport).not.toHaveBeenCalledWith(
			expect.objectContaining({ status: 'finished' })
		);
	});

	test('a failed manual save keeps both in place', async () => {
		const editor = createFakeEditorStore();
		const deps = createDeps({ onNextSave: editor.watch });

		await finishBuild(1, deps);
		editor.save({}, false);
		await flushMicrotasks();

		expect(deps.unlockAutosave).not.toHaveBeenCalled();
		expect(deps.removeFilter).not.toHaveBeenCalled();
	});

	test('a throw from replaceBlocks after the store updated restores the original blocks and releases both', async () => {
		// resetBlocks updates the store, then a subscriber throws: the build
		// may already be in the canvas.
		const deps = createDeps();
		deps.replaceBlocks.mockImplementationOnce(() => {
			deps.calls.push('replaceBlocks');
			throw new Error('subscriber failed');
		});

		await expect(finishBuild(1, deps)).resolves.toBeUndefined();

		expect(deps.calls).toEqual([
			LOCK,
			ADD_FILTER,
			'replaceBlocks',
			'replaceBlocks',
			REMOVE_FILTER,
			UNLOCK,
		]);
		expect(deps.replaceBlocks).toHaveBeenLastCalledWith([
			{ name: 'core/heading' },
		]);
		expect(deps.markDocument).toHaveBeenCalledWith('failed');
	});

	test('if restoring also throws, both stay in place', async () => {
		const deps = createDeps({
			replaceBlocks: jest.fn(() => {
				throw new Error('reset failed');
			}),
		});

		await expect(finishBuild(1, deps)).resolves.toBeUndefined();

		expect(deps.lockAutosave).toHaveBeenCalled();
		expect(deps.unlockAutosave).not.toHaveBeenCalled();
		expect(deps.removeFilter).not.toHaveBeenCalled();
		expect(deps.markDocument).toHaveBeenCalledWith('failed');
	});

	test('a throw after the build is in the canvas restores the original blocks, then releases both', async () => {
		const deps = createDeps({
			notify: jest.fn((status) => {
				if (status === 'warning') {
					throw new Error('notices unavailable');
				}
			}),
		});

		await expect(finishBuild(1, deps)).resolves.toBeUndefined();

		expect(deps.calls).toEqual([
			LOCK,
			ADD_FILTER,
			'replaceBlocks',
			'replaceBlocks',
			REMOVE_FILTER,
			UNLOCK,
		]);
	});

	test('the submitter auto-save branch holds nothing', async () => {
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
		expect(deps.addFilter).not.toHaveBeenCalled();
	});

	// U1: the review notice must not survive the end of the review.
	describe('the review notice is removed once the review ends (U1)', () => {
		test('Discard removes the review notice and shows a "discarded" success notice', async () => {
			const deps = createDeps();

			await finishBuild(1, deps);
			discardAction(deps)();

			expect(deps.removeNotice).toHaveBeenCalledWith(FINISH_NOTICE_ID);
			expect(deps.notify).toHaveBeenCalledWith(
				'success',
				expect.stringContaining('discarded'),
				expect.anything()
			);
		});

		test('Discard is idempotent: a second invocation restores nothing and reports nothing again', async () => {
			const deps = createDeps();

			await finishBuild(1, deps);
			const onClick = discardAction(deps);
			onClick();
			await flushMicrotasks();
			deps.replaceBlocks.mockClear();
			deps.postReport.mockClear();
			deps.removeNotice.mockClear();

			onClick();
			await flushMicrotasks();

			expect(deps.replaceBlocks).not.toHaveBeenCalled();
			expect(deps.postReport).not.toHaveBeenCalled();
			expect(deps.removeNotice).not.toHaveBeenCalled();
		});

		test('a successful manual save removes the review notice and shows a "saved" success notice', async () => {
			const editor = createFakeEditorStore();
			const deps = createDeps({ onNextSave: editor.watch });

			await finishBuild(1, deps);
			editor.save();
			await flushMicrotasks();

			expect(deps.removeNotice).toHaveBeenCalledWith(FINISH_NOTICE_ID);
			expect(deps.notify).toHaveBeenCalledWith(
				'success',
				expect.stringContaining('saved'),
				expect.objectContaining({ id: FINISH_NOTICE_ID })
			);
		});

		test('the review failure/restore path (armed never set) also removes the review notice and corrects the stored report', async () => {
			const deps = createDeps({
				// onNextSave throws synchronously, after the review notice has
				// already been shown — armed never gets set to true, so the
				// finally block's restore path runs.
				onNextSave: jest.fn(() => {
					throw new Error('onNextSave failed');
				}),
			});

			await expect(finishBuild(1, deps)).resolves.toBeUndefined();

			expect(deps.removeNotice).toHaveBeenCalledWith(FINISH_NOTICE_ID);
			// U1 review fix round 1: the store's `setReport({ status:
			// 'awaiting_review', ... })` call (made before the notice was
			// shown) must not be left standing — the sidebar would otherwise
			// keep showing "Pending agent build — Awaiting review" with old
			// findings while an error notice says the build couldn't be
			// checked. The LAST setReport call is what the store ends up
			// holding, and it must not claim awaiting_review.
			const lastReport =
				deps.setReport.mock.calls[
					deps.setReport.mock.calls.length - 1
				][0];
			expect(lastReport?.status).not.toBe('awaiting_review');
			expect(lastReport?.status).toBe('failed');
		});

		// Isolates applyForReview()'s own restore-path fix from
		// finishBuild()'s outer catch (which also corrects the report once
		// applyForReview() rethrows) — this is the specific fix the review
		// asked for, not just an end-to-end symptom fix.
		test("applyForReview() itself corrects the report before rethrowing, not just finishBuild()'s outer catch", async () => {
			const setReport = jest.fn();
			const removeNotice = jest.fn();

			await expect(
				applyForReview({
					report: jest.fn().mockResolvedValue(undefined),
					replaceBlocks: jest.fn(),
					lockAutosave: jest.fn(),
					unlockAutosave: jest.fn(),
					addFilter: jest.fn(),
					removeFilter: jest.fn(),
					notify: jest.fn(),
					removeNotice,
					openSidebar: jest.fn(),
					setReport,
					onNextSave: jest.fn(() => {
						throw new Error('onNextSave failed');
					}),
					currentBlocks: [{ name: 'core/heading' }],
					nextBlocks: [{ name: 'core/paragraph' }],
					status: 'finished',
					findings: [],
					isSubmitter: true,
				})
			).rejects.toThrow('onNextSave failed');

			expect(removeNotice).toHaveBeenCalledWith(FINISH_NOTICE_ID);
			// The awaiting_review setReport() call happens earlier in the same
			// function, before the notice is shown — this asserts the LAST
			// call (what the store actually ends up holding) is corrected.
			expect(setReport).toHaveBeenLastCalledWith(
				expect.objectContaining({ status: 'failed' })
			);
		});
	});
});
