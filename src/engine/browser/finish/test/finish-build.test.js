/**
 * Covers every numbered branch of `finishBuild()` (see the Task 20 brief)
 * with hand-rolled fakes for its injected deps, plus the two real-editor
 * helpers it leans on from `../apply`: `waitForBlockRegistration` (fake
 * timers) and `watchNextSave` (fake `subscribe`/selectors).
 */
import {
	finishBuild,
	finishAfterRegistrationTimeout,
	FINISH_NOTICE_ID,
	FINISH_REPORT_ERROR_NOTICE_ID,
} from '../finish-build';
import { waitForBlockRegistration, watchNextSave } from '../apply';

const TREE = { version: 1, blocks: [{ name: 'core/paragraph' }] };
const DESIGN_CONTEXT = { colors: [] };
const BUILD_ID = 'build-1';

// Every pending fixture below carries `submitterUnfiltered: true`: these
// scenarios cover the branches after assembly, and the sanitize route that
// runs for any other value has its own suite (`sanitize-build.test.js`).

/**
 * Flushes pending microtasks (a macrotask boundary via a real `setTimeout`
 * clears any queued microtasks ahead of it), for asserting on a
 * fire-and-forget `.catch()` chain that isn't itself awaited by the code
 * under test — e.g. the Discard handler's `postReport()` call.
 *
 * @return {Promise<void>}
 */
function flushMicrotasks() {
	return new Promise((resolve) => setTimeout(resolve, 0));
}

/**
 * @param {Object} overrides Per-test dep overrides.
 * @return {Object} A full `finishBuild()` deps object with jest.fn() fakes.
 */
function createDeps(overrides = {}) {
	return {
		fetchPending: jest.fn().mockResolvedValue({ pending: false }),
		postReport: jest.fn().mockResolvedValue(undefined),
		engine: {
			assemble: jest.fn().mockReturnValue({
				status: 'valid',
				markup: '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
				invalid: [],
			}),
			lint: jest.fn().mockReturnValue([]),
		},
		parse: jest.fn().mockReturnValue([{ name: 'core/paragraph' }]),
		getEditorBlocks: jest.fn().mockReturnValue([]),
		replaceBlocks: jest.fn(),
		lockAutosave: jest.fn(),
		unlockAutosave: jest.fn(),
		savePost: jest.fn().mockResolvedValue(true),
		isPublished: jest.fn().mockReturnValue(false),
		notify: jest.fn(),
		markDocument: jest.fn(),
		onNextSave: jest.fn(),
		...overrides,
	};
}

