/**
 * Mirror core Button style variations onto Icon Button.
 *
 * The Icon Button renders with the core button classes
 * (`wp-block-button wp-block-button__link wp-element-button`) so it already
 * inherits theme.json button styling. This makes the same *block style
 * variations* that core Button offers — Fill, Outline, and any a theme or
 * plugin registers for `core/button` — available on `designsetgo/icon-button`
 * too, so the editor Styles panel lists them and adds the matching
 * `is-style-{slug}` class on selection. The visual styling then flows from
 * core's own variation CSS (which targets `.wp-block-button__link.is-style-*`)
 * and, for theme.json-defined variations, from
 * {@see \DesignSetGo\Icon_Button_Styles} on the PHP side.
 *
 * Core Button's Fill/Outline styles are registered in JS (not the PHP block
 * styles registry), so this mirror must run in the editor rather than PHP.
 *
 * @since 2.4.0
 */

import domReady from '@wordpress/dom-ready';
import { registerBlockStyle, store as blocksStore } from '@wordpress/blocks';
import { select } from '@wordpress/data';

const TARGET_BLOCK = 'designsetgo/icon-button';

/**
 * Copy every block style registered for `core/button` onto the Icon Button.
 * Runs after block registration (domReady) so core's styles are present.
 */
domReady(() => {
	const buttonStyles =
		select(blocksStore).getBlockStyles('core/button') || [];

	const existing = new Set(
		(select(blocksStore).getBlockStyles(TARGET_BLOCK) || []).map(
			(style) => style.name
		)
	);

	buttonStyles.forEach((style) => {
		// Don't re-register (avoids the "already registered" console warning)
		// and never clobber a style the Icon Button defines itself.
		if (existing.has(style.name)) {
			return;
		}

		registerBlockStyle(TARGET_BLOCK, {
			name: style.name,
			label: style.label,
			...(style.isDefault ? { isDefault: true } : {}),
		});
	});
});
