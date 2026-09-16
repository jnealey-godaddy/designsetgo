/**
 * Identifiers shared between the Agent build sidebar's own registration
 * (`./panel/index.js`) and the finish flow's notices (`./finish/review.js`,
 * `./finish/finish-build.js`), which open that sidebar via `core/interface`'s
 * `enableComplementaryArea`, plus the small `@wordpress/data` store
 * (`./report-store.js`) both the finish flow and the panel read the last
 * agent build report from.
 *
 * `@wordpress/editor`'s `PluginSidebar` builds its complementary area
 * identifier as `${pluginContext.name}/${name}` with `scope` hardcoded to
 * `'core'` (see `node_modules/@wordpress/editor/src/components/
 * plugin-sidebar/index.js` and `.../interface/src/components/
 * complementary-area/index.js`), so both sides must agree on these exact
 * strings — confirmed against this project's installed `@wordpress/editor`
 * 14.39.0 / `@wordpress/interface` 9.24.0 and, separately, that WordPress
 * 6.9 core's own `wp-includes/js/dist/editor.js` bundles that store
 * registration itself rather than shipping a standalone `wp-interface`
 * script (no `wp-interface` handle exists in
 * `wp-includes/assets/script-loader-packages.php`, and `editor.js` never
 * assigns `window.wp.interface`). That is why `COMPLEMENTARY_AREA_STORE`
 * below is a plain string passed to `@wordpress/data`'s `dispatch()`
 * rather than an import of `@wordpress/interface`'s `store` export — the
 * latter would add an unmet `wp-interface` script dependency that never
 * loads on a real site.
 */

export const AGENT_BUILD_PLUGIN_NAME = 'designsetgo-agent-build';
export const AGENT_BUILD_SIDEBAR_NAME = 'designsetgo-agent-build-sidebar';
export const AGENT_BUILD_SIDEBAR_IDENTIFIER = `${AGENT_BUILD_PLUGIN_NAME}/${AGENT_BUILD_SIDEBAR_NAME}`;
export const COMPLEMENTARY_AREA_SCOPE = 'core';
export const COMPLEMENTARY_AREA_STORE = 'core/interface';
export const AGENT_BUILD_REPORT_STORE = 'designsetgo/agent-build';
