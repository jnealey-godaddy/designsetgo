import { __ } from '@wordpress/i18n';

export default [
	{
		name: 'blog-index',
		title: __('Blog index', 'designsetgo'),
		description: __(
			'Latest posts in a responsive grid with featured image, title, excerpt, and date.',
			'designsetgo'
		),
		icon: 'admin-post',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 9,
			orderBy: 'date',
			order: 'DESC',
			tagName: 'ul',
			itemTagName: 'li',
		},
		innerBlocks: [
			[
				'designsetgo/section',
				{},
				[
					[ 'core/post-featured-image', { isLink: true } ],
					[ 'core/post-title', { level: 3, isLink: true } ],
					[ 'core/post-date' ],
					[ 'core/post-excerpt', { excerptLength: 30 } ],
				],
			],
		],
		scope: ['inserter'],
	},
	{
		name: 'team',
		title: __('Team directory', 'designsetgo'),
		description: __(
			'Team members grid. Switch Post type to your `team` CPT (or similar) in the inspector.',
			'designsetgo'
		),
		icon: 'groups',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 12,
			orderBy: 'menu_order',
			order: 'ASC',
			tagName: 'ul',
			itemTagName: 'li',
		},
		innerBlocks: [
			[
				'designsetgo/section',
				{},
				[
					[ 'core/post-featured-image' ],
					[ 'core/post-title', { level: 3 } ],
					[ 'core/post-excerpt', { excerptLength: 15 } ],
				],
			],
		],
		scope: ['inserter'],
	},
	{
		name: 'testimonials',
		title: __('Testimonials', 'designsetgo'),
		description: __(
			'Customer quotes layout. Use the ACF binding source to render quote meta.',
			'designsetgo'
		),
		icon: 'format-quote',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 6,
			orderBy: 'date',
			order: 'DESC',
			tagName: 'ul',
			itemTagName: 'li',
		},
		innerBlocks: [
			[
				'designsetgo/section',
				{},
				[
					[ 'core/post-excerpt' ],
					[ 'core/post-title', { level: 4 } ],
				],
			],
		],
		scope: ['inserter'],
	},
	{
		name: 'portfolio',
		title: __('Portfolio', 'designsetgo'),
		description: __(
			'Project showcase in a grid with featured images.',
			'designsetgo'
		),
		icon: 'portfolio',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 12,
			orderBy: 'date',
			order: 'DESC',
			tagName: 'ul',
			itemTagName: 'li',
		},
		innerBlocks: [
			[
				'designsetgo/section',
				{},
				[
					[ 'core/post-featured-image', { isLink: true, aspectRatio: '4/3' } ],
					[ 'core/post-title', { level: 3, isLink: true } ],
				],
			],
		],
		scope: ['inserter'],
	},
	{
		name: 'related-posts',
		title: __('Related posts', 'designsetgo'),
		description: __(
			'Posts excluding the current one. Pair with the designsetgo/query/{queryId}/args filter to narrow by shared taxonomy.',
			'designsetgo'
		),
		icon: 'controls-repeat',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 3,
			orderBy: 'rand',
			order: 'DESC',
			excludeCurrent: true,
			tagName: 'ul',
			itemTagName: 'li',
		},
		innerBlocks: [
			[
				'designsetgo/section',
				{},
				[
					[ 'core/post-featured-image', { isLink: true } ],
					[ 'core/post-title', { level: 4, isLink: true } ],
				],
			],
		],
		scope: ['inserter'],
	},
	{
		name: 'events',
		title: __('Events', 'designsetgo'),
		description: __(
			'Upcoming event listings sorted by date. Switch Post type to your `events` CPT (or similar) in the inspector.',
			'designsetgo'
		),
		icon: 'calendar-alt',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 6,
			orderBy: 'date',
			order: 'ASC',
			tagName: 'ul',
			itemTagName: 'li',
		},
		innerBlocks: [
			[
				'designsetgo/section',
				{},
				[
					[ 'core/post-featured-image' ],
					[ 'core/post-date' ],
					[ 'core/post-title', { level: 3, isLink: true } ],
					[ 'core/post-excerpt', { excerptLength: 20 } ],
				],
			],
		],
		scope: ['inserter'],
	},
];
