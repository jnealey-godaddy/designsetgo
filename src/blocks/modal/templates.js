/**
 * Modal Templates
 *
 * Pre-configured modal templates for quick setup.
 * These are used in the template chooser when a modal is first inserted.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';

export const modalTemplates = [
	{
		name: 'blank',
		title: __('Blank', 'designsetgo'),
		description: __('Start with an empty modal', 'designsetgo'),
		icon: 'welcome-add-page',
		attributes: {},
		innerBlocks: [
			[
				'core/heading',
				{
					level: 2,
					placeholder: __('Modal Title', 'designsetgo'),
				},
			],
			[
				'core/paragraph',
				{
					placeholder: __(
						'Add your modal content here…',
						'designsetgo'
					),
				},
			],
		],
	},
	{
		name: 'newsletter',
		title: __('Newsletter Signup', 'designsetgo'),
		description: __('Email collection form with CTA', 'designsetgo'),
		icon: 'email-alt',
		attributes: {
			width: '500px',
			maxWidth: '90vw',
			overlayOpacity: 80,
			animationType: 'slide-up',
			autoTriggerType: 'exitIntent',
			exitIntentSensitivity: 'medium',
			autoTriggerFrequency: 'once',
			cookieDuration: 30,
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
				'designsetgo/form-builder',
				{
					submitButtonText: __('Subscribe', 'designsetgo'),
					submitButtonAlignment: 'center',
					ajaxSubmit: true,
					successMessage: __(
						'Thank you for subscribing!',
						'designsetgo'
					),
				},
				[
					[
						'designsetgo/form-email-field',
						{
							label: __('Email Address', 'designsetgo'),
							placeholder: 'your@email.com',
							required: true,
							fieldName: 'email',
						},
					],
				],
			],
		],
	},
	{
		name: 'announcement',
		title: __('Announcement', 'designsetgo'),
		description: __('Important notice or promo', 'designsetgo'),
		icon: 'megaphone',
		attributes: {
			width: '600px',
			maxWidth: '90vw',
			overlayOpacity: 70,
			animationType: 'zoom',
			autoTriggerType: 'pageLoad',
			autoTriggerDelay: 2,
			autoTriggerFrequency: 'session',
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
				'designsetgo/icon-button',
				{
					text: __('Shop Now', 'designsetgo'),
					fullWidth: true,
					icon: 'cart',
					iconPosition: 'end',
				},
			],
		],
	},
	{
		name: 'video',
		title: __('Video Player', 'designsetgo'),
		description: __('Video embed with optimal sizing', 'designsetgo'),
		icon: 'video-alt3',
		attributes: {
			width: '800px',
			maxWidth: '95vw',
			overlayOpacity: 95,
			overlayColor: '#000000',
			animationType: 'zoom',
			closeButtonPosition: 'top-right',
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
	},
	{
		name: 'image-lightbox',
		title: __('Image Lightbox', 'designsetgo'),
		description: __('Full-screen image display', 'designsetgo'),
		icon: 'format-image',
		attributes: {
			width: 'auto',
			maxWidth: '95vw',
			overlayOpacity: 90,
			overlayColor: '#000000',
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
					content: __('Image caption…', 'designsetgo'),
					align: 'center',
					fontSize: 'small',
				},
			],
		],
	},
	{
		name: 'contact-form',
		title: __('Contact Form', 'designsetgo'),
		description: __('Basic contact form layout', 'designsetgo'),
		icon: 'admin-comments',
		attributes: {
			width: '600px',
			maxWidth: '90vw',
			overlayOpacity: 75,
			animationType: 'slide-up',
		},
		innerBlocks: [
			[
				'core/heading',
				{
					level: 2,
					content: __('Get in Touch', 'designsetgo'),
				},
			],
			[
				'core/paragraph',
				{
					content: __(
						"Have a question? We'd love to hear from you.",
						'designsetgo'
					),
				},
			],
			[
				'designsetgo/form-builder',
				{
					submitButtonText: __('Send Message', 'designsetgo'),
					submitButtonAlignment: 'left',
					ajaxSubmit: true,
					successMessage: __(
						'Thank you! Your message has been sent.',
						'designsetgo'
					),
				},
				[
					[
						'designsetgo/form-text-field',
						{
							label: __('Name', 'designsetgo'),
							placeholder: 'Your name',
							required: true,
							fieldName: 'name',
						},
					],
					[
						'designsetgo/form-email-field',
						{
							label: __('Email', 'designsetgo'),
							placeholder: 'your@email.com',
							required: true,
							fieldName: 'email',
						},
					],
					[
						'designsetgo/form-textarea-field',
						{
							label: __('Message', 'designsetgo'),
							placeholder: 'Your message...',
							required: true,
							fieldName: 'message',
						},
					],
				],
			],
		],
	},
	{
		name: 'product-details',
		title: __('Product Details', 'designsetgo'),
		description: __('Product showcase with image', 'designsetgo'),
		icon: 'cart',
		attributes: {
			width: '900px',
			maxWidth: '95vw',
			overlayOpacity: 85,
			animationType: 'slide-up',
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
							width: '50%',
						},
						[
							[
								'core/image',
								{
									url: '',
									alt: '',
									sizeSlug: 'large',
								},
							],
						],
					],
					[
						'core/column',
						{
							width: '50%',
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
										'Product description and key features go here…',
										'designsetgo'
									),
								},
							],
							[
								'core/paragraph',
								{
									content: __(
										'<strong style="font-size: 1.5em; color: #2d7a4c;">$99.00</strong>',
										'designsetgo'
									),
								},
							],
							[
								'designsetgo/icon-button',
								{
									text: __('Add to Cart', 'designsetgo'),
									icon: 'cart',
									iconPosition: 'start',
								},
							],
						],
					],
				],
			],
		],
	},
	{
		name: 'cookie-notice',
		title: __('Cookie Notice', 'designsetgo'),
		description: __('GDPR-style cookie consent', 'designsetgo'),
		icon: 'shield',
		attributes: {
			width: '500px',
			maxWidth: '95vw',
			overlayOpacity: 50,
			animationType: 'slide-up',
			closeOnBackdrop: false,
			closeOnEsc: false,
			showCloseButton: false,
			autoTriggerType: 'pageLoad',
			autoTriggerDelay: 1,
			autoTriggerFrequency: 'once',
			cookieDuration: 365,
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
							url: '#privacy-policy',
						},
					],
					[
						'designsetgo/icon-button',
						{
							text: __('Accept', 'designsetgo'),
							icon: 'yes',
							iconPosition: 'end',
							modalCloseId: 'true',
						},
					],
				],
			],
		],
	},
	{
		name: 'promo-split',
		title: __('Promo Split', 'designsetgo'),
		description: __('Two-column promo with image', 'designsetgo'),
		icon: 'megaphone',
		attributes: {
			width: '800px',
			maxWidth: '90vw',
			overlayOpacity: 80,
			overlayBlur: 8,
			animationType: 'slide-up',
			autoTriggerType: 'exitIntent',
			autoTriggerDelay: 1900,
			autoTriggerFrequency: 'always',
			exitIntentSensitivity: 'medium',
			closeButtonPosition: 'inside-top-right',
			closeButtonIconColor: '#ffffff',
			backgroundColor: 'accent-1',
			style: {
				spacing: {
					padding: {
						top: '0',
						bottom: '0',
						left: '0',
						right: '0',
					},
				},
			},
		},
		innerBlocks: [
			[
				'designsetgo/grid',
				{
					desktopColumns: 2,
					alignItems: 'center',
					style: {
						spacing: {
							blockGap: 'var(--wp--preset--spacing--50)',
							padding: {
								top: '0',
								bottom: '0',
								left: '0',
								right: '0',
							},
						},
					},
				},
				[
					[
						'designsetgo/section',
						{
							style: {
								spacing: {
									padding: {
										top: 'var(--wp--preset--spacing--50)',
										bottom: 'var(--wp--preset--spacing--50)',
										left: 'var(--wp--preset--spacing--50)',
										right: 'var(--wp--preset--spacing--50)',
									},
								},
							},
							layout: {
								type: 'flex',
								orientation: 'vertical',
								verticalAlignment: 'center',
								justifyContent: 'left',
							},
						},
						[
							[
								'core/heading',
								{
									level: 2,
									content: __('Special Offer', 'designsetgo'),
								},
							],
							[
								'core/paragraph',
								{
									content: __(
										"Here's a message about the special offer",
										'designsetgo'
									),
								},
							],
							[
								'designsetgo/icon-button',
								{
									text: __('Download', 'designsetgo'),
									icon: 'download',
									justification: 'left',
								},
							],
						],
					],
					[
						'designsetgo/section',
						{
							style: {
								spacing: {
									padding: {
										top: 'var(--wp--preset--spacing--50)',
										bottom: 'var(--wp--preset--spacing--50)',
										left: 'var(--wp--preset--spacing--30)',
										right: 'var(--wp--preset--spacing--30)',
									},
								},
							},
						},
						[
							[
								'core/spacer',
								{
									height: '274px',
									style: {
										layout: {
											flexSize: '274px',
											selfStretch: 'fixed',
										},
									},
								},
							],
						],
					],
				],
			],
		],
	},
];

export default modalTemplates;
