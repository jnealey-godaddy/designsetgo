/**
 * Text Reveal Extension - Attributes
 *
 * Adds text reveal attributes to supported blocks (paragraph, heading)
 *
 * @package
 * @since 1.0.0
 */

import { addFilter } from '@wordpress/hooks';
import { DEFAULT_TEXT_REVEAL_SETTINGS, SUPPORTED_BLOCKS } from './constants';
import { shouldExtendBlock } from '../../utils/should-extend-block';

/**
 * Add text reveal attributes to supported blocks
 *
 * @param {Object} settings Block settings
 * @param {string} name     Block name
 * @return {Object} Modified settings
 */
function addTextRevealAttributes(settings, name) {
	// Check user exclusion list first
	if (!shouldExtendBlock(name)) {
		return settings;
	}

	// Only add attributes to supported blocks
	if (!SUPPORTED_BLOCKS.includes(name)) {
		return settings;
	}

	// Add text reveal attributes
	return {
		...settings,
		attributes: {
			...settings.attributes,
			dsgoTextRevealEnabled: {
				type: 'boolean',
				default: DEFAULT_TEXT_REVEAL_SETTINGS.enabled,
			},
			dsgoTextRevealColor: {
				type: 'string',
				default: DEFAULT_TEXT_REVEAL_SETTINGS.revealColor,
			},
			dsgoTextRevealSplitMode: {
				type: 'string',
				default: DEFAULT_TEXT_REVEAL_SETTINGS.splitMode,
			},
			dsgoTextRevealTransition: {
				type: 'number',
				default: DEFAULT_TEXT_REVEAL_SETTINGS.transitionDuration,
			},
			dsgoTextRevealEffect: {
				type: 'string',
				default: DEFAULT_TEXT_REVEAL_SETTINGS.effect,
			},
		},
	};
}

// Register the filter
addFilter(
	'blocks.registerBlockType',
	'designsetgo/text-reveal/add-attributes',
	addTextRevealAttributes
);
