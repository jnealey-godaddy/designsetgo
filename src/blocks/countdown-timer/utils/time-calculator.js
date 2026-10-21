/**
 * Internal dependencies
 */
import { resolveTargetTimestamp } from './timezone';

const UNIT_MS = [
	['days', 86400000],
	['hours', 3600000],
	['minutes', 60000],
	['seconds', 1000],
];

/**
 * Calculate the time remaining until a target date/time.
 *
 * `targetDateTime` is normally a timezoneless wall-clock string (no UTC
 * offset). It's interpreted as local time in `timezone` — or, when
 * `timezone` is empty, in `siteTimezone` (the WordPress site timezone,
 * which the timezone picker's "WordPress Default" option promises). A
 * `targetDateTime` that already carries an explicit offset is respected
 * as-is. See `./timezone.js` for the conversion itself.
 *
 * @param {string}      targetDateTime - Datetime string (timezoneless wall-clock,
 *                                     or ISO 8601 with an explicit offset).
 * @param {string}      timezone       - IANA timezone name, or '' to use
 *                                     `siteTimezone`.
 * @param {string|null} siteTimezone   - Resolved WordPress site timezone
 *                                     (IANA name or fixed offset like
 *                                     "+05:30"), used when `timezone` is
 *                                     empty or unusable. Null when unknown,
 *                                     which reads the wall clock in the
 *                                     browser's zone (see resolveTargetTimestamp).
 * @param {Object}      [show]         - Which units are displayed
 *                                     ({days, hours, minutes, seconds}; a
 *                                     missing key means shown). A hidden unit
 *                                     rolls up into the next smaller visible
 *                                     one (3d 5h with Days off is 77 hours).
 * @return {Object} Object containing days, hours, minutes, seconds, and isComplete flag
 */
export function calculateTimeRemaining(
	targetDateTime,
	timezone = '',
	siteTimezone = null,
	show = {}
) {
	if (!targetDateTime) {
		return {
			days: 0,
			hours: 0,
			minutes: 0,
			seconds: 0,
			isComplete: true,
		};
	}

	try {
		// Resolve to the absolute instant every visitor should agree on.
		const targetMs = resolveTargetTimestamp(
			targetDateTime,
			timezone,
			siteTimezone
		);

		if (!Number.isFinite(targetMs)) {
			return {
				days: 0,
				hours: 0,
				minutes: 0,
				seconds: 0,
				isComplete: true,
			};
		}

		// Calculate the difference in milliseconds
		const difference = targetMs - Date.now();

		// Check if countdown is complete
		if (difference <= 0) {
			return {
				days: 0,
				hours: 0,
				minutes: 0,
				seconds: 0,
				isComplete: true,
			};
		}

		// Largest to smallest; a hidden unit's time falls through to the
		// next visible one instead of being dropped.
		const result = { days: 0, hours: 0, minutes: 0, seconds: 0 };
		let remaining = difference;
		UNIT_MS.forEach(([unit, ms]) => {
			if (show?.[unit] === false) {
				return;
			}
			result[unit] = Math.floor(remaining / ms);
			remaining -= result[unit] * ms;
		});
		const { days, hours, minutes, seconds } = result;

		return {
			days,
			hours,
			minutes,
			seconds,
			isComplete: false,
		};
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('Error calculating time remaining:', error);
		return {
			days: 0,
			hours: 0,
			minutes: 0,
			seconds: 0,
			isComplete: true,
		};
	}
}

/**
 * Format a time unit value with leading zero if needed
 *
 * @param {number} value - The time unit value
 * @return {string} Formatted value with leading zero
 */
export function formatTimeUnit(value) {
	return value < 10 ? `0${value}` : `${value}`;
}

/**
 * Get a human-readable timezone offset
 *
 * @param {string} timezone - IANA timezone string
 * @return {string} Timezone offset (e.g., 'UTC-5')
 */
export function getTimezoneOffset(timezone) {
	try {
		const date = new Date();
		const options = {
			timeZone: timezone,
			timeZoneName: 'short',
		};
		const formatter = new Intl.DateTimeFormat('en-US', options);
		const parts = formatter.formatToParts(date);
		const timeZonePart = parts.find((part) => part.type === 'timeZoneName');
		return timeZonePart ? timeZonePart.value : '';
	} catch {
		return '';
	}
}

/**
 * Resolve the WordPress site timezone as seen by the editor, in the same
 * shape the frontend gets from PHP's `wp_timezone()->getName()`: either an
 * IANA name (e.g. "America/New_York") or a fixed offset ("+05:30" /
 * "-05:00") for sites configured with a manual UTC offset instead of a
 * named zone.
 *
 * `wp.date`'s settings are only available in the editor (enqueued via the
 * `wp-date` script). The frontend has no equivalent, which is why view.js
 * instead reads a `data-site-timezone` attribute stamped on at render time
 * (see `includes/features/class-countdown-timer-timezone.php`) — this
 * function exists only to keep the editor's live preview in agreement with
 * that server-resolved value.
 *
 * @return {string} Resolved site timezone, defaulting to 'UTC'.
 */
export function getEditorSiteTimezone() {
	const timezoneSettings = window?.wp?.date?.getSettings?.()?.timezone;

	if (!timezoneSettings) {
		return 'UTC';
	}

	if (timezoneSettings.string) {
		return timezoneSettings.string;
	}

	const offsetHours = Number(timezoneSettings.offset);
	if (!Number.isFinite(offsetHours)) {
		return 'UTC';
	}

	const sign = offsetHours < 0 ? '-' : '+';
	const absHours = Math.abs(offsetHours);
	const hh = Math.floor(absHours);
	const mm = Math.round((absHours - hh) * 60);

	return `${sign}${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
}
