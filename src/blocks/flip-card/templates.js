/**
 * Flip Card Templates
 *
 * Starter layouts shown by FlipCardPlaceholder when the block is first
 * inserted. Every template seeds both flip-card-front and flip-card-back so
 * the card is immediately interactive — never a single-faced state.
 */

import { __ } from '@wordpress/i18n';

// flip-card-front/back support `spacing.padding` but default to none, so
// templates seed it explicitly — otherwise text sits flush against the card
// edge on first insert. Authors can still adjust via Style → Padding.
const facePadding = {
	style: {
		spacing: {
			padding: {
				top: '32px',
				right: '32px',
				bottom: '32px',
				left: '32px',
			},
		},
	},
};

const flipCardTemplates = [
	{
		name: 'blank',
		title: __('Blank', 'designsetgo'),
		description: __('Empty front and back to fill in', 'designsetgo'),
		icon: 'welcome-add-page',
		attributes: {},
		innerBlocks: [
			['designsetgo/flip-card-front', facePadding],
			['designsetgo/flip-card-back', facePadding],
		],
	},
	{
		name: 'feature',
		title: __('Feature', 'designsetgo'),
		description: __(
			'Title up front, supporting detail on the back',
			'designsetgo'
		),
		icon: 'star-filled',
		attributes: { flipTrigger: 'hover', flipEffect: 'flip' },
		innerBlocks: [
			[
				'designsetgo/flip-card-front',
				facePadding,
				[
					[
						'core/heading',
						{
							level: 3,
							content: __('Feature title', 'designsetgo'),
							textAlign: 'center',
						},
					],
				],
			],
			[
				'designsetgo/flip-card-back',
				facePadding,
				[
					[
						'core/paragraph',
						{
							content: __(
								'Add a short description of the feature, the value it delivers, or how it works.',
								'designsetgo'
							),
							align: 'center',
						},
					],
				],
			],
		],
	},
	{
		name: 'profile',
		title: __('Profile', 'designsetgo'),
		description: __('Headshot up front, bio on the back', 'designsetgo'),
		icon: 'admin-users',
		attributes: { flipTrigger: 'click', flipEffect: 'flip' },
		innerBlocks: [
			[
				'designsetgo/flip-card-front',
				facePadding,
				[
					['core/image', { sizeSlug: 'medium' }],
					[
						'core/heading',
						{
							level: 4,
							content: __('Name', 'designsetgo'),
							textAlign: 'center',
						},
					],
					[
						'core/paragraph',
						{
							content: __('Role / Title', 'designsetgo'),
							align: 'center',
						},
					],
				],
			],
			[
				'designsetgo/flip-card-back',
				facePadding,
				[
					[
						'core/paragraph',
						{
							content: __(
								'Short bio. Mention background, current focus, and how to get in touch.',
								'designsetgo'
							),
							align: 'center',
						},
					],
				],
			],
		],
	},
	{
		name: 'cta',
		title: __('Call to Action', 'designsetgo'),
		description: __(
			'Lead with a hook, finish with a button',
			'designsetgo'
		),
		icon: 'megaphone',
		attributes: { flipTrigger: 'hover', flipEffect: 'flip' },
		innerBlocks: [
			[
				'designsetgo/flip-card-front',
				facePadding,
				[
					[
						'core/heading',
						{
							level: 3,
							content: __('Try it free', 'designsetgo'),
							textAlign: 'center',
						},
					],
					[
						'core/paragraph',
						{
							content: __('Hover to learn more.', 'designsetgo'),
							align: 'center',
						},
					],
				],
			],
			[
				'designsetgo/flip-card-back',
				facePadding,
				[
					[
						'core/paragraph',
						{
							content: __(
								'No credit card required. Cancel anytime.',
								'designsetgo'
							),
							align: 'center',
						},
					],
					[
						'designsetgo/icon-button',
						{
							text: __('Get started', 'designsetgo'),
							align: 'center',
						},
					],
				],
			],
		],
	},
];

export default flipCardTemplates;
