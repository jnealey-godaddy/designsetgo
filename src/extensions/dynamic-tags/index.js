/**
 * Dynamic Tags extension — boot.
 *
 * Adds a per-attribute Dynamic Tag picker to the Inspector and the
 * inline toolbar of every core block that supports the WordPress Block
 * Bindings API, plus JS-side bindings sources so the editor canvas
 * shows the resolved value live (rather than the source label as a
 * placeholder).
 *
 * Honours the admin Block Manager's enabledExtensions allowlist —
 * if `dynamic-tags` is explicitly excluded by the site, none of the
 * editor UI or live-preview bindings register.
 */
const enabled =
	typeof window !== 'undefined' &&
	window.dsgoSettings?.enabledExtensions !== undefined
		? // Empty list = all extensions enabled (mirrors the PHP default).
			!window.dsgoSettings.enabledExtensions.length ||
			window.dsgoSettings.enabledExtensions.includes('dynamic-tags')
		: true;

if (enabled) {
	require('./filters.js');
	require('./register-bindings-sources.js');
}
