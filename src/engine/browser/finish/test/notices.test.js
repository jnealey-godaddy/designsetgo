/**
 * `describeInvalid()`, `viewDetailsAction()`, `failedMessage()`, and
 * `withIssuesCount()` — the reason text and "View details" notice action
 * `finish-build.js` and `review.js` both build their failure/conflict/
 * review/saved notices from (U5: "Failure and conflict notices give no
 * reason"; U4's "...saved with N issue(s)." dedup, review item 3).
 * `makeOpenSidebar()` — the guarded `openSidebar` dep builder (review item 2).
 */
import {
	describeInvalid,
	viewDetailsAction,
	failedMessage,
	withIssuesCount,
	UNEXPECTED_FAILURE_REASON,
	makeOpenSidebar,
} from '../notices';

describe('describeInvalid()', () => {
	test('empty or missing invalid list yields no reason', () => {
		expect(describeInvalid([])).toBe('');
		expect(describeInvalid()).toBe('');
	});

	test('a single entry returns its trimmed reason', () => {
		expect(
			describeInvalid([{ path: 'blocks[0]', block: 'x', reason: 'Boom' }])
		).toBe('Boom');
	});

	test('a reason longer than 140 characters is trimmed with an ellipsis', () => {
		const reason = 'x'.repeat(200);

		const result = describeInvalid([{ reason }]);

		expect(result).toHaveLength(141); // 140 chars + the ellipsis.
		expect(result.startsWith('x'.repeat(140))).toBe(true);
		expect(result.endsWith('…')).toBe(true);
	});

	test('more than one entry appends how many more there are', () => {
		const result = describeInvalid([
			{ reason: 'First reason' },
			{ reason: 'Second' },
			{ reason: 'Third' },
		]);

		expect(result).toBe('First reason and 2 more');
	});

	test('a missing reason on the first entry never crashes', () => {
		expect(describeInvalid([{ path: 'blocks[0]' }])).toBe('');
	});
});

describe('viewDetailsAction()', () => {
	test('wraps the given callback with a "View details" label', () => {
		const openSidebar = () => {};

		const action = viewDetailsAction(openSidebar);

		expect(action).toEqual({
			label: expect.any(String),
			onClick: openSidebar,
		});
		expect(action.label.toLowerCase()).toContain('view details');
	});
});

describe('failedMessage()', () => {
	test('names the first reason when there is one', () => {
		expect(
			failedMessage([{ path: 'blocks[0]', block: 'x', reason: 'Boom' }])
		).toBe('The agent build could not be applied: Boom');
	});

	test('falls back to the bare sentence when there is no reason', () => {
		expect(failedMessage([])).toBe('The agent build could not be applied.');
	});
});

describe('withIssuesCount()', () => {
	test('returns the base sentence unchanged when there are no findings', () => {
		expect(withIssuesCount('Agent changes saved.', [])).toBe(
			'Agent changes saved.'
		);
	});

	test('replaces the trailing period with "with N issue." for one finding', () => {
		expect(
			withIssuesCount('Agent changes saved.', [{ severity: 'warning' }])
		).toBe('Agent changes saved with 1 issue.');
	});

	test('replaces the trailing period with "with N issues." for several findings', () => {
		expect(
			withIssuesCount('Agent build applied and saved.', [
				{ severity: 'warning' },
				{ severity: 'error' },
				{ severity: 'warning' },
			])
		).toBe('Agent build applied and saved with 3 issues.');
	});
});

describe('UNEXPECTED_FAILURE_REASON', () => {
	test('is a non-empty string', () => {
		expect(typeof UNEXPECTED_FAILURE_REASON).toBe('string');
		expect(UNEXPECTED_FAILURE_REASON.length).toBeGreaterThan(0);
	});
});

describe('makeOpenSidebar()', () => {
	test('dispatches core/interface and calls enableComplementaryArea with the sidebar identifier', () => {
		const enableComplementaryArea = jest.fn();
		const dispatch = jest.fn(() => ({ enableComplementaryArea }));

		makeOpenSidebar(dispatch)();

		expect(dispatch).toHaveBeenCalledWith('core/interface');
		expect(enableComplementaryArea).toHaveBeenCalledWith(
			'core',
			'designsetgo-agent-build/designsetgo-agent-build-sidebar'
		);
	});

	test('does nothing, never throws, when dispatch(core/interface) returns nothing', () => {
		const dispatch = jest.fn(() => undefined);

		expect(() => makeOpenSidebar(dispatch)()).not.toThrow();
	});

	test('does nothing, never throws, when enableComplementaryArea is missing', () => {
		const dispatch = jest.fn(() => ({}));

		expect(() => makeOpenSidebar(dispatch)()).not.toThrow();
	});
});
