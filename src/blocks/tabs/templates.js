/**
 * Tabs Templates
 *
 * Starter layouts shown by TabsPlaceholder when the block is first inserted.
 * Each template seeds three tabs so the user has a real starting point rather
 * than an empty tablist after picking a tile.
 */

import { __ } from '@wordpress/i18n';

function tabPanel(title, body) {
	return [
		'designsetgo/tab',
		{ title },
		[
			['core/heading', { level: 3, content: title }],
			['core/paragraph', { content: body }],
		],
	];
}

const tabsTemplates = [
	{
		name: 'blank',
		title: __('Blank', 'designsetgo'),
		description: __('Three empty tabs to fill in', 'designsetgo'),
		icon: 'welcome-add-page',
		attributes: {},
		innerBlocks: [
			['designsetgo/tab', { title: __('Tab 1', 'designsetgo') }],
			['designsetgo/tab', { title: __('Tab 2', 'designsetgo') }],
			['designsetgo/tab', { title: __('Tab 3', 'designsetgo') }],
		],
	},
	{
		name: 'horizontal',
		title: __('Horizontal', 'designsetgo'),
		description: __('Classic top-aligned tabs', 'designsetgo'),
		icon: 'editor-table',
		attributes: {
			orientation: 'horizontal',
			tabStyle: 'default',
			alignment: 'left',
			showNavBorder: true,
		},
		innerBlocks: [
			tabPanel(
				__('Overview', 'designsetgo'),
				__(
					'Introduce the topic of this tab. Use any blocks you need for the body.',
					'designsetgo'
				)
			),
			tabPanel(
				__('Features', 'designsetgo'),
				__(
					'Highlight the key features or differentiators in this section.',
					'designsetgo'
				)
			),
			tabPanel(
				__('Pricing', 'designsetgo'),
				__(
					'Outline pricing tiers, what they include, and how to upgrade.',
					'designsetgo'
				)
			),
		],
	},
	{
		name: 'vertical',
		title: __('Vertical', 'designsetgo'),
		description: __(
			'Side-aligned tabs for longer-form content',
			'designsetgo'
		),
		icon: 'align-pull-left',
		attributes: {
			orientation: 'vertical',
			tabStyle: 'default',
			alignment: 'left',
		},
		innerBlocks: [
			tabPanel(
				__('Getting started', 'designsetgo'),
				__(
					'Walk readers through the first thing they need to do.',
					'designsetgo'
				)
			),
			tabPanel(
				__('Configuration', 'designsetgo'),
				__(
					'Document the settings or options that matter most.',
					'designsetgo'
				)
			),
			tabPanel(
				__('FAQ', 'designsetgo'),
				__(
					'Answer the questions readers ask most often.',
					'designsetgo'
				)
			),
		],
	},
	{
		name: 'pill',
		title: __('Pill', 'designsetgo'),
		description: __(
			'Rounded pill-style tabs with subtle hover',
			'designsetgo'
		),
		icon: 'marker',
		attributes: {
			orientation: 'horizontal',
			tabStyle: 'pills',
			alignment: 'center',
			gap: '8px',
		},
		innerBlocks: [
			tabPanel(
				__('Design', 'designsetgo'),
				__(
					'Highlight the design philosophy and visual decisions.',
					'designsetgo'
				)
			),
			tabPanel(
				__('Build', 'designsetgo'),
				__(
					'Explain the build process and the tooling involved.',
					'designsetgo'
				)
			),
			tabPanel(
				__('Ship', 'designsetgo'),
				__(
					'Cover deployment, monitoring, and ongoing iteration.',
					'designsetgo'
				)
			),
		],
	},
];

export default tabsTemplates;
