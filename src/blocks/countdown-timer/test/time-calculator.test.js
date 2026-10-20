import { calculateTimeRemaining } from '../utils/time-calculator';

describe('calculateTimeRemaining hidden-unit roll-up', () => {
	const NOW = Date.UTC(2030, 0, 1, 0, 0, 0);
	// 3d 5h 7m 9s ahead, expressed with an explicit offset so no zone logic.
	const target = '2030-01-04T05:07:09+00:00';

	beforeEach(() => {
		jest.spyOn(Date, 'now').mockReturnValue(NOW);
	});
	afterEach(() => jest.restoreAllMocks());

	it('shows plain mod values with every unit visible', () => {
		const r = calculateTimeRemaining(target, '', null);
		expect([r.days, r.hours, r.minutes, r.seconds]).toEqual([3, 5, 7, 9]);
	});

	it('rolls days into hours when Days is hidden', () => {
		const r = calculateTimeRemaining(target, '', null, { days: false });
		expect([r.hours, r.minutes, r.seconds]).toEqual([77, 7, 9]);
	});

	it('rolls hours into minutes when Hours is hidden', () => {
		const r = calculateTimeRemaining(target, '', null, { hours: false });
		expect([r.days, r.minutes, r.seconds]).toEqual([3, 307, 9]);
	});

	it('rolls everything into seconds when only Seconds is shown', () => {
		const r = calculateTimeRemaining(target, '', null, {
			days: false,
			hours: false,
			minutes: false,
		});
		expect(r.seconds).toBe(3 * 86400 + 5 * 3600 + 7 * 60 + 9);
	});

	it('handles Days and Minutes hidden together', () => {
		const r = calculateTimeRemaining(target, '', null, {
			days: false,
			minutes: false,
		});
		expect([r.hours, r.seconds]).toEqual([77, 7 * 60 + 9]);
	});
});
