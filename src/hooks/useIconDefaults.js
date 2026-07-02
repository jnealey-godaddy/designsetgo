/**
 * useIconDefaults
 *
 * Resolves the theme-level icon defaults configured in theme.json under
 * `settings.custom.designsetgo.*`. Icon blocks leave `iconSize` / `iconStyle`
 * unset to inherit these values; a concrete attribute value is an explicit
 * per-instance override.
 *
 * Size is read from a PER-BLOCK token so blocks with different natural sizes
 * (Icon 48, Icon Button 20, Icon List 32) can be tuned independently, e.g.
 * `settings.custom.designsetgo.iconButton.defaultSize`. Style is a single
 * shared site-wide token (`settings.custom.designsetgo.icon.defaultStyle`).
 *
 * Mirrors the PHP resolution in Icon_Injector::get_icon_defaults() (style)
 * and each block's style.scss token fallback (size) so the editor preview and
 * the frontend agree on the inherited value.
 */

import { useSettings } from '@wordpress/block-editor';

const FALLBACK_STYLE = 'filled';

/**
 * Resolve the effective icon size/style defaults for the current context.
 *
 * @param {Object} [options]              Options.
 * @param {string} [options.sizeKey]      theme.json custom key for the size
 *                                        token (e.g. 'icon', 'iconButton').
 * @param {number} [options.sizeFallback] Natural size when the token is unset.
 * @return {{size: number, style: string}} Resolved icon defaults.
 */
export function useIconDefaults({ sizeKey = 'icon', sizeFallback = 48 } = {}) {
	const [size, style] = useSettings(
		`custom.designsetgo.${sizeKey}.defaultSize`,
		'custom.designsetgo.icon.defaultStyle'
	);

	const resolvedSize = Number.parseInt(size, 10);
	const resolvedStyle =
		style === 'outlined' || style === 'filled' ? style : null;

	return {
		size:
			Number.isFinite(resolvedSize) && resolvedSize > 0
				? resolvedSize
				: sizeFallback,
		style: resolvedStyle || FALLBACK_STYLE,
	};
}
