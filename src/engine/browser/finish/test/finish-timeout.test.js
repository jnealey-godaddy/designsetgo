/**
 * `finishAfterRegistrationTimeout()` — the block-registration-never-settled
 * fallback path (split out of `finish-build.test.js` alongside the
 * `finish-timeout.js` file split). Covers U1's requirement that this path
 * also keeps the Agent build sidebar's stored report honest: `failed` with
 * the timeout reason when a build was pending, cleared when nothing was.
 */
import { finishAfterRegistrationTimeout } from '../finish-timeout';
import { FINISH_NOTICE_ID } from '../review';

const TREE = { version: 1, blocks: [{ name: 'core/paragraph' }] };
const BUILD_ID = 'build-1';
const TIMEOUT_INVALID = [
	{ path: '', block: '', reason: 'block registration did not settle' },
];

/**
 * @param {Object} overrides Per-test dep overrides.
 * @return {Object} `finishAfterRegistrationTimeout()` deps with jest.fn() fakes.
 */
function createDeps(overrides = {}) {
	return {
		fetchPending: jest.fn().mockResolvedValue({ pending: false }),
		postReport: jest.fn().mockResolvedValue(undefined),
		notify: jest.fn(),
		setReport: jest.fn(),
		markDocument: jest.fn(),
		...overrides,
	};
}

describe('finishAfterRegistrationTimeout()', () => {
	test('with nothing pending, marks done, clears the stored report, and reports nothing', async () => {
		const deps = createDeps();

		await finishAfterRegistrationTimeout(deps);

		expect(deps.markDocument).toHaveBeenCalledWith('done');
		expect(deps.postReport).not.toHaveBeenCalled();
		expect(deps.notify).not.toHaveBeenCalled();
		// U1: nothing pending means nothing to show — never leave a report
		// from an earlier build this same page load lingering in the sidebar.
		expect(deps.setReport).toHaveBeenCalledWith(null);
	});

	test('with a build pending, reports failed for that build, stores a matching failed report, and shows an error', async () => {
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
			invalid: TIMEOUT_INVALID,
		});
		expect(deps.notify).toHaveBeenCalledWith(
			'error',
			expect.any(String),
			expect.objectContaining({ id: FINISH_NOTICE_ID })
		);
		expect(deps.markDocument).toHaveBeenCalledWith('failed');
		// U1: the Agent build sidebar must show why, not a stale
		// "awaiting_review" (or nothing at all) from before the timeout.
		expect(deps.setReport).toHaveBeenCalledWith({
			status: 'failed',
			invalid: TIMEOUT_INVALID,
			findings: [],
		});
	});

	test('a REST failure marks failed, stores a failed report, and never throws', async () => {
		const deps = createDeps({
			fetchPending: jest.fn().mockRejectedValue(new Error('down')),
		});

		await expect(
			finishAfterRegistrationTimeout(deps)
		).resolves.toBeUndefined();

		expect(deps.markDocument).toHaveBeenCalledWith('failed');
		expect(deps.setReport).toHaveBeenCalledWith({
			status: 'failed',
			invalid: TIMEOUT_INVALID,
			findings: [],
		});
	});
});
