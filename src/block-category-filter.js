/**
 * Custom icon for the DesignSetGo block category.
 *
 * The category itself is registered server-side via the `block_categories_all`
 * filter (see includes/class-plugin.php). PHP only supports a Dashicon slug
 * for the category icon, so we override with a branded SVG here.
 */

import domReady from '@wordpress/dom-ready';
import { dispatch } from '@wordpress/data';
import { createElement } from '@wordpress/element';

const CATEGORY_SLUG = 'designsetgo';

const categoryIcon = createElement(
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

domReady(() => {
	const store = dispatch('core/blocks');
	if (store && typeof store.updateCategory === 'function') {
		store.updateCategory(CATEGORY_SLUG, { icon: categoryIcon });
	}
});
