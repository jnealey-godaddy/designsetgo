/**
 * Accordion Templates
 *
 * Starter layouts shown by AccordionPlaceholder when the block is first
 * inserted. Every template ends with at least one accordion-item so the user
 * never lands in a silent-empty state after picking a tile.
 */

import { __ } from '@wordpress/i18n';

const accordionTemplates = [
	{
		name: 'blank',
		title: __('Blank', 'designsetgo'),
		description: __('Two empty items to fill in', 'designsetgo'),
		icon: 'welcome-add-page',
		attributes: {},
		innerBlocks: [
			[
				'designsetgo/accordion-item',
				{ title: __('Accordion Item 1', 'designsetgo') },
			],
			[
				'designsetgo/accordion-item',
				{ title: __('Accordion Item 2', 'designsetgo') },
			],
		],
	},
	{
		name: 'faq',
		title: __('FAQ', 'designsetgo'),
		description: __(
			'Frequently asked questions with short answers',
			'designsetgo'
		),
		icon: 'editor-help',
		attributes: {
			iconStyle: 'plus-minus',
			iconPosition: 'right',
		},
		innerBlocks: [
			[
				'designsetgo/accordion-item',
				{
					title: __('What is included with my plan?', 'designsetgo'),
				},
				[
					[
						'core/paragraph',
						{
							content: __(
								'Every plan includes the full block library, regular updates, and access to support. Upgrade at any time to unlock advanced layouts.',
								'designsetgo'
							),
						},
					],
				],
			],
			[
				'designsetgo/accordion-item',
				{
					title: __(
						'How do I cancel my subscription?',
						'designsetgo'
					),
				},
				[
					[
						'core/paragraph',
						{
							content: __(
								'You can cancel anytime from your account dashboard. Your access continues until the end of the billing period.',
								'designsetgo'
							),
						},
					],
				],
			],
			[
				'designsetgo/accordion-item',
				{
					title: __('Do you offer refunds?', 'designsetgo'),
				},
				[
					[
						'core/paragraph',
						{
							content: __(
								'We offer a 30-day money-back guarantee. Just reach out to support and we will process your refund.',
								'designsetgo'
							),
						},
					],
				],
			],
		],
	},
	{
		name: 'content',
		title: __('Content', 'designsetgo'),
		description: __(
			'Rich content sections with headings and media',
			'designsetgo'
		),
		icon: 'editor-alignleft',
		attributes: {
			iconStyle: 'chevron',
			iconPosition: 'right',
			borderBetween: false,
		},
		innerBlocks: [
			[
				'designsetgo/accordion-item',
				{
					title: __('Overview', 'designsetgo'),
					isOpen: true,
				},
				[
					[
						'core/heading',
						{
							level: 3,
							content: __('Section heading', 'designsetgo'),
						},
					],
					[
						'core/paragraph',
						{
							content: __(
								'Use this section to explain a concept in depth. Add any blocks you need — paragraphs, images, columns, or buttons all work inside an accordion item.',
								'designsetgo'
							),
						},
					],
				],
			],
			[
				'designsetgo/accordion-item',
				{
					title: __('Details', 'designsetgo'),
				},
				[
					[
						'core/paragraph',
						{
							content: __(
								'Drop in supporting details, examples, or step-by-step instructions.',
								'designsetgo'
							),
						},
					],
				],
			],
		],
	},
	{
		name: 'icon-list',
		title: __('Icon List', 'designsetgo'),
		description: __(
			'Compact list with icons and short blurbs',
			'designsetgo'
		),
		icon: 'list-view',
		attributes: {
			iconStyle: 'caret',
			iconPosition: 'left',
			borderBetween: false,
			itemGap: '0.25rem',
		},
		innerBlocks: [
			[
				'designsetgo/accordion-item',
				{
					title: __('Lightning fast', 'designsetgo'),
				},
				[
					[
						'core/paragraph',
						{
							content: __(
								'Optimized assets and minimal markup keep page loads quick.',
								'designsetgo'
							),
						},
					],
				],
			],
			[
				'designsetgo/accordion-item',
				{
					title: __('Accessible by default', 'designsetgo'),
				},
				[
					[
						'core/paragraph',
						{
							content: __(
								'ARIA roles and keyboard navigation are wired up automatically.',
								'designsetgo'
							),
						},
					],
				],
			],
			[
				'designsetgo/accordion-item',
				{
					title: __('Designed to extend', 'designsetgo'),
				},
				[
					[
						'core/paragraph',
						{
							content: __(
								'Style with theme.json or override individual items in the editor.',
								'designsetgo'
							),
						},
					],
				],
			],
		],
	},
];

export default accordionTemplates;