describe('finishBuild()', () => {
	test('1. not pending marks the document done and does nothing else', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({ pending: false }),
		});

		await finishBuild(1, deps);

		expect(deps.markDocument).toHaveBeenCalledWith('done');
		expect(deps.postReport).not.toHaveBeenCalled();
		expect(deps.replaceBlocks).not.toHaveBeenCalled();
	});

	test('2. a conflict reports conflict, warns, and marks failed', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: true,
				tree: TREE,
				mode: 'replace',
			}),
		});

		await finishBuild(1, deps);

		expect(deps.postReport).toHaveBeenCalledWith({
			status: 'conflict',
			buildId: BUILD_ID,
		});
		expect(deps.notify).toHaveBeenCalledWith(
			'warning',
			expect.any(String),
			expect.objectContaining({ id: FINISH_NOTICE_ID })
		);
		expect(deps.markDocument).toHaveBeenCalledWith('failed');
		expect(deps.replaceBlocks).not.toHaveBeenCalled();
	});

	test('3. an invalid assemble reports failed with invalid + findings, only allowed keys', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
		});
		deps.engine.assemble.mockReturnValue({
			status: 'invalid',
			markup: '',
			invalid: [
				{
					path: '0',
					block: 'core/unknown',
					reason: 'Unknown block type',
					code: 'unknown-block',
					internalDebug: 'drop me',
				},
			],
		});
		deps.engine.lint.mockReturnValue([
			{
				rule: 'no-custom-html',
				severity: 'warning',
				path: '1',
				message: 'Avoid custom HTML',
				suggestion: 'Use a real block',
				internalDebug: 'drop me too',
			},
		]);

		await finishBuild(1, deps);

		expect(deps.engine.lint).toHaveBeenCalledWith(TREE, DESIGN_CONTEXT);
		expect(deps.postReport).toHaveBeenCalledWith({
			buildId: BUILD_ID,
			status: 'failed',
			invalid: [
				{
					path: '0',
					block: 'core/unknown',
					reason: 'Unknown block type',
					code: 'unknown-block',
				},
			],
			findings: [
				{
					rule: 'no-custom-html',
					severity: 'warning',
					path: '1',
					message: 'Avoid custom HTML',
					suggestion: 'Use a real block',
				},
			],
		});
		expect(deps.notify).toHaveBeenCalledWith(
			'error',
			expect.any(String),
			expect.objectContaining({ id: FINISH_NOTICE_ID })
		);
		expect(deps.markDocument).toHaveBeenCalledWith('failed');
		expect(deps.replaceBlocks).not.toHaveBeenCalled();
	});

	test('4. append mode prepends the current blocks to the parsed blocks', async () => {
		const existing = [{ name: 'core/heading' }];
		const parsed = [{ name: 'core/paragraph' }];
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'append',
				designContext: DESIGN_CONTEXT,
			}),
			getEditorBlocks: jest.fn().mockReturnValue(existing),
			parse: jest.fn().mockReturnValue(parsed),
		});

		await finishBuild(1, deps);

		expect(deps.replaceBlocks).toHaveBeenCalledWith([
			...existing,
			...parsed,
		]);
	});

	test('4b. replace mode uses only the parsed blocks', async () => {
		const existing = [{ name: 'core/heading' }];
		const parsed = [{ name: 'core/paragraph' }];
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
			getEditorBlocks: jest.fn().mockReturnValue(existing),
			parse: jest.fn().mockReturnValue(parsed),
		});

		await finishBuild(1, deps);

		expect(deps.replaceBlocks).toHaveBeenCalledWith(parsed);
	});

	test('5. draft save success with no findings reports finished', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
			isPublished: jest.fn().mockReturnValue(false),
			savePost: jest.fn().mockResolvedValue(true),
		});

		await finishBuild(1, deps);

		expect(deps.replaceBlocks).toHaveBeenCalled();
		expect(deps.savePost).toHaveBeenCalled();
		expect(deps.postReport).toHaveBeenCalledWith({
			buildId: BUILD_ID,
			status: 'finished',
			findings: [],
		});
		expect(deps.notify).toHaveBeenCalledWith(
			'success',
			expect.any(String),
			expect.objectContaining({ id: FINISH_NOTICE_ID })
		);
		expect(deps.markDocument).toHaveBeenCalledWith('done');
	});

	test('5. draft save success with findings reports finished_with_findings', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
			isPublished: jest.fn().mockReturnValue(false),
			savePost: jest.fn().mockResolvedValue(true),
		});
		deps.engine.lint.mockReturnValue([
			{
				rule: 'no-custom-html',
				severity: 'warning',
				path: '0',
				message: 'Avoid custom HTML',
			},
		]);

		await finishBuild(1, deps);

		expect(deps.postReport).toHaveBeenCalledWith({
			buildId: BUILD_ID,
			status: 'finished_with_findings',
			findings: [
				{
					rule: 'no-custom-html',
					severity: 'warning',
					path: '0',
					message: 'Avoid custom HTML',
				},
			],
		});
		expect(deps.markDocument).toHaveBeenCalledWith('done');
	});

	test('5b. draft save failure reports failed with a save-failed invalid entry', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
			isPublished: jest.fn().mockReturnValue(false),
			savePost: jest.fn().mockResolvedValue(false),
		});

		await finishBuild(1, deps);

		expect(deps.postReport).toHaveBeenCalledWith({
			buildId: BUILD_ID,
			status: 'failed',
			invalid: [{ path: '', block: '', reason: 'save failed' }],
		});
		expect(deps.notify).toHaveBeenCalledWith(
			'error',
			expect.any(String),
			expect.objectContaining({ id: FINISH_NOTICE_ID })
		);
		expect(deps.markDocument).toHaveBeenCalledWith('failed');
	});

	test('6. a published post is applied for review without saving, and marks done', async () => {
		const original = [{ name: 'core/heading' }];
		const parsed = [{ name: 'core/paragraph' }];
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
			getEditorBlocks: jest.fn().mockReturnValue(original),
			parse: jest.fn().mockReturnValue(parsed),
			isPublished: jest.fn().mockReturnValue(true),
		});

		await finishBuild(1, deps);

		expect(deps.replaceBlocks).toHaveBeenCalledWith(parsed);
		expect(deps.savePost).not.toHaveBeenCalled();
		expect(deps.postReport).toHaveBeenCalledWith({
			buildId: BUILD_ID,
			status: 'awaiting_review',
			findings: [],
		});
		expect(deps.notify).toHaveBeenCalledWith(
			'warning',
			'Review agent changes before updating.',
			expect.objectContaining({
				id: FINISH_NOTICE_ID,
				// Carries the only Discard, so it must never be dismissible.
				isDismissible: false,
				actions: [
					expect.objectContaining({ label: expect.any(String) }),
				],
			})
		);
		expect(deps.onNextSave).toHaveBeenCalledTimes(1);
		expect(deps.markDocument).toHaveBeenCalledWith('done');
	});

	test('6c. a failed awaiting_review report still shows the review notice with Discard, plus a separate report-error notice, and still marks done', async () => {
		const original = [{ name: 'core/heading' }];
		const parsed = [{ name: 'core/paragraph' }];
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
			getEditorBlocks: jest.fn().mockReturnValue(original),
			parse: jest.fn().mockReturnValue(parsed),
			isPublished: jest.fn().mockReturnValue(true),
			postReport: jest
				.fn()
				.mockRejectedValueOnce(new Error('network down')),
		});

		await finishBuild(1, deps);

		// The review notice with its Discard action must still appear even
		// though reporting `awaiting_review` failed.
		expect(deps.notify).toHaveBeenCalledWith(
			'warning',
			expect.any(String),
			expect.objectContaining({
				id: FINISH_NOTICE_ID,
				actions: [
					expect.objectContaining({ label: expect.any(String) }),
				],
			})
		);
		// ...plus a separate, non-blocking notice about the failed report.
		expect(deps.notify).toHaveBeenCalledWith(
			'error',
			expect.any(String),
			expect.objectContaining({ id: FINISH_REPORT_ERROR_NOTICE_ID })
		);
		expect(deps.onNextSave).toHaveBeenCalledTimes(1);
		expect(deps.markDocument).toHaveBeenCalledWith('done');
	});

	test('6a. Discard restores the original blocks and posts discarded', async () => {
		const original = [{ name: 'core/heading' }];
		const parsed = [{ name: 'core/paragraph' }];
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
			getEditorBlocks: jest.fn().mockReturnValue(original),
			parse: jest.fn().mockReturnValue(parsed),
			isPublished: jest.fn().mockReturnValue(true),
		});

		await finishBuild(1, deps);

		const [, , options] = deps.notify.mock.calls[0];
		const discard = options.actions[0];

		discard.onClick();

		expect(deps.replaceBlocks).toHaveBeenLastCalledWith(original);
		expect(deps.postReport).toHaveBeenLastCalledWith({
			buildId: BUILD_ID,
			status: 'discarded',
		});
	});

	test('6a-reject. Discard swallows a rejected postReport and still restores blocks', async () => {
		const original = [{ name: 'core/heading' }];
		const parsed = [{ name: 'core/paragraph' }];
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
			getEditorBlocks: jest.fn().mockReturnValue(original),
			parse: jest.fn().mockReturnValue(parsed),
			isPublished: jest.fn().mockReturnValue(true),
		});

		await finishBuild(1, deps);

		deps.postReport.mockRejectedValueOnce(new Error('network down'));
		const [, , options] = deps.notify.mock.calls[0];
		const discard = options.actions[0];

		expect(() => discard.onClick()).not.toThrow();
		expect(deps.replaceBlocks).toHaveBeenLastCalledWith(original);
		expect(deps.postReport).toHaveBeenLastCalledWith({
			buildId: BUILD_ID,
			status: 'discarded',
		});

		// The rejection above must be swallowed, not surfaced as an
		// unhandled promise rejection — if it weren't, Jest would report it
		// as a separate test failure once the flush below lets it settle.
		await flushMicrotasks();
	});

	test('6b. a later successful save posts finished_with_findings once', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
			isPublished: jest.fn().mockReturnValue(true),
		});
		deps.engine.lint.mockReturnValue([
			{
				rule: 'no-custom-html',
				severity: 'warning',
				path: '0',
				message: 'Avoid custom HTML',
			},
		]);

		await finishBuild(1, deps);

		const onSuccess = deps.onNextSave.mock.calls[0][0];
		deps.postReport.mockClear();

		await onSuccess();

		expect(deps.postReport).toHaveBeenCalledTimes(1);
		expect(deps.postReport).toHaveBeenCalledWith({
			buildId: BUILD_ID,
			status: 'finished_with_findings',
			findings: [
				{
					rule: 'no-custom-html',
					severity: 'warning',
					path: '0',
					message: 'Avoid custom HTML',
				},
			],
		});
	});

	test('6b-reject. a later save postReport rejection is swallowed, not thrown', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
			isPublished: jest.fn().mockReturnValue(true),
		});

		await finishBuild(1, deps);

		const onSuccess = deps.onNextSave.mock.calls[0][0];
		deps.postReport.mockRejectedValueOnce(new Error('network down'));

		await expect(onSuccess()).resolves.toBeUndefined();
	});

	test("7. another user's build on a draft is applied for review, never saved", async () => {
		const original = [{ name: 'core/heading' }];
		const parsed = [{ name: 'core/paragraph' }];
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: false,
				submitter: 42,
				tree: TREE,
				mode: 'replace',
				designContext: DESIGN_CONTEXT,
			}),
			getEditorBlocks: jest.fn().mockReturnValue(original),
			parse: jest.fn().mockReturnValue(parsed),
			isPublished: jest.fn().mockReturnValue(false),
		});

		await finishBuild(1, deps);

		expect(deps.replaceBlocks).toHaveBeenCalledWith(parsed);
		expect(deps.savePost).not.toHaveBeenCalled();
		expect(deps.postReport).toHaveBeenCalledWith(
			expect.objectContaining({
				status: 'awaiting_review',
				buildId: BUILD_ID,
			})
		);
		expect(deps.notify).toHaveBeenCalledWith(
			'warning',
			expect.stringContaining('on behalf of another user'),
			expect.objectContaining({
				id: FINISH_NOTICE_ID,
				isDismissible: false,
				actions: [expect.objectContaining({ label: 'Discard' })],
			})
		);
		expect(deps.onNextSave).toHaveBeenCalledTimes(1);
		expect(deps.markDocument).toHaveBeenCalledWith('done');
	});

	test("7b. another user's build on a published post uses the other-user review wording", async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: false,
				tree: TREE,
				mode: 'replace',
			}),
			isPublished: jest.fn().mockReturnValue(true),
		});

		await finishBuild(1, deps);

		expect(deps.savePost).not.toHaveBeenCalled();
		expect(deps.notify).toHaveBeenCalledWith(
			'warning',
			expect.stringContaining('on behalf of another user'),
			expect.objectContaining({ id: FINISH_NOTICE_ID })
		);
	});

	test('7c. a response without isSubmitter is never auto-saved', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				tree: TREE,
				mode: 'replace',
			}),
			isPublished: jest.fn().mockReturnValue(false),
		});

		await finishBuild(1, deps);

		expect(deps.savePost).not.toHaveBeenCalled();
		expect(deps.postReport).toHaveBeenCalledWith(
			expect.objectContaining({
				status: 'awaiting_review',
				buildId: BUILD_ID,
			})
		);
	});

	test('a REST GET failure marks failed and shows an error notice, never throwing', async () => {
		const deps = createDeps({
			fetchPending: jest
				.fn()
				.mockRejectedValue(new Error('network down')),
		});

		await expect(finishBuild(1, deps)).resolves.toBeUndefined();

		expect(deps.markDocument).toHaveBeenCalledWith('failed');
		expect(deps.notify).toHaveBeenCalledWith(
			'error',
			expect.any(String),
			expect.objectContaining({ id: FINISH_NOTICE_ID })
		);
		expect(deps.replaceBlocks).not.toHaveBeenCalled();
	});

	test('a REST POST failure is caught and never thrown', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({ pending: false }),
			postReport: jest.fn().mockRejectedValue(new Error('network down')),
		});
		// Force a branch that posts a report even though fetchPending says
		// "not pending" would normally skip it — use the conflict branch
		// instead, which always reports.
		deps.fetchPending.mockResolvedValue({
			pending: true,
			submitterUnfiltered: true,
			buildId: BUILD_ID,
			conflict: true,
			tree: TREE,
			mode: 'replace',
		});

		await expect(finishBuild(1, deps)).resolves.toBeUndefined();

		expect(deps.markDocument).toHaveBeenLastCalledWith('failed');
	});

	test('6d. after Discard, a later save reports nothing', async () => {
		const listeners = [];
		const subscribe = (listener) => {
			listeners.push(listener);
			return () => {
				const index = listeners.indexOf(listener);
				if (index !== -1) {
					listeners.splice(index, 1);
				}
			};
		};
		let isSaving = false;
		const fire = () => [...listeners].forEach((listener) => listener());
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				conflict: false,
				isSubmitter: true,
				tree: TREE,
				mode: 'replace',
			}),
			isPublished: jest.fn().mockReturnValue(true),
			onNextSave: (callback) =>
				watchNextSave({
					subscribe,
					isSavingPost: () => isSaving,
					didPostSaveRequestSucceed: () => true,
					isAutosavingPost: () => false,
					onSuccess: callback,
				}),
		});

		await finishBuild(1, deps);

		const [, , options] = deps.notify.mock.calls[0];
		options.actions[0].onClick();
		await flushMicrotasks();
		deps.postReport.mockClear();

		isSaving = true;
		fire();
		isSaving = false;
		fire();
		await flushMicrotasks();

		expect(deps.postReport).not.toHaveBeenCalled();
	});
});

