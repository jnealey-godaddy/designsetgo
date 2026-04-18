/**
 * Scroll Accordion Templates
 *
 * Starter layouts shown by ScrollAccordionPlaceholder when the block is first
 * inserted. Each template seeds three sticky-stacking cards so authors can
 * scroll-test the effect immediately.
 */

import { __ } from '@wordpress/i18n';

const cardPadding = {
	top: 'var:preset|spacing|60',
	right: 'var:preset|spacing|60',
	bottom: 'var:preset|spacing|60',
	left: 'var:preset|spacing|60',
};

function card({ background, text = '#ffffff', heading, body, accentText }) {
	return [
		'designsetgo/scroll-accordion-item',
		{
			// Shadow lives under `style.shadow` because the block exposes
			// shadow via `supports.shadow: true` rather than declaring a
			// top-level `shadow` attribute. A top-level `shadow:` here
			// would be silently dropped on save.
			style: {
				spacing: { padding: cardPadding },
				color: { background, text },
				border: { radius: '16px' },
				shadow: '0 10px 40px rgba(0, 0, 0, 0.1)',
			},
		},
		[
			[
				'core/heading',
				{
					level: 2,
					content: heading,
					style: {
						typography: {
							fontSize: '2.5rem',
							fontWeight: '700',
						},
					},
				},
			],
			[
				'core/paragraph',
				{
					content: body,
					style: {
						typography: { fontSize: '1.125rem' },
						color: { text: accentText || '#cbd5e1' },
					},
				},
			],
		],
	];
}

const scrollAccordionTemplates = [
	{
		name: 'blank',
		title: __('Blank', 'designsetgo'),
		description: __('Three empty cards to fill in', 'designsetgo'),
		icon: 'welcome-add-page',
		attributes: {},
		innerBlocks: [
			[
				'designsetgo/scroll-accordion-item',
				{
					style: {
						spacing: { padding: cardPadding },
						border: { radius: '16px' },
					},
				},
			],
			[
				'designsetgo/scroll-accordion-item',
				{
					style: {
						spacing: { padding: cardPadding },
						border: { radius: '16px' },
					},
				},
			],
			[
				'designsetgo/scroll-accordion-item',
				{
					style: {
						spacing: { padding: cardPadding },
						border: { radius: '16px' },
					},
				},
			],
		],
	},
	{
		name: 'product',
		title: __('Product', 'designsetgo'),
		description: __(
			'Showcase product capabilities as readers scroll',
			'designsetgo'
		),
		icon: 'screenoptions',
		attributes: { alignItems: 'flex-start' },
		innerBlocks: [
			card({
				background: '#1e293b',
				heading: __('Design Systems', 'designsetgo'),
				body: __(
					'Build consistent, scalable interfaces with reusable components and design tokens.',
					'designsetgo'
				),
			}),
			card({
				background: '#0f172a',
				heading: __('Component Library', 'designsetgo'),
				body: __(
					'Pre-built, accessible components that work seamlessly together for rapid development.',
					'designsetgo'
				),
			}),
			card({
				background: '#7c3aed',
				heading: __('Launch & Scale', 'designsetgo'),
				body: __(
					'Deploy with confidence and scale effortlessly with performance-optimized architecture.',
					'designsetgo'
				),
				accentText: '#ede9fe',
			}),
		],
	},
	{
		name: 'process',
		title: __('Process', 'designsetgo'),
		description: __(
			'Walk through a multi-step process or timeline',
			'designsetgo'
		),
		icon: 'list-view',
		attributes: { alignItems: 'flex-start' },
		innerBlocks: [
			card({
				background: '#0f3460',
				heading: __('1. Discover', 'designsetgo'),
				body: __(
					'Understand the problem space, the users, and the constraints.',
					'designsetgo'
				),
			}),
			card({
				background: '#16213e',
				heading: __('2. Design', 'designsetgo'),
				body: __(
					'Sketch, prototype, and validate solutions with stakeholders.',
					'designsetgo'
				),
			}),
			card({
				background: '#1a1a2e',
				heading: __('3. Deliver', 'designsetgo'),
				body: __(
					'Ship the work, measure impact, and iterate on what we learn.',
					'designsetgo'
				),
			}),
		],
	},
];

export default scrollAccordionTemplates;
