/**
 * Modal Block Variations
 *
 * Pre-configured modal patterns for common use cases.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';

const variations = [
	{
		name: 'newsletter',
		title: __('Newsletter Signup', 'designsetgo'),
		description: __(
			'Modal optimized for newsletter signup forms with exit intent trigger.',
			'designsetgo'
		),
		icon: 'email',
		attributes: {
			width: '500px',
			maxWidth: '90vw',
			height: 'auto',
			maxHeight: '90vh',
			autoTriggerType: 'exitIntent',
			exitIntentSensitivity: 'medium',
			exitIntentMinTime: 10,
			autoTriggerFrequency: 'once',
			cookieDuration: 30,
			overlayOpacity: 80,
			animationType: 'slide-up',
		},
		innerBlocks: [
			[
				'core/heading',
				{
					level: 2,
					content: __('Subscribe to Our Newsletter', 'designsetgo'),
					textAlign: 'center',
				},
			],
			[
				'core/paragraph',
				{
					content: __(
						'Get the latest updates and exclusive content delivered to your inbox.',
						'designsetgo'
					),
					align: 'center',
				},
			],
			[
				'core/paragraph',
				{
					content: __(
						'<strong>Email:</strong> <input type="email" placeholder="your@email.com" style="width: 100%; padding: 8px; margin: 8px 0;" />',
						'designsetgo'
					),
				},
			],
			[
				'core/buttons',
				{
					layout: { type: 'flex', justifyContent: 'center' },
				},
				[
					[
						'core/button',
						{
							text: __('Subscribe', 'designsetgo'),
							width: 100,
						},
					],
				],
			],
		],
		scope: ['block'],
	},
	{
		name: 'video',
		title: __('Video Player', 'designsetgo'),
		description: __(
			'Modal optimized for video content with 16:9 aspect ratio.',
			'designsetgo'
		),
		icon: 'video-alt3',
		attributes: {
			width: '800px',
			maxWidth: '95vw',
			height: 'auto',
			maxHeight: '95vh',
			overlayOpacity: 95,
			animationType: 'zoom',
			closeButtonPosition: 'outside-top-right',
		},
		innerBlocks: [
			[
				'core/embed',
				{
					url: '',
					type: 'video',
					providerNameSlug: 'youtube',
					responsive: true,
					className: 'wp-embed-aspect-16-9',
				},
			],
		],
		scope: ['block'],
	},
	{
		name: 'lightbox',
		title: __('Image Lightbox', 'designsetgo'),
		description: __(
			'Full-screen modal for displaying images with minimal chrome.',
			'designsetgo'
		),
		icon: 'format-image',
		attributes: {
			width: 'auto',
			maxWidth: '95vw',
			height: 'auto',
			maxHeight: '95vh',
			overlayOpacity: 90,
			overlayBlur: 5,
			animationType: 'fade',
			closeButtonPosition: 'inside-top-right',
			closeButtonSize: 32,
			closeButtonIconColor: '#ffffff',
			closeButtonBgColor: 'rgba(0, 0, 0, 0.5)',
		},
		innerBlocks: [
			[
				'core/image',
				{
					url: '',
					alt: '',
					sizeSlug: 'full',
					linkDestination: 'none',
				},
			],
			[
				'core/paragraph',
				{
					content: __('Image caption goes here…', 'designsetgo'),
					align: 'center',
					fontSize: 'small',
				},
			],
		],
		scope: ['block'],
	},
	{
		name: 'announcement',
		title: __('Announcement / Promo', 'designsetgo'),
		description: __(
			'Eye-catching modal for announcements and promotional content.',
			'designsetgo'
		),
		icon: 'megaphone',
		attributes: {
			width: '600px',
			maxWidth: '90vw',
			height: 'auto',
			autoTriggerType: 'pageLoad',
			autoTriggerDelay: 2000,
			autoTriggerFrequency: 'session',
			overlayOpacity: 70,
			animationType: 'zoom',
		},
		innerBlocks: [
			[
				'core/heading',
				{
					level: 2,
					content: __('🎉 Special Offer!', 'designsetgo'),
					textAlign: 'center',
				},
			],
			[
				'core/paragraph',
				{
					content: __(
						'Get 20% off your first purchase. Use code <strong>WELCOME20</strong> at checkout.',
						'designsetgo'
					),
					align: 'center',
					fontSize: 'medium',
				},
			],
			[
				'core/buttons',
				{
					layout: { type: 'flex', justifyContent: 'center' },
				},
				[
					[
						'core/button',
						{
							text: __('Shop Now', 'designsetgo'),
							width: 100,
						},
					],
				],
			],
		],
		scope: ['block'],
	},
	{
		name: 'cookie-notice',
		title: __('Cookie Notice', 'designsetgo'),
		description: __(
			'Modal for cookie consent and privacy notices.',
			'designsetgo'
		),
		icon: 'shield',
		attributes: {
			width: '500px',
			maxWidth: '95vw',
			height: 'auto',
			autoTriggerType: 'pageLoad',
			autoTriggerDelay: 1000,
			autoTriggerFrequency: 'once',
			cookieDuration: 365,
			overlayOpacity: 50,
			animationType: 'slide-up',
			closeOnBackdrop: false,
			closeOnEsc: false,
			showCloseButton: false,
		},
		innerBlocks: [
			[
				'core/heading',
				{
					level: 3,
					content: __('🍪 Cookie Notice', 'designsetgo'),
				},
			],
			[
				'core/paragraph',
				{
					content: __(
						'We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.',
						'designsetgo'
					),
					fontSize: 'small',
				},
			],
			[
				'core/buttons',
				{
					layout: { type: 'flex', justifyContent: 'left' },
				},
				[
					[
						'designsetgo/icon-button',
						{
							text: __('Learn More', 'designsetgo'),
							icon: 'info',
							iconPosition: 'start',
						},
					],
					[
						'designsetgo/icon-button',
						{
							text: __('Accept', 'designsetgo'),
							icon: 'yes',
							iconPosition: 'end',
						},
					],
				],
			],
		],
		scope: ['block'],
	},
	{
		name: 'gallery-image',
		title: __('Image Gallery Item', 'designsetgo'),
		description: __(
			'Modal optimized for image galleries with navigation controls.',
			'designsetgo'
		),
		icon: 'images-alt2',
		attributes: {
			width: 'auto',
			maxWidth: '95vw',
			height: 'auto',
			maxHeight: '95vh',
			overlayOpacity: 95,
			overlayBlur: 3,
			animationType: 'fade',
			closeButtonPosition: 'top-right',
			closeButtonSize: 32,
			closeButtonIconColor: '#ffffff',
			closeButtonBgColor: 'rgba(0, 0, 0, 0.7)',
			galleryGroupId: 'image-gallery',
			galleryIndex: 0,
			showGalleryNavigation: true,
			navigationStyle: 'arrows',
			navigationPosition: 'sides',
		},
		innerBlocks: [
			[
				'core/image',
				{
					url: '',
					alt: '',
					sizeSlug: 'full',
					linkDestination: 'none',
				},
			],
			[
				'core/paragraph',
				{
					content: __(
						'Add image caption or description…',
						'designsetgo'
					),
					align: 'center',
					style: {
						color: {
							text: '#ffffff',
						},
					},
				},
			],
		],
		scope: ['block'],
	},
	{
		name: 'gallery-product',
		title: __('Product Gallery', 'designsetgo'),
		description: __(
			'Modal for product image galleries with details.',
			'designsetgo'
		),
		icon: 'cart',
		attributes: {
			width: '900px',
			maxWidth: '95vw',
			height: 'auto',
			maxHeight: '90vh',
			overlayOpacity: 85,
			animationType: 'slide-up',
			closeButtonPosition: 'inside-top-right',
			galleryGroupId: 'product-gallery',
			galleryIndex: 0,
			showGalleryNavigation: true,
			navigationStyle: 'arrows',
			navigationPosition: 'sides',
		},
		innerBlocks: [
			[
				'core/columns',
				{
					verticalAlignment: 'center',
				},
				[
					[
						'core/column',
						{
							width: '60%',
						},
						[
							[
								'core/image',
								{
									url: '',
									alt: '',
									sizeSlug: 'large',
									linkDestination: 'none',
								},
							],
						],
					],
					[
						'core/column',
						{
							width: '40%',
						},
						[
							[
								'core/heading',
								{
									level: 3,
									content: __('Product Name', 'designsetgo'),
								},
							],
							[
								'core/paragraph',
								{
									content: __(
										'Product description and details go here…',
										'designsetgo'
									),
								},
							],
							[
								'core/buttons',
								{},
								[
									[
										'core/button',
										{
											text: __(
												'View Product',
												'designsetgo'
											),
										},
									],
								],
							],
						],
					],
				],
			],
		],
		scope: ['block'],
	},
	{
		name: 'gallery-portfolio',
		title: __('Portfolio Item', 'designsetgo'),
		description: __(
			'Modal for portfolio galleries with project details.',
			'designsetgo'
		),
		icon: 'portfolio',
		attributes: {
			width: '1000px',
			maxWidth: '95vw',
			height: 'auto',
			maxHeight: '90vh',
			overlayOpacity: 90,
			animationType: 'zoom',
			closeButtonPosition: 'top-right',
			closeButtonSize: 28,
			closeButtonIconColor: '#ffffff',
			closeButtonBgColor: 'rgba(0, 0, 0, 0.6)',
			galleryGroupId: 'portfolio',
			galleryIndex: 0,
			showGalleryNavigation: true,
			navigationStyle: 'chevrons',
			navigationPosition: 'bottom',
		},
		innerBlocks: [
			[
				'core/image',
				{
					url: '',
					alt: '',
					sizeSlug: 'large',
					linkDestination: 'none',
				},
			],
			[
				'core/heading',
				{
					level: 2,
					content: __('Project Title', 'designsetgo'),
					textAlign: 'center',
				},
			],
			[
				'core/paragraph',
				{
					content: __(
						'Project description, technologies used, and key highlights…',
						'designsetgo'
					),
					align: 'center',
				},
			],
			[
				'core/buttons',
				{
					layout: { type: 'flex', justifyContent: 'center' },
				},
				[
					[
						'core/button',
						{
							text: __('View Live Site', 'designsetgo'),
						},
					],
					[
						'core/button',
						{
							text: __('View Case Study', 'designsetgo'),
							className: 'is-style-outline',
						},
					],
				],
			],
		],
		scope: ['block'],
	},
	{
		name: 'gallery-team',
		title: __('Team Member Gallery', 'designsetgo'),
		description: __(
			'Modal for team member galleries with bio and contact info.',
			'designsetgo'
		),
		icon: 'groups',
		attributes: {
			width: '700px',
			maxWidth: '90vw',
			height: 'auto',
			maxHeight: '90vh',
			overlayOpacity: 75,
			animationType: 'slide-down',
			closeButtonPosition: 'inside-top-right',
			galleryGroupId: 'team',
			galleryIndex: 0,
			showGalleryNavigation: true,
			navigationStyle: 'text',
			navigationPosition: 'bottom',
		},
		innerBlocks: [
			[
				'core/columns',
				{
					verticalAlignment: 'top',
				},
				[
					[
						'core/column',
						{
							width: '35%',
						},
						[
							[
								'core/image',
								{
									url: '',
									alt: '',
									sizeSlug: 'medium',
									linkDestination: 'none',
									className: 'is-style-rounded',
								},
							],
						],
					],
					[
						'core/column',
						{
							width: '65%',
						},
						[
							[
								'core/heading',
								{
									level: 3,
									content: __(
										'Team Member Name',
										'designsetgo'
									),
								},
							],
							[
								'core/paragraph',
								{
									content: __(
										'<strong>Position / Title</strong>',
										'designsetgo'
									),
									style: {
										color: {
											text: '#666666',
										},
									},
								},
							],
							[
								'core/paragraph',
								{
									content: __(
										'Brief bio and background information about the team member…',
										'designsetgo'
									),
								},
							],
							[
								'core/paragraph',
								{
									content: __(
										'📧 email@example.com<br>🔗 linkedin.com/in/profile',
										'designsetgo'
									),
									fontSize: 'small',
								},
							],
						],
					],
				],
			],
		],
		scope: ['block'],
	},
	{
		name: 'off-canvas',
		title: __('Off-Canvas Panel', 'designsetgo'),
		description: __(
			'A panel that slides in from the edge of the screen. Useful for menus, filters, and carts.',
			'designsetgo'
		),
		icon: 'align-right',
		attributes: {
			displayMode: 'panel',
			panelEdge: 'right',
			panelSize: '24rem',
			// 'fade' rather than 'none': the panel's slide is a transform on
			// the dialog, and dsgo-modal--animation-none sets
			// `transition: none !important` on that same element, which would
			// suppress it. 'fade' only animates opacity, so the two compose.
			animationType: 'fade',
			overlayOpacity: 40,
			closeOnBackdrop: true,
			closeOnEsc: true,
			showCloseButton: true,
			// Outside positions sit at -12px, which a screen-edge panel clips.
			closeButtonPosition: 'inside-top-right',
		},
		innerBlocks: [
			[
				'core/heading',
				{
					level: 2,
					content: __('Menu', 'designsetgo'),
				},
			],
			['core/paragraph', { content: '' }],
		],
		isActive: ['displayMode'],
		scope: ['inserter', 'transform'],
	},
];

export default variations;
