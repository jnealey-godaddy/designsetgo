/**
 * Mirror core Button style variations onto Icon Button.
 *
 * The Icon Button renders with the core button classes
 * (`wp-block-button wp-block-button__link wp-element-button`) so it already
 * inherits theme.json button styling. This makes the same *block style
 * variations* that core Button registers in JS — Fill, Outline, and any a
 * plugin registers for `core/button` — available on `designsetgo/icon-button`
 * too, so the editor Styles panel lists them and adds the matching
 * `is-style-{slug}` class on selection. The visual styling then flows from
 * core's own variation CSS (which targets `.wp-block-button__link.is-style-*`).
 *
 * Core Button's Fill/Outline styles are registered in JS (not the PHP block
 * styles registry), so this mirror must run in the editor rather than PHP.
 *
 * Registration order between core/button, this block, and their styles is not
 * guaranteed. Rather than read core's styles once (which silently mirrors
 * nothing whenever it runs before core/button's styles are registered), watch
 * the blocks store and (re)mirror idempotently whenever it changes, so the
 * styles always appear regardless of load order.
 *
 * @since 2.4.0
 */

import { registerBlockStyle, store as blocksStore } from '@wordpress/blocks';
import { select, subscribe } from '@wordpress/data';

const TARGET_BLOCK = 'designsetgo/icon-button';
const SOURCE_BLOCK = 'core/button';

/**
 * Register any `core/button` block styles not already on the Icon Button.
 * Idempotent: a no-op once every source style has been mirrored, and it never
 * clobbers a style the Icon Button defines itself.
 */
export function mirrorCoreButtonStyles() {
	const sel = select(blocksStore);

	// getBlockStyles needs the target registered, and there's nothing to copy
	// until core/button's styles exist. Either can happen after this runs, so
	// bail quietly — the subscription will call again once they do.
	if (!sel.getBlockType(TARGET_BLOCK)) {
		return;
	}
	const sourceStyles = sel.getBlockStyles(SOURCE_BLOCK) || [];
	if (!sourceStyles.length) {
		return;
	}

	const existing = new Set(
		(sel.getBlockStyles(TARGET_BLOCK) || []).map((style) => style.name)
	);

	sourceStyles.forEach((style) => {
		if (existing.has(style.name)) {
			return;
		}
		registerBlockStyle(TARGET_BLOCK, {
			name: style.name,
			label: style.label,
			...(style.isDefault ? { isDefault: true } : {}),
		});
	});
}

/**
 * Mirror now (covers the already-registered case) and on every subsequent
 * blocks-store change (covers styles/blocks that register later). Idempotent,
 * so the recurring call settles to a no-op once everything is mirrored.
 *
 * @return {Function} Unsubscribe handle (used by tests; production leaves it
 *                     running for the editor's lifetime).
 */
export function startMirroringButtonStyles() {
	mirrorCoreButtonStyles();
	return subscribe(mirrorCoreButtonStyles);
}