describe('finishAfterRegistrationTimeout()', () => {
	test('with nothing pending, marks done silently', async () => {
		const deps = createDeps();

		await finishAfterRegistrationTimeout(deps);

		expect(deps.markDocument).toHaveBeenCalledWith('done');
		expect(deps.postReport).not.toHaveBeenCalled();
		expect(deps.notify).not.toHaveBeenCalled();
	});

	test('with a build pending, reports failed for that build and shows an error', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockResolvedValue({
				pending: true,
				submitterUnfiltered: true,
				buildId: BUILD_ID,
				tree: TREE,
				mode: 'replace',
			}),
		});

		await finishAfterRegistrationTimeout(deps);

		expect(deps.postReport).toHaveBeenCalledWith({
			status: 'failed',
			buildId: BUILD_ID,
			invalid: [
				{
					path: '',
					block: '',
					reason: 'block registration did not settle',
				},
			],
		});
		expect(deps.notify).toHaveBeenCalledWith(
			'error',
			expect.any(String),
			expect.objectContaining({ id: FINISH_NOTICE_ID })
		);
		expect(deps.markDocument).toHaveBeenCalledWith('failed');
	});

	test('a REST failure marks failed and never throws', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockRejectedValue(new Error('down')),
		});

		await expect(
			finishAfterRegistrationTimeout(deps)
		).resolves.toBeUndefined();

		expect(deps.markDocument).toHaveBeenCalledWith('failed');
	});
});

