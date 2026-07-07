/**
 * SVG Patterns Extension - Inherited-preset resolver
 *
 * Turns a theme.json preset (settings.custom.designsetgo.svgPattern) into a
 * concrete { type, color, opacity, scale }, applying in-plugin fallbacks per
 * field. Shared by the editor preview and the inspector panel.
 *
 * @package
 */

import { INHERIT_FALLBACK } from '../constants';
import { PATTERNS } from '../pattern-data';

/**
 * @param {?Object} preset Raw theme preset object, or nullish.
 * @return {{type: string, color: string, opacity: number, scale: number}}
 *         Fully-resolved pattern config.
 */
export function resolveInheritedPattern(preset) {
	const p = preset && typeof preset === 'object' ? preset : {};

	const type =
		typeof p.type === 'string' && PATTERNS[p.type]
			? p.type
			: INHERIT_FALLBACK.type;

	const color =
		typeof p.color === 'string' && p.color !== ''
			? p.color
			: INHERIT_FALLBACK.color;

	const opacity =
		typeof p.opacity === 'number' ? p.opacity : INHERIT_FALLBACK.opacity;

	const scale =
		typeof p.scale === 'number' ? p.scale : INHERIT_FALLBACK.scale;

	return { type, color, opacity, scale };
}
