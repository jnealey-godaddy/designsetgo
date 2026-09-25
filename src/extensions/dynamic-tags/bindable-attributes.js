import { __ } from '@wordpress/i18n';

/**
 * Allowlist of bindable attributes per core block, per the WordPress
 * Block Bindings API. Keep in sync with WP core — extending an
 * unsupported attribute would write bindings that core silently ignores.
 *
 * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/
 */
export const BINDABLE_ATTRIBUTES = {
	'core/paragraph': [
		{
			attribute: 'content',
			returns: ['text'],
			label: __('Content', 'designsetgo'),
		},
	],
	'core/heading': [
		{
			attribute: 'content',
			returns: ['text'],
			label: __('Content', 'designsetgo'),
		},
	],
	'core/image': [
		{
			attribute: 'url',
			returns: ['image', 'url'],
			label: __('Image URL', 'designsetgo'),
			subkey: 'url',
		},
		{
			attribute: 'id',
			returns: ['image', 'number'],
			label: __('Attachment ID', 'designsetgo'),
			subkey: 'id',
		},
		{
			attribute: 'alt',
			returns: ['text'],
			label: __('Alt text', 'designsetgo'),
			subkey: 'alt',
		},
		{
			attribute: 'title',
			returns: ['text'],
			label: __('Title', 'designsetgo'),
			subkey: 'title',
		},
	],
	'core/button': [
		{ attribute: 'url', returns: ['url'], label: __('URL', 'designsetgo') },
		{
			attribute: 'text',
			returns: ['text'],
			label: __('Text', 'designsetgo'),
		},
		{
			attribute: 'linkTarget',
			returns: ['text'],
			label: __('Link target', 'designsetgo'),
		},
		{
			attribute: 'rel',
			returns: ['text'],
			label: __('Rel', 'designsetgo'),
		},
	],
	'core/post-date': [
		{
			attribute: 'datetime',
			returns: ['date', 'text'],
			label: __('Date', 'designsetgo'),
		},
	],
	// DesignSetGo blocks — keep in step with DEFAULT_SUPPORTED_ATTRIBUTES in
	// includes/bindings/class-block-bindings-support.php, which opts them in
	// server-side and places bound values in the markup.
	'designsetgo/heading-segment': [
		{
			attribute: 'content',
			returns: ['text'],
			label: __('Content', 'designsetgo'),
		},
	],
	'designsetgo/accordion-item': [
		{
			attribute: 'title',
			returns: ['text'],
			label: __('Title', 'designsetgo'),
		},
	],
	'designsetgo/modal-trigger': [
		{
			attribute: 'text',
			returns: ['text'],
			label: __('Text', 'designsetgo'),
		},
	],
	'designsetgo/icon-button': [
		{
			attribute: 'text',
			returns: ['text'],
			label: __('Text', 'designsetgo'),
		},
	],
	'designsetgo/breadcrumbs': [
		{
			attribute: 'homeText',
			returns: ['text'],
			label: __('Home text', 'designsetgo'),
		},
		{
			attribute: 'prefixText',
			returns: ['text'],
			label: __('Prefix text', 'designsetgo'),
		},
	],
	'designsetgo/query-pagination': [
		{
			attribute: 'labelLoadMore',
			returns: ['text'],
			label: __('Load more label', 'designsetgo'),
		},
		{
			attribute: 'labelLoading',
			returns: ['text'],
			label: __('Loading label', 'designsetgo'),
		},
		{
			attribute: 'buttonLabelWhenPaused',
			returns: ['text'],
			label: __('Paused button label', 'designsetgo'),
		},
	],
	'designsetgo/star-rating': [
		{
			attribute: 'rating',
			returns: ['number'],
			label: __('Rating', 'designsetgo'),
		},
		{
			attribute: 'ratingCount',
			returns: ['number'],
			label: __('Rating count', 'designsetgo'),
		},
	],
};

export function getBindableAttributes(blockName) {
	return BINDABLE_ATTRIBUTES[blockName] || null;
}