describe('waitForBlockRegistration()', () => {
	beforeEach(() => {
		jest.useFakeTimers();
	});

	afterEach(() => {
		jest.useRealTimers();
	});

	test('resolves settled once the block count is stable across 3 checks and a post id exists', async () => {
		const lengths = [10, 12, 12, 12, 12];
		const getBlockTypesLength = jest.fn(() => lengths.shift() ?? 12);
		const getPostId = jest.fn().mockReturnValue(7);

		const promise = waitForBlockRegistration({
			getBlockTypesLength,
			getPostId,
			intervalMs: 100,
			stableChecks: 3,
			timeoutMs: 10000,
		});

		// 4 ticks needed: 12 (1st read after 10), 12,12,12 -> 3 consecutive
		// stable reads from the polling loop.
		await jest.advanceTimersByTimeAsync(100 * 4);

		await expect(promise).resolves.toEqual({ settled: true, postId: 7 });
	});

	test('times out and reports not settled when the count never stabilizes', async () => {
		let n = 0;
		const getBlockTypesLength = jest.fn(() => {
			n += 1;
			return n; // always changing
		});
		const getPostId = jest.fn().mockReturnValue(7);

		const promise = waitForBlockRegistration({
			getBlockTypesLength,
			getPostId,
			intervalMs: 100,
			stableChecks: 3,
			timeoutMs: 500,
		});

		await jest.advanceTimersByTimeAsync(600);

		await expect(promise).resolves.toEqual({ settled: false, postId: 7 });
	});

	test('times out when the count stabilizes but no post id is ever available', async () => {
		const getBlockTypesLength = jest.fn().mockReturnValue(12);
		const getPostId = jest.fn().mockReturnValue(null);

		const promise = waitForBlockRegistration({
			getBlockTypesLength,
			getPostId,
			intervalMs: 100,
			stableChecks: 3,
			timeoutMs: 300,
		});

		await jest.advanceTimersByTimeAsync(300);

		await expect(promise).resolves.toEqual({
			settled: false,
			postId: null,
		});
	});
});

