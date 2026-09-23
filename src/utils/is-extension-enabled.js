/**
 * Extension toggle check
 *
 * @package
 */

/**
 * Whether the site has left an extension switched on.
 *
 * Reads the Blocks & Extensions screen's `disabled_extensions` setting,
 * localized as `window.dsgoSettings.disabledExtensions`. An extension is on
 * unless it is listed, so one added in a later release starts enabled.
 *
 * Extensions gate only their editor controls on this. Their attributes and
 * saved markup stay registered, so content already built with a disabled
 * extension still validates and renders as saved.
 *
 * @param {...string} names Extension names, e.g. 'custom-css'. With several,
 *                          every one must be enabled.
 * @return {boolean} Whether all of them are enabled.
 */
export function isExtensionEnabled(...names) {
	const disabled =
		(typeof window !== 'undefined' &&
			window.dsgoSettings?.disabledExtensions) ||
		[];

	return names.every((name) => !disabled.includes(name));
}
