/**
 * SVG Patterns Extension - Attributes
 *
 * @package
 */

import { addFilter } from '@wordpress/hooks';
import { SUPPORTED_BLOCKS, DEFAULTS } from './constants';
import { shouldExtendBlock } from '../../utils/should-extend-block';

/**
 * Add SVG pattern attributes to supported blocks
 *
 * @param {Object}  settings             Block settings
 * @param {string}  name                 Block name
 * @param {?Object} deprecatedDefinition The deprecation entry being
 *                                       reprocessed, or null on the primary
 *                                       registration pass. WordPress re-runs
 *                                       this filter once per deprecation
 *                                       entry too — see the early return below.
 * @return {Object} Modified settings
 */
function addSvgPatternAttributes(settings, name, deprecatedDefinition) {
	if (!shouldExtendBlock(name)) {
		return settings;
	}

	if (!SUPPORTED_BLOCKS.includes(name)) {
		return settings;
	}

	// WordPress re-applies every `blocks.registerBlockType` filter to each
	// deprecation entry too (so filters can normalize deprecated shapes
	// consistently), passing the deprecation object as this 3rd argument.
	// Our legacy-color deprecation below already carries the exact
	// attributes/save/migrate it needs; re-running this function on it would
	// stomp its deliberately-old dsgoSvgPatternColor default back to the
	// current one, defeating the point of the deprecation.
	if (deprecatedDefinition) {
		return settings;
	}

	const attributes = {
		...settings.attributes,
		dsgoSvgPatternEnabled: {
			type: 'boolean',
			default: DEFAULTS.enabled,
		},
		dsgoSvgPatternType: {
			type: 'string',
			default: DEFAULTS.pattern,
		},
		dsgoSvgPatternColor: {
			type: 'string',
			default: '',
		},
		dsgoSvgPatternOpacity: {
			type: 'number',
			default: DEFAULTS.opacity,
		},
		dsgoSvgPatternScale: {
			type: 'number',
			default: DEFAULTS.scale,
		},
		dsgoSvgPatternFixed: {
			type: 'boolean',
			default: DEFAULTS.fixed,
		},
	};

	// Silent-migration deprecation for content saved before dsgoSvgPatternColor's
	// default changed from DEFAULTS.color ('#9c92ac') to ''. Re-uses the block's
	// own current `save` (captured here, before any other filter can touch it) —
	// the `blocks.getSaveContent.extraProps` filter that writes
	// data-dsgo-svg-pattern-color is driven purely by the attribute's resolved
	// value, so pairing this deprecation's old default with the real save
	// naturally reproduces the old baked-in output for byte comparison. Nothing
	// else about the block changed, so this only matches content invalidated by
	// the default swap — genuinely different old markup still falls through to
	// the block's own deprecations (or the recovery UI) as before.
	const legacyColorDeprecation = {
		attributes: {
			...attributes,
			dsgoSvgPatternColor: {
				type: 'string',
				default: DEFAULTS.color,
			},
		},
		supports: settings.supports,
		apiVersion: settings.apiVersion,
		save: settings.save,
		migrate(migratedAttributes) {
			return migratedAttributes;
		},
	};

	return {
		...settings,
		attributes,
		deprecated: [legacyColorDeprecation, ...(settings.deprecated || [])],
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/svg-pattern-attributes',
	addSvgPatternAttributes
);
