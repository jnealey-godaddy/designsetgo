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

/**
 * Per-action copy for the shared `value` field.
 *
 * `value` carries a different thing for every action, so the control has to
 * relabel itself or the author is left guessing what to type. Actions absent
 * from this table take no value at all and the field is hidden.
 */
export const ACTION_VALUE_FIELD = {
	toggleClass: {
		label: __('Class name', 'designsetgo'),
		help: __(
			'Without the leading dot. For example: is-open',
			'designsetgo'
		),
	},
	addClass: {
		label: __('Class name', 'designsetgo'),
		help: __(
			'Without the leading dot. For example: is-open',
			'designsetgo'
		),
	},
	removeClass: {
		label: __('Class name', 'designsetgo'),
		help: __(
			'Without the leading dot. For example: is-open',
			'designsetgo'
		),
	},
	setAttribute: {
		label: __('Attribute value', 'designsetgo'),
		help: __('For example: true', 'designsetgo'),
	},
	openModal: {
		label: __('Modal ID', 'designsetgo'),
		help: __('The HTML anchor of the modal block to open.', 'designsetgo'),
	},
	closeModal: {
		label: __('Modal ID', 'designsetgo'),
		help: __(
			'Leave empty to close whichever modal is open.',
			'designsetgo'
		),
	},
	copyToClipboard: {
		label: __('Text to copy', 'designsetgo'),
		help: __("Leave empty to copy the target's own text.", 'designsetgo'),
	},
};

/** Actions that scroll, and so respect the offset field. */
export const OFFSET_ACTIONS = ['scrollTo'];

export const DEFAULT_INTERACTION = {
	id: '',
	trigger: 'click',
	targetMode: 'self',
	targetSelector: '',
	action: 'toggleClass',
	value: '',
	attributeName: '',
	key: '',
	once: false,
	offset: 0,
};
