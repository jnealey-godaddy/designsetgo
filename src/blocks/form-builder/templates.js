/**
 * Form Builder Templates
 *
 * Preset structures surfaced in the first-insert placeholder.
 * Each template seeds its own innerBlocks and any notification/messaging
 * defaults appropriate for the use case.
 */

import { __ } from '@wordpress/i18n';

export const formBuilderTemplates = [
	{
		name: 'blank',
		title: __('Blank', 'designsetgo'),
		description: __('Start with a single text field', 'designsetgo'),
		icon: 'welcome-add-page',
		attributes: {},
		innerBlocks: [
			[
				'designsetgo/form-text-field',
				{
					label: __('Name', 'designsetgo'),
					fieldName: 'name',
					required: true,
				},
			],
		],
	},
	{
		name: 'contact',
		title: __('Contact', 'designsetgo'),
		description: __(
			'Name, email, and message — the classic contact form',
			'designsetgo'
		),
		icon: 'email',
		attributes: {
			submitButtonText: __('Send Message', 'designsetgo'),
			successMessage: __(
				"Thanks — we'll be in touch shortly.",
				'designsetgo'
			),
			enableEmail: true,
			emailSubject: __('New contact form submission', 'designsetgo'),
			emailReplyTo: 'email',
		},
		innerBlocks: [
			[
				'designsetgo/form-text-field',
				{
					label: __('Name', 'designsetgo'),
					fieldName: 'name',
					required: true,
				},
			],
			[
				'designsetgo/form-email-field',
				{
					label: __('Email', 'designsetgo'),
					fieldName: 'email',
					required: true,
				},
			],
			[
				'designsetgo/form-textarea-field',
				{
					label: __('Message', 'designsetgo'),
					fieldName: 'message',
					required: true,
				},
			],
		],
	},
	{
		name: 'newsletter',
		title: __('Newsletter', 'designsetgo'),
		description: __(
			'Single email field with an inline subscribe button',
			'designsetgo'
		),
		icon: 'email-alt',
		attributes: {
			submitButtonText: __('Subscribe', 'designsetgo'),
			submitButtonPosition: 'inline',
			successMessage: __('Thanks for subscribing!', 'designsetgo'),
			enableEmail: true,
			emailSubject: __('New newsletter signup', 'designsetgo'),
			emailReplyTo: 'email',
		},
		innerBlocks: [
			[
				'designsetgo/form-email-field',
				{
					label: __('Email Address', 'designsetgo'),
					fieldName: 'email',
					required: true,
					placeholder: __('you@example.com', 'designsetgo'),
				},
			],
		],
	},
	{
		name: 'event-registration',
		title: __('Event Registration', 'designsetgo'),
		description: __(
			'Name, email, phone, and number of guests',
			'designsetgo'
		),
		icon: 'calendar-alt',
		attributes: {
			submitButtonText: __('Register', 'designsetgo'),
			successMessage: __(
				"You're on the list — check your inbox for details.",
				'designsetgo'
			),
			enableEmail: true,
			emailSubject: __('New event registration', 'designsetgo'),
			emailReplyTo: 'email',
		},
		innerBlocks: [
			[
				'designsetgo/form-text-field',
				{
					label: __('Full Name', 'designsetgo'),
					fieldName: 'name',
					required: true,
				},
			],
			[
				'designsetgo/form-email-field',
				{
					label: __('Email', 'designsetgo'),
					fieldName: 'email',
					required: true,
				},
			],
			[
				'designsetgo/form-phone-field',
				{
					label: __('Phone', 'designsetgo'),
					fieldName: 'phone',
				},
			],
			[
				'designsetgo/form-number-field',
				{
					label: __('Number of Guests', 'designsetgo'),
					fieldName: 'guests',
					min: 1,
					max: 10,
					defaultValue: 1,
				},
			],
		],
	},
	{
		name: 'lead-capture',
		title: __('Lead Capture', 'designsetgo'),
		description: __(
			'Name, work email, company, and phone for B2B leads',
			'designsetgo'
		),
		icon: 'businessman',
		attributes: {
			submitButtonText: __('Get in Touch', 'designsetgo'),
			successMessage: __(
				"Thanks — we'll reach out shortly.",
				'designsetgo'
			),
			enableEmail: true,
			emailSubject: __('New lead submission', 'designsetgo'),
			emailReplyTo: 'email',
		},
		innerBlocks: [
			[
				'designsetgo/form-text-field',
				{
					label: __('Full Name', 'designsetgo'),
					fieldName: 'name',
					required: true,
				},
			],
			[
				'designsetgo/form-email-field',
				{
					label: __('Work Email', 'designsetgo'),
					fieldName: 'email',
					required: true,
				},
			],
			[
				'designsetgo/form-text-field',
				{
					label: __('Company', 'designsetgo'),
					fieldName: 'company',
					required: true,
				},
			],
			[
				'designsetgo/form-phone-field',
				{
					label: __('Phone', 'designsetgo'),
					fieldName: 'phone',
				},
			],
		],
	},
];
