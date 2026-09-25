/**
 * Accordion Block - Transforms
 *
 * Converts to and from core Details blocks: each Details becomes one
 * accordion item, and back.
 */

import { createBlock } from '@wordpress/blocks';

/**
 * Rich-text attributes arrive as RichTextData on WP 6.5+ and as HTML strings
 * before that; both stringify to the HTML.
 *
 * @param {*} value Rich-text attribute value.
 * @return {string} HTML string.
 */
const toHTML = (value) => (value ? String(value) : '');

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
						// Details blocks open independently; an accordion that
						// closed the others would change how the content behaves.
						allowMultipleOpen: attributesList.length > 1,
					},
					attributesList.map((attributes, index) =>
						createBlock(
							'designsetgo/accordion-item',
							{
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
			transform: (attributes, innerBlocks) =>
				innerBlocks
					.filter(
						(item) => item.name === 'designsetgo/accordion-item'
					)
					.map((item) =>
						createBlock(
							'core/details',
							{
								summary: toHTML(item.attributes.title),
								showContent: !!item.attributes.isOpen,
							},
							item.innerBlocks
						)
					),
		},
	],
};

export default transforms;
