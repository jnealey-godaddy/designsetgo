import { __ } from '@wordpress/i18n';

/**
 * Dynamic Query inserter variations.
 *
 * Post-restructure (v2.6) the outer designsetgo/query block is a pure
 * container; presentation attrs (columns, tagName, groupBy…) and the item
 * template live on the required designsetgo/query-results child.
 *
 * Each variation ships with a sensible default set of sibling blocks —
 * filter, no-results, pagination — so authors see the whole Dynamic Query
 * toolkit on first insert. Removing a block you don't need is trivial;
 * discovering blocks that weren't scaffolded for you is not.
 */
export default [
	{
		name: 'blog-index',
		title: __('Blog index', 'designsetgo'),
		description: __(
			'Latest posts with search + sort, a responsive card grid, and numbered pagination.',
			'designsetgo'
		),
		icon: 'admin-post',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 9,
			orderBy: 'date',
			order: 'DESC',
		},
		innerBlocks: [
			[
				'designsetgo/query-filter',
				{
					filterKind: 'search',
					paramName: 'q',
					label: __('Search posts', 'designsetgo'),
					placeholder: __('Search…', 'designsetgo'),
				},
			],
			[
				'designsetgo/query-filter',
				{
					filterKind: 'sort',
					paramName: 'sort',
					label: __('Sort by', 'designsetgo'),
				},
			],
			[
				'designsetgo/query-results',
				{ tagName: 'ul', itemTagName: 'li', columns: 3 },
				[
					[
						'designsetgo/section',
						{},
						[
							['core/post-featured-image', { isLink: true }],
							['core/post-title', { level: 3, isLink: true }],
							['core/post-date'],
							['core/post-excerpt', { excerptLength: 30 }],
						],
					],
				],
			],
			['designsetgo/query-no-results'],
			['designsetgo/query-pagination', { paginationKind: 'numbered' }],
		],
	},
	{
		name: 'team',
		title: __('Team directory', 'designsetgo'),
		description: __(
			'Searchable grid of team members. Switch Post type to your `team` CPT in the inspector.',
			'designsetgo'
		),
		icon: 'groups',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 12,
			orderBy: 'menu_order',
			order: 'ASC',
		},
		innerBlocks: [
			[
				'designsetgo/query-filter',
				{
					filterKind: 'search',
					paramName: 'q',
					label: __('Search team', 'designsetgo'),
					placeholder: __('Search by name…', 'designsetgo'),
				},
			],
			[
				'designsetgo/query-results',
				{ tagName: 'ul', itemTagName: 'li', columns: 4 },
				[
					[
						'designsetgo/section',
						{},
						[
							['core/post-featured-image'],
							['core/post-title', { level: 3 }],
							['core/post-excerpt', { excerptLength: 15 }],
						],
					],
				],
			],
			['designsetgo/query-no-results'],
		],
	},
	{
		name: 'testimonials',
		title: __('Testimonials', 'designsetgo'),
		description: __(
			'Customer quotes layout with load-more pagination. Pair with the ACF binding source to render quote meta.',
			'designsetgo'
		),
		icon: 'format-quote',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 6,
			orderBy: 'date',
			order: 'DESC',
		},
		innerBlocks: [
			[
				'designsetgo/query-results',
				{ tagName: 'ul', itemTagName: 'li', columns: 2 },
				[
					[
						'designsetgo/section',
						{},
						[
							['core/post-excerpt'],
							['core/post-title', { level: 4 }],
						],
					],
				],
			],
			['designsetgo/query-pagination', { paginationKind: 'loadmore' }],
		],
	},
	{
		name: 'portfolio',
		title: __('Portfolio', 'designsetgo'),
		description: __(
			'Project showcase with category filter and load-more pagination.',
			'designsetgo'
		),
		icon: 'portfolio',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 12,
			orderBy: 'date',
			order: 'DESC',
		},
		innerBlocks: [
			[
				'designsetgo/query-filter',
				{
					filterKind: 'checkbox',
					taxonomy: 'category',
					paramName: 'filter_category',
					label: __('Filter by category', 'designsetgo'),
				},
			],
			[
				'designsetgo/query-results',
				{ tagName: 'ul', itemTagName: 'li', columns: 3 },
				[
					[
						'designsetgo/section',
						{},
						[
							[
								'core/post-featured-image',
								{ isLink: true, aspectRatio: '4/3' },
							],
							['core/post-title', { level: 3, isLink: true }],
						],
					],
				],
			],
			['designsetgo/query-no-results'],
			['designsetgo/query-pagination', { paginationKind: 'loadmore' }],
		],
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
		},
		innerBlocks: [
			[
				'designsetgo/query-results',
				{ tagName: 'ul', itemTagName: 'li', columns: 3 },
				[
					[
						'designsetgo/section',
						{},
						[
							['core/post-featured-image', { isLink: true }],
							['core/post-title', { level: 4, isLink: true }],
						],
					],
				],
			],
		],
	},
	{
		name: 'featured-carousel',
		title: __('Featured carousel', 'designsetgo'),
		description: __(
			'Slider that iterates posts — featured image, title, excerpt per slide. Edits to slide 1 apply to all.',
			'designsetgo'
		),
		icon: 'images-alt2',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 5,
			orderBy: 'date',
			order: 'DESC',
		},
		innerBlocks: [
			[
				'designsetgo/slider',
				{
					slidesPerView: 1,
					showArrows: true,
					showDots: true,
					effect: 'slide',
					loop: true,
					autoplay: false,
				},
				[
					[
						'designsetgo/slide',
						{
							contentVerticalAlign: 'center',
							contentHorizontalAlign: 'center',
						},
						[
							['core/post-featured-image', { aspectRatio: '16/9' }],
							['core/post-title', { level: 3, isLink: true }],
							['core/post-excerpt', { excerptLength: 20 }],
						],
					],
				],
			],
			['designsetgo/query-no-results'],
		],
	},
	{
		name: 'post-spotlight',
		title: __('Post spotlight (scroll-slides)', 'designsetgo'),
		description: __(
			'Scroll-driven panels that iterate posts. Each panel shows title + excerpt; one template applies to every post.',
			'designsetgo'
		),
		icon: 'format-gallery',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 4,
			orderBy: 'date',
			order: 'DESC',
		},
		innerBlocks: [
			[
				'designsetgo/scroll-slides',
				{ minHeight: '70vh' },
				[
					[
						'designsetgo/scroll-slide',
						{ navHeading: __('Story', 'designsetgo') },
						[
							['core/post-title', { level: 2, isLink: true }],
							['core/post-excerpt', { excerptLength: 30 }],
						],
					],
				],
			],
			['designsetgo/query-no-results'],
		],
	},
	{
		name: 'events',
		title: __('Events', 'designsetgo'),
		description: __(
			'Upcoming event listings with sort controls and pagination. Switch Post type to your `events` CPT in the inspector.',
			'designsetgo'
		),
		icon: 'calendar-alt',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 6,
			orderBy: 'date',
			order: 'ASC',
		},
		innerBlocks: [
			[
				'designsetgo/query-filter',
				{
					filterKind: 'sort',
					paramName: 'sort',
					label: __('Sort by', 'designsetgo'),
				},
			],
			[
				'designsetgo/query-results',
				{ tagName: 'ul', itemTagName: 'li', columns: 2 },
				[
					[
						'designsetgo/section',
						{},
						[
							['core/post-featured-image'],
							['core/post-date'],
							['core/post-title', { level: 3, isLink: true }],
							['core/post-excerpt', { excerptLength: 20 }],
						],
					],
				],
			],
			['designsetgo/query-no-results'],
			['designsetgo/query-pagination', { paginationKind: 'numbered' }],
		],
	},
];
