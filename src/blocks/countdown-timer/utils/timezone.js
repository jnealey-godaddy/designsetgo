/**
 * Timezone-aware conversion helpers for the Countdown Timer block.
 *
 * `targetDateTime` is normally a "timezoneless" wall-clock string, e.g.
 * `2025-12-31T23:59:59` (this is `@wordpress/components`' DateTimePicker
 * `TIMEZONELESS_FORMAT`: `Y-m-d\TH:i:s`) — it carries no UTC offset of its
 * own. Historically the block treated that string as if `new Date()` could
 * parse it correctly, which means every visitor's *own* browser applied its
 * *own* local offset, so a countdown hit zero at a different real-world
 * moment for every visitor. This module interprets the wall-clock string as
 * local time *in a specific timezone* (the block's `timezone` attribute, or
 * the WordPress site timezone when that attribute is empty) and converts it
 * to the single absolute instant every visitor should agree on.
 *
 * If `targetDateTime` already carries an explicit offset or `Z` suffix
 * (e.g. older/imported content, or content set via block bindings), that
 * offset is respected as-is and no zone interpretation happens.
 */

// Matches an ISO-ish datetime whose time portion ends with `Z` or an
// explicit `+HH:MM` / `-HH:MM` (colon optional) offset.
const EXPLICIT_OFFSET_RE = /T\d{2}:\d{2}:\d{2}(?:\.\d+)?(Z|[+-]\d{2}:?\d{2})$/;

// A fixed UTC offset written as `+HH:MM` / `-HH:MM` (colon optional), the
// shape WordPress's `wp_timezone_string()` / `wp_timezone()->getName()`
// return when the site has no IANA timezone configured (a manual
// "UTC+5:30"-style offset).
const FIXED_OFFSET_RE = /^([+-])(\d{2}):?(\d{2})$/;

// The timezoneless wall-clock shape the DateTimePicker stores.
const WALL_CLOCK_RE =
	/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.\d+)?$/;

/**
 * Whether a datetime string already carries an explicit UTC offset (or `Z`).
 *
 * @param {string} dateTimeString - Candidate datetime string.
 * @return {boolean} True when the string carries its own offset.
 */
export function hasExplicitOffset(dateTimeString) {
	return (
		typeof dateTimeString === 'string' &&
		EXPLICIT_OFFSET_RE.test(dateTimeString)
	);
}

/**
 * Parse a fixed `+HH:MM` / `-HH:MM` offset string into minutes.
 *
 * @param {string} zone - Candidate zone string.
 * @return {number|null} Offset in minutes (east of UTC is positive), or
 *                        null when `zone` isn't a fixed-offset string.
 */
function parseFixedOffsetMinutes(zone) {
	const match = FIXED_OFFSET_RE.exec(zone.trim());
	if (!match) {
		return null;
	}
	const [, sign, hh, mm] = match;
	const minutes = parseInt(hh, 10) * 60 + parseInt(mm, 10);
	return sign === '-' ? -minutes : minutes;
}

/**
 * Whether `zone` is a timezone name Intl recognizes (IANA identifiers, and
 * a few aliases like "UTC").
 *
 * @param {string} zone - Candidate IANA timezone name.
 * @return {boolean} True when Intl accepts the zone.
 */
function isValidIanaZone(zone) {
	try {
		// eslint-disable-next-line no-new
		new Intl.DateTimeFormat('en-US', { timeZone: zone });
		return true;
	} catch (error) {
		return false;
	}
}

/**
 * Get the UTC offset (in minutes, east-positive) a given UTC instant has in
 * an IANA zone. Uses the standard Intl fixed-point technique: format the
 * UTC instant in the target zone, read the wall-clock parts back out, and
 * diff against the original instant.
 *
 * @param {number} utcGuessMs - A UTC instant, in epoch milliseconds.
 * @param {string} zone       - IANA timezone name.
 * @return {number} Offset in minutes.
 */
