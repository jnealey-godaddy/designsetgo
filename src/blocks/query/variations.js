import { __ } from '@wordpress/i18n';

/**
 * Dynamic Query inserter variations.
 *
 * Post-restructure (v2.6) the outer designsetgo/query block is a pure
 * container; presentation attrs (columns, tagName, groupBy…) and the item
 * template live on the required designsetgo/query-results child.
 *
 * Each variation carries a `layoutVariant` on the query-results child so the
 * scoped SCSS in query-results/style.scss can style each layout distinctly.
 * Pair the layoutVariant with a per-variation template that plays to that
 * layout's strengths (quote-card omits the image, compact-row uses a square
 * thumb, etc.) so the inserter previews read as visually different at a
 * glance rather than "same grid, different columns".
 */
export default [
	{
		name: 'blog-index',
		title: __('Blog index', 'designsetgo'),
		description: __(
			'Magazine-style cards with featured image, date, and excerpt. Search + sort + numbered pagination.',
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
				{
					tagName: 'ul',
					itemTagName: 'li',
					columns: 3,
					layoutVariant: 'magazine',
				},
				[
					[
						'designsetgo/section',
						{},
						[
							[
								'core/post-featured-image',
								{ isLink: true, aspectRatio: '3/2' },
							],
							['core/post-date'],
							['core/post-title', { level: 3, isLink: true }],
							['core/post-excerpt', { excerptLength: 25 }],
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
			'Circular avatars in a centered grid. Switch Post type to your `team` CPT in the inspector.',
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
				{
					tagName: 'ul',
					itemTagName: 'li',
					columns: 4,
					layoutVariant: 'avatar-grid',
				},
				[
					[
						'designsetgo/section',
						{},
						[
							[
								'core/post-featured-image',
								{
									align: 'center',
									aspectRatio: '1',
									width: '140px',
								},
							],
							['core/post-title', { level: 3 }],
							['core/post-excerpt', { excerptLength: 12 }],
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
			'Pull-quote cards with oversized excerpt and attribution. Load-more pagination.',
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
				{
					tagName: 'ul',
					itemTagName: 'li',
					columns: 2,
					layoutVariant: 'quote-card',
				},
				[
					[
						'designsetgo/section',
						{},
						[
							['core/post-excerpt', { excerptLength: 40 }],
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
			'Image tiles with overlay title. Category filter and load-more pagination.',
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
					filterStyle: 'pill',
				},
			],
			[
				'designsetgo/query-results',
				{
					tagName: 'ul',
					itemTagName: 'li',
					columns: 3,
					layoutVariant: 'image-tile',
				},
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
			'Compact horizontal rows — small thumbnail + title. Excludes current post by default.',
			'designsetgo'
		),
		icon: 'controls-repeat',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 6,
			orderBy: 'rand',
			order: 'DESC',
			excludeCurrent: true,
		},
		innerBlocks: [
			[
				'designsetgo/query-results',
				{
					tagName: 'ul',
					itemTagName: 'li',
					columns: 3,
					layoutVariant: 'compact-row',
				},
				[
					[
						'designsetgo/section',
						{},
						[
							[
								'core/post-featured-image',
								{
									isLink: true,
									aspectRatio: '1',
									width: '96px',
								},
							],
							[
								'designsetgo/section',
								{},
								[
									[
										'core/post-title',
										{ level: 4, isLink: true },
									],
									['core/post-date'],
								],
							],
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
			'Cinematic slider — one post per slide, oversized image, centered title. Dots + arrows.',
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
					slidesPerView: 3,
					slidesPerViewTablet: 2,
					slidesPerViewMobile: 1,
					showArrows: true,
					showDots: true,
					arrowPosition: 'outside',
					dotPosition: 'outside',
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
							[
								'core/post-featured-image',
								{ aspectRatio: '21/9' },
							],
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
			'Scroll-driven story panels. One post per panel, big title, no image. Ideal for narrative lists.',
			'designsetgo'
		),
		icon: 'format-gallery',
		attributes: {
			source: 'posts',
			postType: 'post',
			perPage: 4,
			orderBy: 'date',
			order: 'DESC',
			// Scroll-slides is a pinned, viewport-height experience; a
			// constrained outer query container would letterbox it.
			align: 'full',
		},
		innerBlocks: [
			[
				'designsetgo/scroll-slides',
				{
					align: 'full',
					minHeight: '100vh',
					// Default maxHeight on the block is 900px, which caps the
					// pinned section below the 100vh the variation asks for on
					// tall viewports. Clear it so 100vh lands at viewport
					// height.
					maxHeight: '',
					overlayColor: '#000000',
					style: { color: { text: '#ffffff' } },
				},
				[
					[
						'designsetgo/scroll-slide',
						{},
						[
							[
								'core/post-excerpt',
								{
									excerptLength: 30,
									moreText: __('Read more', 'designsetgo'),
									showMoreOnNewLine: true,
								},
							],
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
			'Date-forward cards with accent rail. Sort controls and numbered pagination. Switch Post type to your `events` CPT.',
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
				{
					tagName: 'ul',
					itemTagName: 'li',
					columns: 2,
					layoutVariant: 'date-forward',
				},
				[
					[
						'designsetgo/section',
						{},
						[
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
