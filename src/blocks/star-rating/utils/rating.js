/**
 * Star Rating — value math.
 *
 * Mirrored byte-for-byte in behaviour by `designsetgo_star_rating_*()` in
 * render.php. The editor preview and the frontend must agree on the fill
 * percentage down to the rounding step, or an author sets a half star in the
 * canvas and publishes a whole one.
 *
 * Kept free of WordPress imports so it can be unit-tested directly.
 *
 * @since 2.8.0
 */

/** Smallest and largest number of icons a rating may be drawn over. */
export const MIN_MAX_RATING = 1;
export const MAX_MAX_RATING = 10;

/**
 * Clamp the icon count to something a page can reasonably render.
 *
 * A bound source can hand back anything, including a value that would emit
 * ten thousand SVGs.
 *
 * @param {number} value Requested maximum.
 * @return {number} Integer between MIN_MAX_RATING and MAX_MAX_RATING.
 */
export function clampMaxRating(value) {
	const numeric = Number(value);

	if (!Number.isFinite(numeric)) {
		return 5;
	}

	return Math.min(
		MAX_MAX_RATING,
		Math.max(MIN_MAX_RATING, Math.round(numeric))
	);
}

/**
 * Clamp a rating into the 0..max range.
 *
 * @param {number} value     Raw rating.
 * @param {number} maxRating Maximum, already clamped.
 * @return {number} Rating within range. Non-numeric input reads as 0.
 */
export function clampRating(value, maxRating) {
	const numeric = Number(value);

	if (!Number.isFinite(numeric)) {
		return 0;
	}

	return Math.min(maxRating, Math.max(0, numeric));
}

/**
 * Snap a rating to the configured display precision.
 *
 * Only the drawn stars are snapped — the number shown next to them, and the
 * number handed to structured data, stay exact. Rounding 4.4 up to 4.5 for
 * the icons is a display convention; claiming a 4.5 average in JSON-LD when
 * the source says 4.4 is a false statement.
 *
 * @param {number} value     Clamped rating.
 * @param {string} precision 'exact' | 'half' | 'full'.
 * @return {number} Snapped rating.
 */
export function snapToPrecision(value, precision) {
	if ('full' === precision) {
		return Math.round(value);
	}

	if ('half' === precision) {
		return Math.round(value * 2) / 2;
	}

	return value;
}

/**
 * Width of the filled overlay, as a percentage of the icon row.
 *
 * @param {number} rating    Raw rating.
 * @param {number} maxRating Raw maximum.
 * @param {string} precision Display precision.
 * @return {number} 0–100, rounded to four decimals.
 */
export function getFillPercent(rating, maxRating, precision) {
	const max = clampMaxRating(maxRating);
	const snapped = snapToPrecision(clampRating(rating, max), precision);

	return Math.round((snapped / max) * 100 * 10000) / 10000;
}

/**
 * Format a rating for display next to the stars.
 *
 * Whole numbers lose the decimal ("4", not "4.0"); everything else keeps one
 * place, which is as much as a star row can express.
 *
 * @param {number} value Rating.
 * @return {string} Formatted number.
 */
export function formatRatingValue(value) {
	const numeric = Number.isFinite(Number(value)) ? Number(value) : 0;
	const rounded = Math.round(numeric * 10) / 10;

	return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1);
}

/**
 * Apply the author's count template.
 *
 * `str_replace` semantics rather than sprintf's: the template is author input,
 * and a stray `%d` or a lone `%` in "50% recommend" must not throw or eat the
 * rest of the string.
 *
 * @param {string} template Template containing `%s`.
 * @param {number} count    Rating count.
 * @return {string} Rendered text.
 */
export function formatCount(template, count) {
	const numeric = Number.isFinite(Number(count))
		? Math.round(Number(count))
		: 0;
	const text = String(numeric);

	if (!template || !template.includes('%s')) {
		return text;
	}

	return template.split('%s').join(text);
}
