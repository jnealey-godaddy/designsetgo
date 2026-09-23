/**
 * Dynamic Tags extension — boot.
 *
 * Adds a per-attribute Dynamic Tag picker to the Inspector and the
 * inline toolbar of every core block that supports the WordPress Block
 * Bindings API, plus JS-side bindings sources so the editor canvas
 * shows the resolved value live (rather than the source label as a
 * placeholder).
 *
 * Honours the Blocks & Extensions toggle — if the site switched
 * `dynamic-tags` off, none of the editor UI or live-preview bindings
 * register. Server-side bindings still resolve, so saved content keeps
 * rendering.
 */
import { isExtensionEnabled } from '../../utils/is-extension-enabled';

if (isExtensionEnabled('dynamic-tags')) {
	require('./filters.js');
	require('./register-bindings-sources.js');
}
