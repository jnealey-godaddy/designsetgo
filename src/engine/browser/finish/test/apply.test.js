/**
 * `describeInvalid()` and `viewDetailsAction()` — the reason text and
 * "View details" notice action `finish-build.js` and `review.js` both build
 * their failure/conflict/review notices from (U5: "Failure and conflict
 * notices give no reason").
 */
import { describeInvalid, viewDetailsAction } from '../apply';

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
