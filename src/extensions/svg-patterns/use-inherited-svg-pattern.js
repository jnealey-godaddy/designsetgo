/**
 * SVG Patterns Extension - Inherited-preset hook
 *
 * Reads the theme's SVG pattern preset from
 * settings.custom.designsetgo.svgPattern and resolves it (with per-field
 * fallbacks) to a concrete { type, color, opacity, scale }. Shared by the
 * editor preview HOC and the inspector panel so the two never drift.
 *
 * @package
 */

import { useMemo } from '@wordpress/element';
import { useSettings } from '@wordpress/block-editor';
import { resolveInheritedPattern } from './utils/resolve-inherited-pattern';

/**
 * @return {{type: string, color: string, opacity: number, scale: number}}
 *         The fully-resolved inherited pattern preset.
 */
export function useInheritedSvgPattern() {
	// useSettings reads one leaf at a time; pull the four fields we need.
	const [type, color, opacity, scale] = useSettings(
		'custom.designsetgo.svgPattern.type',
		'custom.designsetgo.svgPattern.color',
		'custom.designsetgo.svgPattern.opacity',
		'custom.designsetgo.svgPattern.scale'
	);

	return useMemo(
		() => resolveInheritedPattern({ type, color, opacity, scale }),
		[type, color, opacity, scale]
	);
}
