/**
 * Slider Templates
 *
 * Starter layouts shown by SliderPlaceholder when the block is first inserted.
 * Each template seeds three slides so the slider has enough content to demo
 * navigation, autoplay, and transitions immediately after insertion.
 */

import { __ } from '@wordpress/i18n';

const slidePadding = {
	top: 'var:preset|spacing|70',
	bottom: 'var:preset|spacing|70',
	left: 'var:preset|spacing|30',
	right: 'var:preset|spacing|30',
};

function basicSlide(heading, body) {
	return [
		'designsetgo/slide',
		{
			style: { spacing: { padding: slidePadding } },
		},
		[
			[
				'core/heading',
				{ level: 2, content: heading, textAlign: 'center' },
			],
			['core/paragraph', { content: body, align: 'center' }],
		],
	];
}

const sliderTemplates = [
	{
		name: 'blank',
		title: __('Blank', 'designsetgo'),
		description: __('Three empty slides to fill in', 'designsetgo'),
		icon: 'welcome-add-page',
		attributes: {},
		innerBlocks: [
			[
				'designsetgo/slide',
				{ style: { spacing: { padding: slidePadding } } },
			],
			[
				'designsetgo/slide',
				{ style: { spacing: { padding: slidePadding } } },
			],
			[
				'designsetgo/slide',
				{ style: { spacing: { padding: slidePadding } } },
			],
		],
	},
	{
		name: 'hero',
		title: __('Hero', 'designsetgo'),
		description: __(
			'Single full-bleed slides with bold headlines',
			'designsetgo'
		),
		icon: 'cover-image',
		attributes: {
			slidesPerView: 1,
			slidesPerViewTablet: 1,
			slidesPerViewMobile: 1,
			effect: 'fade',
			showArrows: true,
			showDots: true,
			autoplay: true,
			autoplayInterval: 6000,
			loop: true,
		},
		innerBlocks: [
			basicSlide(
				__('Build at the speed of thought', 'designsetgo'),
				__(
					'Compose pages in minutes with a library designed for site builders.',
					'designsetgo'
				)
			),
			basicSlide(
				__('Designed for performance', 'designsetgo'),
				__(
					'Lean markup, optimized assets, and zero jank — out of the box.',
					'designsetgo'
				)
			),
			basicSlide(
				__('Ready when you are', 'designsetgo'),
				__(
					'Drop in your content, customize the look, and ship.',
					'designsetgo'
				)
			),
		],
	},
	{
		name: 'testimonial',
		title: __('Testimonial', 'designsetgo'),
		description: __(
			'Three quote-style slides for social proof',
			'designsetgo'
		),
		icon: 'format-quote',
		attributes: {
			slidesPerView: 1,
			slidesPerViewTablet: 1,
			slidesPerViewMobile: 1,
			effect: 'slide',
			showArrows: true,
			showDots: true,
			autoplay: true,
			autoplayInterval: 7000,
			loop: true,
		},
		innerBlocks: [
			basicSlide(
				__('"This saved us weeks of design work."', 'designsetgo'),
				__('— Jamie L., Product Lead', 'designsetgo')
			),
			basicSlide(
				__(
					'"The block library is exactly what our team needed."',
					'designsetgo'
				),
				__('— Priya R., Marketing Director', 'designsetgo')
			),
			basicSlide(
				__(
					'"Setup took ten minutes. Pages went live the same day."',
					'designsetgo'
				),
				__('— Marcus B., Agency Owner', 'designsetgo')
			),
		],
	},
	{
		name: 'gallery',
		title: __('Gallery', 'designsetgo'),
		description: __(
			'Three-up grid for browsing images or cards',
			'designsetgo'
		),
		icon: 'images-alt2',
		attributes: {
			slidesPerView: 3,
			slidesPerViewTablet: 2,
			slidesPerViewMobile: 1,
			effect: 'slide',
			showArrows: true,
			showDots: false,
			gap: '16px',
		},
		innerBlocks: [
			[
				'designsetgo/slide',
				{ style: { spacing: { padding: slidePadding } } },
				[['core/image', { sizeSlug: 'large' }]],
			],
			[
				'designsetgo/slide',
				{ style: { spacing: { padding: slidePadding } } },
				[['core/image', { sizeSlug: 'large' }]],
			],
			[
				'designsetgo/slide',
				{ style: { spacing: { padding: slidePadding } } },
				[['core/image', { sizeSlug: 'large' }]],
			],
		],
	},
];

export default sliderTemplates;
