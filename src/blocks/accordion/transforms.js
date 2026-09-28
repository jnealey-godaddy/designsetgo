/**
 * Accordion Block - Transforms
 *
 * Converts to and from core Details blocks: each Details becomes one
 * accordion item, and back.
 */

import { createBlock, getBlockType } from '@wordpress/blocks';
import { pickColors } from '../../utils/pick-attributes';

/**
 * Rich-text attributes arrive as RichTextData on WP 6.5+ and as HTML strings
 * before that; both stringify to the HTML.
 *
 * @param {*} value Rich-text attribute value.
 * @return {string} HTML string.
 */
const toHTML = (value) => (value ? String(value) : '');

// Accordion items and Details both support colour, border and padding.
const ITEM_STYLE = { border: true, padding: true };

/**
 * Whether Details blocks form an exclusive group: sharing a `name` makes the
 * browser keep only one of them open (WordPress 6.9+).
 *
 * @param {Object[]} attributesList Details attributes.
 * @return {boolean} True when they all share one non-empty name.
 */
const isExclusiveGroup = (attributesList) => {
	const first = attributesList[0]?.name || '';
	return first !== '' && attributesList.every((a) => a.name === first);
};

const transforms = {
	from: [
		{
			type: 'block',
			blocks: ['core/details'],
			// Selecting several Details blocks makes one accordion of them.
			isMultiBlock: true,
			transform: (attributesList, innerBlocksList) =>
				createBlock(
					'designsetgo/accordion',
					{
						// Details open independently unless they share a name;
						// keep whichever behaviour the content already had.
						allowMultipleOpen:
							attributesList.length > 1 &&
							!isExclusiveGroup(attributesList),
					},
					attributesList.map((attributes, index) =>
						createBlock(
							'designsetgo/accordion-item',
							{
								...pickColors(attributes, ITEM_STYLE),
								title: toHTML(attributes.summary),
								isOpen: !!attributes.showContent,
							},
							innerBlocksList[index]
						)
					)
				),
		},
	],
	to: [
		{
			type: 'block',
			blocks: ['core/details'],
			// An accordion with no items would become zero Details blocks,
			// which WordPress treats as deleting it.
			isMatch: (attributes, block) =>
				!block?.innerBlocks ||
				block.innerBlocks.some(
					(item) => item.name === 'designsetgo/accordion-item'
				),
			transform: (attributes, innerBlocks) => {
				const items = innerBlocks.filter(
					(item) => item.name === 'designsetgo/accordion-item'
				);
				// One-open-at-a-time becomes a shared Details name, where the
				// running WordPress supports it.
				const group =
					!attributes.allowMultipleOpen &&
					items.length > 1 &&
					getBlockType('core/details')?.attributes?.name
						? `accordion-${
								items[0].attributes.uniqueId ||
								items[0].clientId.slice(0, 8)
							}`
						: undefined;

				return items.map((item) =>
					createBlock(
						'core/details',
						{
							...pickColors(item.attributes, ITEM_STYLE),
							summary: toHTML(item.attributes.title),
							showContent: !!item.attributes.isOpen,
							...(group && { name: group }),
						},
						item.innerBlocks
					)
				);
			},
		},
	],
};

export default transforms;
