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
	} catch {
		// Silent fail - navigation will proceed anyway.
	}
}

/**
 * Whether the most recent editor save went through.
 *
 * `savePost()` resolves even when the request fails, so callers that go on to
 * do something irreversible (merge a draft into the live page) must check this
 * first, otherwise they publish a stale copy of the content.
 *
 * @return {boolean} True when the last save succeeded and nothing is saving.
 */
export function didSaveSucceed() {
	try {
		const editor = wp.data.select('core/editor');
		return (
			!editor.isSavingPost() &&
			editor.didPostSaveRequestSucceed() &&
			!editor.didPostSaveRequestFail?.()
		);
	} catch {
		return false;
	}
}

/**
 * Whether an API error is the "live page changed since the draft" conflict.
 *
 * @param {Object} err Error thrown by apiFetch.
 * @return {boolean} True for a draft_conflict error.
 */
export function isConflictError(err) {
	return err?.code === 'draft_conflict' || err?.data?.status === 409;
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
