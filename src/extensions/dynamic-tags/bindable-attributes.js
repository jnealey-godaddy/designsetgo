/**
 * Allowlist of bindable attributes per core block, per the WordPress
 * Block Bindings API. Keep in sync with WP core — extending an
 * unsupported attribute would write bindings that core silently ignores.
 *
 * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/
 */
export const BINDABLE_ATTRIBUTES = {
	'core/paragraph': [
		{ attribute: 'content', returns: [ 'text' ], label: 'Content' },
	],
	'core/heading': [
		{ attribute: 'content', returns: [ 'text' ], label: 'Content' },
	],
	'core/image': [
		{ attribute: 'url', returns: [ 'image', 'url' ], label: 'Image URL', subkey: 'url' },
		{ attribute: 'id', returns: [ 'image', 'number' ], label: 'Attachment ID', subkey: 'id' },
		{ attribute: 'alt', returns: [ 'text' ], label: 'Alt text', subkey: 'alt' },
		{ attribute: 'title', returns: [ 'text' ], label: 'Title', subkey: 'title' },
	],
	'core/button': [
		{ attribute: 'url', returns: [ 'url' ], label: 'URL' },
		{ attribute: 'text', returns: [ 'text' ], label: 'Text' },
		{ attribute: 'linkTarget', returns: [ 'text' ], label: 'Link target' },
		{ attribute: 'rel', returns: [ 'text' ], label: 'Rel' },
	],
	'core/post-date': [
		{ attribute: 'datetime', returns: [ 'date', 'text' ], label: 'Date' },
	],
};

export function getBindableAttributes( blockName ) {
	return BINDABLE_ATTRIBUTES[ blockName ] || null;
}
