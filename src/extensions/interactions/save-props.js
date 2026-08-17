/**
 * Interaction Layers - Save props
 *
 * Serialises interactions onto the block wrapper as a JSON data attribute.
 *
 * @package
 */

import { addFilter } from '@wordpress/hooks';

/**
 * Emit data-dsgo-interactions on the saved markup.
 *
 * Omitted entirely when the list is empty, so existing content serialises
 * byte-identically and no deprecation is needed.
 *
 * @param {Object} extraProps Existing save props.
 * @param {Object} blockType  Block type.
 * @param {Object} attributes Block attributes.
 * @return {Object} Modified save props.
 */
function addInteractionsProp(extraProps, blockType, attributes) {
	const interactions = attributes?.dsgoInteractions;

	if (!Array.isArray(interactions) || 0 === interactions.length) {
		return extraProps;
	}

	return {
		...extraProps,
		'data-dsgo-interactions': JSON.stringify(interactions),
	};
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/interactions/save-props',
	addInteractionsProp
);
