/**
 * Frontend JavaScript Entry Point
 *
 * Page-wide frontend runtime shared by every DesignSetGo page.
 * It will be compiled to build/frontend.js by @wordpress/scripts.
 *
 * Block-specific frontend scripts are loaded per-block via viewScript in
 * block.json, so they are NOT imported here (avoids duplicating code).
 *
 * @package
 */

// ===== SOFT NAVIGATION SUPPORT =====
// Re-dispatch initialization event on bfcache restoration (back/forward nav)
// so extensions and blocks can re-initialize without relying solely on DOMContentLoaded.
window.addEventListener('pageshow', (event) => {
	if (event.persisted) {
		document.dispatchEvent(
			new CustomEvent('dsgo-content-loaded', {
				detail: { source: 'bfcache' },
			})
		);
	}
});

// ===== UTILITIES =====
import './utils/focus-outline.js';

// Loads the block and extension assets that content swapped in by a soft
// navigation (Airo refresh, Query "load more") needs.
import './utils/soft-nav-assets.js';

// ===== EXTENSIONS FRONTEND =====
// Each extension's frontend script is its own bundle (build/extensions/<name>/
// frontend.js), enqueued only when its markup is on the page. The manifest is
// includes/data/frontend-extensions.json. Those bundles depend on this one for
// the bfcache → dsgo-content-loaded dispatch above.
