/**
 * Frontend JavaScript Entry Point
 *
 * This file bundles extension frontend scripts for the DesignSetGo plugin.
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

// ===== EXTENSIONS FRONTEND =====
// Block animations - scroll-triggered, hover, click animations
import './extensions/block-animations/frontend.js';

// Block animations - SVG stroke drawing (needs runtime path measurement)
import './extensions/block-animations/svg-draw.js';

// Clickable group - makes containers clickable with link functionality
import './extensions/clickable-group/frontend.js';

// Background video - video background initialization
import './extensions/background-video/frontend.js';

// Text reveal - scroll-triggered text color animation
import './extensions/text-reveal/frontend.js';

// Expanding background - scroll-driven expanding background effect
import './extensions/expanding-background/frontend.js';

// Vertical scroll parallax - scroll-based vertical movement
import './extensions/vertical-scroll-parallax/frontend.js';
