/**
 * Dynamic Tags extension — boot.
 *
 * Adds a per-attribute Dynamic Tag picker to the Inspector and the
 * inline toolbar of every core block that supports the WordPress Block
 * Bindings API, plus JS-side bindings sources so the editor canvas
 * shows the resolved value live (rather than the source label as a
 * placeholder).
 *
 * Honours the admin Block Manager's disabledExtensions list — if the
 * site switched `dynamic-tags` off, none of the editor UI or
 * live-preview bindings register.
 */
const enabled =
	typeof window === 'undefined' ||
	!window.dsgoSettings?.disabledExtensions?.includes('dynamic-tags');

if (enabled) {
	require('./filters.js');
	require('./register-bindings-sources.js');
}
