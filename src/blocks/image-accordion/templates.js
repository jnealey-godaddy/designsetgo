/**
 * Image Accordion Templates
 *
 * Starter layouts shown by ImageAccordionPlaceholder when the block is first
 * inserted. The image-accordion's frontend behavior is driven by per-item
 * background images, so the template seeds three items with placeholder
 * heading + paragraph content the author can replace.
 */

import { __ } from '@wordpress/i18n';

function imageItem(heading, body) {
	return [
		'designsetgo/image-accordion-item',
		{},
		[
			['core/heading', { level: 3, content: heading }],
			['core/paragraph', { content: body, align: 'center' }],
		],
	];
}

const imageAccordionTemplates = [
	{
		name: 'blank',
		title: __('Blank', 'designsetgo'),
		description: __('Three empty panels to fill in', 'designsetgo'),
		icon: 'welcome-add-page',
		attributes: {},
		innerBlocks: [
			['designsetgo/image-accordion-item', {}],
			['designsetgo/image-accordion-item', {}],
			['designsetgo/image-accordion-item', {}],
		],
	},
	{
		name: 'showcase',
		title: __('Showcase', 'designsetgo'),
		description: __(
			'Three feature panels with hover-to-expand',
			'designsetgo'
		),
		icon: 'images-alt2',
		attributes: {
			triggerType: 'hover',
			enableOverlay: true,
			overlayOpacity: 50,
			overlayOpacityExpanded: 20,
		},
		innerBlocks: [
			imageItem(
				__('Design', 'designsetgo'),
				__(
					'Craft beautiful interfaces with carefully chosen typography and color.',
					'designsetgo'
				)
			),
			imageItem(
				__('Build', 'designsetgo'),
				__(
					'Compose layouts from accessible, reusable building blocks.',
					'designsetgo'
				)
			),
			imageItem(
				__('Ship', 'designsetgo'),
				__(
					'Publish polished pages without writing a line of code.',
					'designsetgo'
				)
			),
		],
	},
	{
		name: 'gallery',
		title: __('Gallery', 'designsetgo'),
		description: __(
			'Click-to-expand gallery for image-led storytelling',
			'designsetgo'
		),
		icon: 'format-gallery',
		attributes: {
			triggerType: 'click',
			expandedRatio: 4,
			enableOverlay: true,
			overlayOpacity: 30,
			overlayOpacityExpanded: 0,
		},
		innerBlocks: [
			imageItem(
				__('Series One', 'designsetgo'),
				__('Add a caption for the first image.', 'designsetgo')
			),
			imageItem(
				__('Series Two', 'designsetgo'),
				__('Add a caption for the second image.', 'designsetgo')
			),
			imageItem(
				__('Series Three', 'designsetgo'),
				__('Add a caption for the third image.', 'designsetgo')
			),
			imageItem(
				__('Series Four', 'designsetgo'),
				__('Add a caption for the fourth image.', 'designsetgo')
			),
		],
	},
];

export default imageAccordionTemplates;
