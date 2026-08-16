/**
 * Interaction Layers - Constants
 *
 * Option tables and the canonical interaction shape.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';

export const TRIGGERS = [
	{ value: 'click', label: __('Click', 'designsetgo') },
	{ value: 'hover', label: __('Hover', 'designsetgo') },
	{ value: 'inView', label: __('Scrolls into view', 'designsetgo') },
	{ value: 'exitIntent', label: __('Exit intent', 'designsetgo') },
	{ value: 'keydown', label: __('Key press', 'designsetgo') },
];

export const ACTIONS = [
	{ value: 'toggleClass', label: __('Toggle class', 'designsetgo') },
	{ value: 'addClass', label: __('Add class', 'designsetgo') },
	{ value: 'removeClass', label: __('Remove class', 'designsetgo') },
	{ value: 'setAttribute', label: __('Set attribute', 'designsetgo') },
	{ value: 'scrollTo', label: __('Scroll to', 'designsetgo') },
	{ value: 'openModal', label: __('Open modal', 'designsetgo') },
	{ value: 'closeModal', label: __('Close modal', 'designsetgo') },
	{ value: 'copyToClipboard', label: __('Copy to clipboard', 'designsetgo') },
];

export const TARGET_MODES = [
	{ value: 'self', label: __('This block', 'designsetgo') },
	{ value: 'selector', label: __('CSS selector', 'designsetgo') },
	{ value: 'parent', label: __('Closest ancestor', 'designsetgo') },
];

export const DEFAULT_INTERACTION = {
	id: '',
	trigger: 'click',
	targetMode: 'self',
	targetSelector: '',
	action: 'toggleClass',
	value: '',
	attributeName: '',
	once: false,
	offset: 0,
};
