/**
 * Draft Mode API Helpers
 *
 * @package
 * @since 1.4.0
 */

import apiFetch from '@wordpress/api-fetch';

/**
 * Get draft status for a post
 *
 * @param {number} postId - The post ID to check.
 * @return {Promise<Object>} Status object.
 */
export async function getDraftStatus(postId) {
	return apiFetch({
		path: `/designsetgo/v1/draft-mode/status/${postId}`,
		method: 'GET',
	});
}

/**
 * Create a draft of a published page
 *
 * @param {number} postId            - The published page ID.
 * @param {Object} overrides         - Optional content overrides to capture unsaved edits.
 * @param {string} overrides.content - Post content.
 * @param {string} overrides.title   - Post title.
 * @param {string} overrides.excerpt - Post excerpt.
 * @return {Promise<Object>} Result with draft_id and edit_url.
 */
export async function createDraft(postId, overrides = {}) {
	return apiFetch({
		path: '/designsetgo/v1/draft-mode/create',
		method: 'POST',
		data: {
			post_id: postId,
			...overrides,
		},
	});
}

/**
 * Publish (merge) a draft into the original
 *
 * @param {number}  draftId - The draft post ID.
 * @param {boolean} force   - Publish even if the live page changed after the draft was created.
 * @return {Promise<Object>} Result with original_id.
 */
export async function publishDraft(draftId, force = false) {
	return apiFetch({
		path: `/designsetgo/v1/draft-mode/${draftId}/publish`,
		method: 'POST',
		data: { force },
	});
}

/**
 * Discard a draft without publishing
 *
 * @param {number} draftId - The draft post ID.
 * @return {Promise<Object>} Result with original_id.
 */
export async function discardDraft(draftId) {
	return apiFetch({
		path: `/designsetgo/v1/draft-mode/${draftId}`,
		method: 'DELETE',
	});
}
