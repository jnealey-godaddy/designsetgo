/**
 * Interaction Layers - Attributes
 *
 * Registers the dsgoInteractions attribute on every eligible block.
 *
 * @package
 */

import { addFilter } from '@wordpress/hooks';
import { shouldExtendBlock } from '../../utils/should-extend-block';

/**
 * Add the interactions attribute to a block's settings.
 *
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Modified settings.
 */
function addInteractionsAttribute(settings, name) {
	if (!shouldExtendBlock(name)) {
		return settings;
	}

	if (name.startsWith('core-embed/') || 'core/freeform' === name) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			dsgoInteractions: {
				type: 'array',
				default: [],
			},
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/interactions/attributes',
	addInteractionsAttribute
);
