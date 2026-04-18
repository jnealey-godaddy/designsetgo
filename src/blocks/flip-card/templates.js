/**
 * Flip Card Templates
 *
 * Starter layouts shown by FlipCardPlaceholder when the block is first
 * inserted. Every template seeds one front face and one back face so the
 * card is immediately interactive — never a single-faced state.
 *
 * Children are `designsetgo/flip-card-face` (the consolidated block
 * introduced in Theme 2) distinguished by the `side` attribute; the
 * legacy flip-card-front / flip-card-back siblings remain registered
 * with `inserter: false` for existing content but are not seeded here.
 */

import { __ } from '@wordpress/i18n';

// Face children support `spacing.padding` but default to none, so templates
// seed it explicitly — otherwise text sits flush against the card edge on
// first insert. Authors can still adjust via Style → Padding.
const facePadding = {
	spacing: {
		padding: {
			top: '32px',
			right: '32px',
			bottom: '32px',
			left: '32px',
		},
	},
};

const face = (side, extra = {}, innerBlocks = []) => [
	'designsetgo/flip-card-face',
	{
		side,
		style: {
			...facePadding,
			...(extra.style || {}),
		},
	},
	innerBlocks,
];

// Theme-agnostic neutral colors for template starters. Hardcoded hex keeps
// the faces visibly "card-like" on any theme; authors override via Style.
// Palette matches the DesignSetGo wider system (slate-inspired neutrals).
const neutralBack = {
	style: {
		color: {
			background: '#f1f5f9',
			text: '#0f172a',
		},
	},
};

const contrastBack = {
	style: {
		color: {
			background: '#0f172a',
			text: '#ffffff',
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
		innerBlocks: [face('front'), face('back')],
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
			face('front', {}, [
				[
					'core/heading',
					{
						level: 3,
						content: __('Feature title', 'designsetgo'),
						textAlign: 'center',
					},
				],
			]),
			face('back', neutralBack, [
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
			]),
		],
	},
	{
		name: 'profile',
		title: __('Profile', 'designsetgo'),
		description: __('Headshot up front, bio on the back', 'designsetgo'),
		icon: 'admin-users',
		attributes: { flipTrigger: 'click', flipEffect: 'flip' },
		innerBlocks: [
			face('front', {}, [
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
			]),
			face('back', {}, [
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
			]),
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
			face('front', {}, [
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
			]),
			face('back', contrastBack, [
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
			]),
		],
	},
];

export default flipCardTemplates;
