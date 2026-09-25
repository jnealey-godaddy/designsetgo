/**
 * Countdown Timer Block - Timezone Conversion Unit Tests
 *
 * Tests the shared wall-clock -> absolute-instant conversion used by both
 * view.js (frontend) and edit.js (editor live preview), so a countdown
 * hits zero at the same real-world moment for every visitor regardless of
 * their own browser's timezone.
 *
 * @package
 */

import {
	hasExplicitOffset,
	wallClockToUtc,
	resolveTargetTimestamp,
} from '../../../src/blocks/countdown-timer/utils/timezone';
import {
	calculateTimeRemaining,
	getEditorSiteTimezone,
} from '../../../src/blocks/countdown-timer/utils/time-calculator';

describe('Countdown Timer - hasExplicitOffset', () => {
	test('detects a trailing Z', () => {
		expect(hasExplicitOffset('2025-06-15T10:00:00Z')).toBe(true);
	});

	test('detects a trailing Z with fractional seconds', () => {
		expect(hasExplicitOffset('2025-06-15T10:00:00.123Z')).toBe(true);
	});

	test('detects a colon offset', () => {
		expect(hasExplicitOffset('2025-06-15T10:00:00+02:00')).toBe(true);
	});

	test('detects a no-colon offset', () => {
		expect(hasExplicitOffset('2025-06-15T10:00:00-0500')).toBe(true);
	});

	test('returns false for a timezoneless wall-clock string', () => {
		expect(hasExplicitOffset('2025-06-15T10:00:00')).toBe(false);
	});

	test('returns false for empty/non-string input', () => {
		expect(hasExplicitOffset('')).toBe(false);
		expect(hasExplicitOffset(undefined)).toBe(false);
		expect(hasExplicitOffset(null)).toBe(false);
	});
});

describe('Countdown Timer - wallClockToUtc (IANA zones)', () => {
	test('converts a wall clock in a zone with no DST (Asia/Kolkata, UTC+5:30)', () => {
		const ms = wallClockToUtc('2025-06-15T10:00:00', 'Asia/Kolkata');
		expect(ms).toBe(Date.UTC(2025, 5, 15, 4, 30, 0));
	});

	test('converts a wall clock in UTC itself', () => {
		const ms = wallClockToUtc('2025-06-15T10:00:00', 'UTC');
		expect(ms).toBe(Date.UTC(2025, 5, 15, 10, 0, 0));
	});

	test('handles America/New_York before the spring-forward transition (EST, UTC-5)', () => {
		// 2025-03-09 02:00 local is when US clocks spring forward.
		const ms = wallClockToUtc('2025-03-08T12:00:00', 'America/New_York');
		expect(ms).toBe(Date.UTC(2025, 2, 8, 17, 0, 0));
	});

	test('handles America/New_York after the spring-forward transition (EDT, UTC-4)', () => {
		const ms = wallClockToUtc('2025-03-10T12:00:00', 'America/New_York');
		expect(ms).toBe(Date.UTC(2025, 2, 10, 16, 0, 0));
	});

	test('handles America/New_York before the fall-back transition (EDT, UTC-4)', () => {
		// 2025-11-02 02:00 local is when US clocks fall back.
		const ms = wallClockToUtc('2025-11-01T12:00:00', 'America/New_York');
		expect(ms).toBe(Date.UTC(2025, 10, 1, 16, 0, 0));
	});

	test('handles America/New_York after the fall-back transition (EST, UTC-5)', () => {
		const ms = wallClockToUtc('2025-11-03T12:00:00', 'America/New_York');
		expect(ms).toBe(Date.UTC(2025, 10, 3, 17, 0, 0));
	});

	test('handles Europe/London before the spring-forward transition (GMT, UTC+0)', () => {
		// 2025-03-30 01:00 UTC is when UK clocks spring forward.
		const ms = wallClockToUtc('2025-03-29T12:00:00', 'Europe/London');
		expect(ms).toBe(Date.UTC(2025, 2, 29, 12, 0, 0));
	});

	test('handles Europe/London after the spring-forward transition (BST, UTC+1)', () => {
		const ms = wallClockToUtc('2025-03-31T12:00:00', 'Europe/London');
		expect(ms).toBe(Date.UTC(2025, 2, 31, 11, 0, 0));
	});
});

describe('Countdown Timer - wallClockToUtc (fixed offsets and fallbacks)', () => {
	test('converts using a fixed positive offset string', () => {
		const ms = wallClockToUtc('2025-01-01T00:00:00', '+05:30');
		expect(ms).toBe(Date.UTC(2024, 11, 31, 18, 30, 0));
	});

	test('converts using a fixed negative offset string', () => {
		const ms = wallClockToUtc('2025-01-01T00:00:00', '-05:00');
		expect(ms).toBe(Date.UTC(2025, 0, 1, 5, 0, 0));
	});

	test('converts using a fixed offset string without a colon', () => {
		const ms = wallClockToUtc('2025-01-01T00:00:00', '-0500');
		expect(ms).toBe(Date.UTC(2025, 0, 1, 5, 0, 0));
	});

	test('falls back to treating the wall clock as UTC for an invalid zone', () => {
		const ms = wallClockToUtc('2025-06-15T10:00:00', 'Not/AZone');
		expect(ms).toBe(Date.UTC(2025, 5, 15, 10, 0, 0));
	});

	test('falls back to treating the wall clock as UTC for an empty zone', () => {
		const ms = wallClockToUtc('2025-06-15T10:00:00', '');
		expect(ms).toBe(Date.UTC(2025, 5, 15, 10, 0, 0));
	});
});