describe('watchNextSave()', () => {
	/**
	 * @return {{fire: Function, listeners: Function[]}} A fake `subscribe`
	 *   that records listeners and lets the test fire them manually.
	 */
	function createFakeStore() {
		const listeners = [];
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
			subscribe,
			fire: () => listeners.forEach((listener) => listener()),
		};
	}

	test('fires onSuccess once when a save transitions from saving to a successful, non-autosave finish', () => {
		const { subscribe, fire } = createFakeStore();
		let isSaving = false;
		let succeeded = false;
		let isAutosave = false;
		const onSuccess = jest.fn();

		watchNextSave({
			subscribe,
			isSavingPost: () => isSaving,
			didPostSaveRequestSucceed: () => succeeded,
			isAutosavingPost: () => isAutosave,
			onSuccess,
		});

		isSaving = true;
		fire();
		isSaving = false;
		succeeded = true;
		isAutosave = false;
		fire();

		expect(onSuccess).toHaveBeenCalledTimes(1);

		// A second save/finish cycle must not fire again (one-shot).
		isSaving = true;
		fire();
		isSaving = false;
		fire();

		expect(onSuccess).toHaveBeenCalledTimes(1);
	});

	test('does not fire for an autosave', () => {
		const { subscribe, fire } = createFakeStore();
		let isSaving = false;
		const onSuccess = jest.fn();

		watchNextSave({
			subscribe,
			isSavingPost: () => isSaving,
			didPostSaveRequestSucceed: () => true,
			isAutosavingPost: () => true,
			onSuccess,
		});

		isSaving = true;
		fire();
		isSaving = false;
		fire();

		expect(onSuccess).not.toHaveBeenCalled();
	});

	test('does not fire when the save request failed', () => {
		const { subscribe, fire } = createFakeStore();
		let isSaving = false;
		const onSuccess = jest.fn();

		watchNextSave({
			subscribe,
			isSavingPost: () => isSaving,
			didPostSaveRequestSucceed: () => false,
			isAutosavingPost: () => false,
			onSuccess,
		});

		isSaving = true;
		fire();
		isSaving = false;
		fire();

		expect(onSuccess).not.toHaveBeenCalled();
	});
});
