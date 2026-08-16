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

/**
 * Action group labels, in the order they appear in the picker.
 *
 * The list is long enough that a flat select is hard to scan, so each action
 * declares which group it belongs to.
 */
export const ACTION_GROUPS = [
	{ key: 'visibility', label: __('Visibility', 'designsetgo') },
	{ key: 'classes', label: __('Classes and attributes', 'designsetgo') },
	{ key: 'scroll', label: __('Scrolling', 'designsetgo') },
	{ key: 'blocks', label: __('Blocks', 'designsetgo') },
	{ key: 'media', label: __('Media', 'designsetgo') },
	{ key: 'other', label: __('Other', 'designsetgo') },
];

export const ACTIONS = [
	{ value: 'show', label: __('Show', 'designsetgo'), group: 'visibility' },
	{ value: 'hide', label: __('Hide', 'designsetgo'), group: 'visibility' },
	{
		value: 'toggleVisibility',
		label: __('Show / hide', 'designsetgo'),
		group: 'visibility',
	},
	{
		value: 'toggleClass',
		label: __('Toggle class', 'designsetgo'),
		group: 'classes',
	},
	{
		value: 'addClass',
		label: __('Add class', 'designsetgo'),
		group: 'classes',
	},
	{
		value: 'removeClass',
		label: __('Remove class', 'designsetgo'),
		group: 'classes',
	},
	{
		value: 'setAttribute',
		label: __('Set attribute', 'designsetgo'),
		group: 'classes',
	},
	{
		value: 'removeAttribute',
		label: __('Remove attribute', 'designsetgo'),
		group: 'classes',
	},
	{
		value: 'scrollTo',
		label: __('Scroll to', 'designsetgo'),
		group: 'scroll',
	},
	{
		value: 'scrollToTop',
		label: __('Scroll to top of page', 'designsetgo'),
		group: 'scroll',
	},
	{
		value: 'openModal',
		label: __('Open modal', 'designsetgo'),
		group: 'blocks',
	},
	{
		value: 'closeModal',
		label: __('Close modal', 'designsetgo'),
		group: 'blocks',
	},
	{
		value: 'submitForm',
		label: __('Submit form', 'designsetgo'),
		group: 'blocks',
	},
	{
		value: 'playMedia',
		label: __('Play video or audio', 'designsetgo'),
		group: 'media',
	},
	{
		value: 'pauseMedia',
		label: __('Pause video or audio', 'designsetgo'),
		group: 'media',
	},
	{
		value: 'toggleMedia',
		label: __('Play / pause video or audio', 'designsetgo'),
		group: 'media',
	},
	{
		value: 'copyToClipboard',
		label: __('Copy to clipboard', 'designsetgo'),
		group: 'other',
	},
	{
		value: 'focusTarget',
		label: __('Move focus to', 'designsetgo'),
		group: 'other',
	},
	{
		value: 'dispatchEvent',
		label: __('Fire a custom event', 'designsetgo'),
		group: 'other',
	},
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
	dispatchEvent: {
		label: __('Event name', 'designsetgo'),
		help: __(
			'Fired on the target and allowed to bubble. For example: my-plugin-opened',
			'designsetgo'
		),
	},
};

/**
 * Actions that show or hide their target.
 *
 * Grouped so the runtime can mirror the resulting state onto the trigger as
 * `aria-expanded` without every action having to remember to.
 */
export const VISIBILITY_ACTIONS = ['show', 'hide', 'toggleVisibility'];

export { HIDDEN_CLASS } from './hidden-class';

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