describe('Countdown Timer - resolveTargetTimestamp', () => {
	test('respects an explicit Z offset already on targetDateTime, ignoring timezone args', () => {
		const ms = resolveTargetTimestamp(
			'2025-06-15T10:00:00Z',
			'Asia/Kolkata',
			'America/New_York'
		);
		expect(ms).toBe(Date.UTC(2025, 5, 15, 10, 0, 0));
	});

	test('respects an explicit numeric offset already on targetDateTime', () => {
		const ms = resolveTargetTimestamp(
			'2025-06-15T10:00:00+02:00',
			'Asia/Kolkata',
			'America/New_York'
		);
		expect(ms).toBe(Date.UTC(2025, 5, 15, 8, 0, 0));
	});

	test('uses the block timezone attribute when set', () => {
		const ms = resolveTargetTimestamp(
			'2025-06-15T10:00:00',
			'Asia/Kolkata',
			'America/New_York'
		);
		expect(ms).toBe(Date.UTC(2025, 5, 15, 4, 30, 0));
	});

	test('falls back to the site timezone when the block timezone is empty', () => {
		const ms = resolveTargetTimestamp(
			'2025-06-15T10:00:00',
			'',
			'America/Chicago'
		);
		expect(ms).toBe(
			wallClockToUtc('2025-06-15T10:00:00', 'America/Chicago')
		);
	});

	test('falls back to UTC when both timezone and siteTimezone are empty', () => {
		const ms = resolveTargetTimestamp('2025-06-15T10:00:00', '', '');
		expect(ms).toBe(Date.UTC(2025, 5, 15, 10, 0, 0));
	});

	test('returns NaN for an empty targetDateTime', () => {
		expect(Number.isNaN(resolveTargetTimestamp('', 'UTC', 'UTC'))).toBe(
			true
		);
	});
});

describe('Countdown Timer - calculateTimeRemaining (timezone-aware)', () => {
	test('is not complete when the timezone-adjusted target is still in the future', () => {
		// One hour from now, expressed as a wall clock in the site's own
		// timezone (so the conversion has to correctly round-trip back to
		// "now + 1h" for isComplete to be false).
		const siteTimezone = 'America/Los_Angeles';
		const futureUtcMs = Date.now() + 60 * 60 * 1000;
		const parts = new Intl.DateTimeFormat('en-US', {
			timeZone: siteTimezone,
			hourCycle: 'h23',
			year: 'numeric',
			month: '2-digit',
			day: '2-digit',
			hour: '2-digit',
			minute: '2-digit',
			second: '2-digit',
		})
			.formatToParts(new Date(futureUtcMs))
			.reduce((acc, { type, value }) => ({ ...acc, [type]: value }), {});

		const wallClock = `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}:${parts.second}`;

		const result = calculateTimeRemaining(wallClock, '', siteTimezone);

		expect(result.isComplete).toBe(false);
		// Should be ~1 hour remaining (allow small test-execution slack).
		expect(result.days).toBe(0);
		expect(result.hours).toBe(0);
		expect(result.minutes).toBeGreaterThanOrEqual(58);
	});

	test('is complete when the timezone-adjusted target is in the past', () => {
		const result = calculateTimeRemaining(
			'2020-01-01T00:00:00',
			'America/New_York',
			''
		);
		expect(result.isComplete).toBe(true);
	});

	test('is complete for an empty targetDateTime', () => {
		const result = calculateTimeRemaining('', 'UTC', 'UTC');
		expect(result.isComplete).toBe(true);
	});
});

describe('Countdown Timer - getEditorSiteTimezone', () => {
	const originalWp = window.wp;

	afterEach(() => {
		window.wp = originalWp;
	});

	test('returns the IANA string when the site has a named timezone', () => {
		window.wp = {
			date: {
				getSettings: () => ({
					timezone: { string: 'America/New_York', offset: -5 },
				}),
			},
		};
		expect(getEditorSiteTimezone()).toBe('America/New_York');
	});

	test('formats a fixed positive offset when no named timezone is set', () => {
		window.wp = {
			date: {
				getSettings: () => ({ timezone: { string: '', offset: 5.5 } }),
			},
		};
		expect(getEditorSiteTimezone()).toBe('+05:30');
	});

	test('formats a fixed negative offset when no named timezone is set', () => {
		window.wp = {
			date: {
				getSettings: () => ({ timezone: { string: '', offset: -4 } }),
			},
		};
		expect(getEditorSiteTimezone()).toBe('-04:00');
	});

	test('falls back to UTC when wp.date settings are unavailable', () => {
		window.wp = undefined;
		expect(getEditorSiteTimezone()).toBe('UTC');
	});
});
