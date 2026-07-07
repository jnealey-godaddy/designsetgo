/**
 * SVG Patterns Extension - Inherited-preset resolver
 *
 * Turns a theme.json preset (settings.custom.designsetgo.svgPattern) into a
 * concrete { type, color, opacity, scale }, applying in-plugin fallbacks per
 * field. Shared by the editor preview and the inspector panel.
 *
 * @package
 */

import { INHERIT_FALLBACK, RANGES } from '../constants';
import { PATTERN_IDS } from '../pattern-data';

/**
 * Clamp a numeric preset value into a range, falling back when it isn't a
 * finite number. Mirrors the PHP renderer's `max( min, min( max, value ) )`
 * clamps so editor preview and frontend render never diverge (e.g. a theme
 * opacity of 0 clamps to the range minimum in both places, not to the
 * fallback in one and the minimum in the other).
 *
 * @param {*}      value    Raw preset value.
 * @param {number} min      Range minimum.
 * @param {number} max      Range maximum.
 * @param {number} fallback Value used when `value` isn't a finite number.
 * @return {number} Clamped value or the fallback.
 */
function clampNumber(value, min, max, fallback) {
	if (typeof value !== 'number' || !Number.isFinite(value)) {
		return fallback;
	}
	return Math.max(min, Math.min(max, value));
}

/**
 * @param {?Object} preset Raw theme preset object, or nullish.
 * @return {{type: string, color: string, opacity: number, scale: number}}
 *         Fully-resolved pattern config.
 */
export function resolveInheritedPattern(preset) {
	const p = preset && typeof preset === 'object' ? preset : {};

	// Use the PATTERN_IDS allowlist (own keys), not a truthy PATTERNS[type]
	// bracket lookup — the latter would accept inherited Object.prototype
	// property names like "constructor"/"toString" as valid patterns, which
	// then crash getPatternBackground() when it destructures a missing shape.
	const type =
		typeof p.type === 'string' && PATTERN_IDS.includes(p.type)
			? p.type
			: INHERIT_FALLBACK.type;

	const color =
		typeof p.color === 'string' && p.color !== ''
			? p.color
			: INHERIT_FALLBACK.color;

	const opacity = clampNumber(
		p.opacity,
		RANGES.opacity.min,
		RANGES.opacity.max,
		INHERIT_FALLBACK.opacity
	);

	const scale = clampNumber(
		p.scale,
		RANGES.scale.min,
		RANGES.scale.max,
		INHERIT_FALLBACK.scale
	);

	return { type, color, opacity, scale };
}
