/**
 * DesignSetGo block collection.
 *
 * DesignSetGo blocks register into the core category of the core block they
 * stand in for (Grid → design next to Columns, Fifty Fifty → media next to
 * Media & Text, and so on). Tools that filter blocks by category, such as AI
 * page builders, then find them next to their core counterparts. This
 * collection keeps a "DesignSetGo" group in the inserter, keyed on the
 * `designsetgo/` namespace.
 *
 * The file keeps its historical name because PHP enqueues it as the
 * `designsetgo-block-category-icon` handle and localizes editor globals
 * (sticky header, integrations) onto it.
 */

import { registerBlockCollection } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const collectionIcon = createElement(
	'svg',
	{
		xmlns: 'http://www.w3.org/2000/svg',
		viewBox: '0 0 24 24',
		width: 24,
		height: 24,
		fill: 'currentColor',
		'aria-hidden': 'true',
		focusable: 'false',
	},
	createElement('path', {
		d: 'M3 3h8v8H3V3zm10 0h8v5h-8V3zm0 7h8v4h-8v-4zM3 13h8v8H3v-8zm10 3h8v5h-8v-5z',
	})
);

registerBlockCollection('designsetgo', {
	title: __('DesignSetGo', 'designsetgo'),
	icon: collectionIcon,
});
