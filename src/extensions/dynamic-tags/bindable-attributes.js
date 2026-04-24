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
};

export function getBindableAttributes(blockName) {
	return BINDABLE_ATTRIBUTES[blockName] || null;
}