function getZoneOffsetMinutes(utcGuessMs, zone) {
	const formatter = new Intl.DateTimeFormat('en-US', {
		timeZone: zone,
		hourCycle: 'h23',
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
		hour: '2-digit',
		minute: '2-digit',
		second: '2-digit',
	});
	const lookup = {};
	formatter.formatToParts(new Date(utcGuessMs)).forEach(({ type, value }) => {
		lookup[type] = value;
	});
	// A handful of locales report midnight as hour "24" rather than "00".
	const hour = lookup.hour === '24' ? '00' : lookup.hour;
	const zonedReadAsUtc = Date.UTC(
		parseInt(lookup.year, 10),
		parseInt(lookup.month, 10) - 1,
		parseInt(lookup.day, 10),
		parseInt(hour, 10),
		parseInt(lookup.minute, 10),
		parseInt(lookup.second, 10)
	);
	return (zonedReadAsUtc - utcGuessMs) / 60000;
}

/**
 * Convert a timezoneless wall-clock string to an absolute instant (epoch
 * ms), interpreting it as local time in `zone`.
 *
 * @param {string} wallClockString - Wall-clock datetime, e.g.
 *                                 "2025-12-31T23:59:59".
 * @param {string} zone            - IANA timezone name, or a fixed offset
 *                                 like "+05:30" / "-05:00".
 * @return {number} Epoch milliseconds. `NaN` if `wallClockString` can't be
 *                   parsed at all.
 */
export function wallClockToUtc(wallClockString, zone) {
	const match = WALL_CLOCK_RE.exec(wallClockString);
	if (!match) {
		// Not the expected timezoneless shape (already has an offset, or
		// malformed) — defer to native parsing rather than guess.
		return new Date(wallClockString).getTime();
	}

	const [, yStr, moStr, dStr, hStr, miStr, sStr] = match;
	const y = parseInt(yStr, 10);
	const mo = parseInt(moStr, 10);
	const d = parseInt(dStr, 10);
	const h = parseInt(hStr, 10);
	const mi = parseInt(miStr, 10);
	const s = parseInt(sStr, 10);
	const wallAsUtc = Date.UTC(y, mo - 1, d, h, mi, s);

	const fixedOffsetMinutes = parseFixedOffsetMinutes(zone || '');
	if (fixedOffsetMinutes !== null) {
		return wallAsUtc - fixedOffsetMinutes * 60000;
	}

	if (!zone || !isValidIanaZone(zone)) {
		// Safe fallback: an unrecognized/missing zone still needs a
		// deterministic answer that's the same for every visitor, so treat
		// the wall clock as UTC rather than falling back to browser-local
		// (which would silently reintroduce the original per-visitor bug).
		return wallAsUtc;
	}

	// Fixed-point refinement: near a DST transition the offset at the
	// (wrong) initial guess can differ from the offset that actually
	// applies, so re-derive the offset from each successive guess. Two
	// passes converge for every real-world IANA zone (offsets never shift
	// by more than a couple of hours between guesses this close together).
	let instant = wallAsUtc;
	for (let i = 0; i < 2; i++) {
		const offsetMinutes = getZoneOffsetMinutes(instant, zone);
		instant = wallAsUtc - offsetMinutes * 60000;
	}
	return instant;
}

/**
 * Resolve the absolute target instant (epoch ms) for the countdown.
 *
 * @param {string} targetDateTime - Stored `targetDateTime` attribute value.
 * @param {string} timezone       - Block's `timezone` attribute (IANA name,
 *                                or '' for "use the site timezone").
 * @param {string} siteTimezone   - The resolved WordPress site timezone
 *                                (IANA name or fixed offset), used when
 *                                `timezone` is empty.
 * @return {number} Epoch milliseconds, or `NaN` when `targetDateTime` is
 *                   empty/unparseable.
 */
export function resolveTargetTimestamp(targetDateTime, timezone, siteTimezone) {
	if (!targetDateTime) {
		return NaN;
	}

	if (hasExplicitOffset(targetDateTime)) {
		return new Date(targetDateTime).getTime();
	}

	const zone =
		(timezone && timezone.trim()) ||
		(siteTimezone && siteTimezone.trim()) ||
		'UTC';

	return wallClockToUtc(targetDateTime, zone);
}
