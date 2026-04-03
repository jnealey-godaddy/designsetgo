/**
 * Draft Mode Utilities
 *
 * Shared utility functions for draft mode components.
 *
 * @package
 * @since 1.4.0
 */

/**
 * Clear editor dirty state to prevent "Leave site?" warning.
 *
 * Resets reference blocks to current blocks so WordPress thinks
 * the current state IS the saved state (no dirty changes).
 * This allows navigation without triggering the beforeunload warning.
 */
export function clearDirtyState() {
	try {
		const currentBlocks = wp.data.select('core/block-editor').getBlocks();
		wp.data.dispatch('core/editor').resetEditorBlocks(currentBlocks, {
			__unstableShouldCreateUndoLevel: false,
		});
	} catch (e) {
		// Silent fail - navigation will proceed anyway.
	}
}

/**
 * Navigate to a URL after clearing the dirty state.
 *
 * @param {string} url - The URL to navigate to.
 */
export function navigateTo(url) {
	clearDirtyState();
	try {
		const parsed = new URL(url, window.location.href);
		if (parsed.protocol === 'https:' || parsed.protocol === 'http:') {
			window.location.assign(parsed.href);
		}
	} catch {
		// Invalid URL — do not navigate.
	}
}
