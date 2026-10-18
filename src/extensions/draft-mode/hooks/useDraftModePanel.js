/**
 * Draft Mode Panel Hook
 *
 * Custom hook for managing draft mode state and actions.
 * Extracts state logic from DraftModePanel component to improve
 * testability and reduce component size.
 *
 * @package
 * @since 1.4.0
 */

import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	getDraftStatus,
	createDraft,
	publishDraft,
	discardDraft,
} from '../api';
import { clearDirtyState } from '../utils';

/**
 * Custom hook for Draft Mode panel state and actions
 *
 * @return {Object} Hook state and handlers
 */
export function useDraftModePanel() {
	const [status, setStatus] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [isCreatingDraft, setIsCreatingDraft] = useState(false);
	const [isActionLoading, setIsActionLoading] = useState(false);
	const [error, setError] = useState(null);
	const [showPublishConfirm, setShowPublishConfirm] = useState(false);
	const [showDiscardConfirm, setShowDiscardConfirm] = useState(false);
	const [showCreateConfirm, setShowCreateConfirm] = useState(false);

	// Get current post data from the editor store.
	const {
		postId,
		postType,
		postStatus,
		hasEdits,
		currentContent,
		currentTitle,
		currentExcerpt,
	} = useSelect((select) => {
		const editor = select('core/editor');
		return {
			postId: editor.getCurrentPostId(),
			postType: editor.getCurrentPostType(),
			postStatus: editor.getEditedPostAttribute('status'),
			hasEdits: editor.isEditedPostDirty(),
			currentContent: editor.getEditedPostContent(),
			currentTitle: editor.getEditedPostAttribute('title'),
			currentExcerpt: editor.getEditedPostAttribute('excerpt'),
		};
	}, []);

	// Get editor dispatch functions.
	const { savePost } = useDispatch('core/editor');

	// Fetch draft status when post ID changes.
	const fetchStatus = useCallback(async () => {
		if (!postId) {
			return;
		}

		setIsLoading(true);
		setError(null);

		try {
			const result = await getDraftStatus(postId);
			setStatus(result);
		} catch (err) {
			setError(
				err.message || __('Failed to load draft status.', 'designsetgo')
			);
		} finally {
			setIsLoading(false);
		}
	}, [postId]);

	useEffect(() => {
		fetchStatus();
	}, [fetchStatus]);

	// Handle create draft button click (shows confirmation modal).
	const handleCreateDraft = () => {
		setShowCreateConfirm(true);
	};

	// Execute draft creation after confirmation.
	const confirmCreateDraft = async () => {
		setShowCreateConfirm(false);

		if (isCreatingDraft) {
			return;
		}

		setIsCreatingDraft(true);
		setError(null);

		try {
			// Create draft with current content (captures any edits made).
			const result = await createDraft(postId, {
				content: currentContent,
				title: currentTitle,
				excerpt: currentExcerpt,
			});

			if (result.success && result.edit_url) {
				// Clear dirty state before navigating to prevent "Leave site?" warning.
				clearDirtyState();
				window.location.href = result.edit_url;
			} else {
				// Success but no edit URL - unexpected response.
				setError(
					__(
						'Draft created but unable to navigate. Please refresh the page.',
						'designsetgo'
					)
				);
				setIsCreatingDraft(false);
			}
		} catch (err) {
			// If draft creation failed because one already exists, redirect to it.
			if (err.data?.draft_id && err.data?.edit_url) {
				clearDirtyState();
				window.location.href = err.data.edit_url;
				return;
			}

			// Categorize error types for better user feedback.
			let errorMessage = __('Failed to create draft.', 'designsetgo');

			if (err.data?.status === 403) {
				errorMessage = __(
					'You do not have permission to create drafts.',
					'designsetgo'
				);
			} else if (err.data?.status === 404) {
				errorMessage = __(
					'The original page was not found.',
					'designsetgo'
				);
			} else if (
				err.message &&
				err.message !== 'Failed to create draft.'
			) {
				errorMessage = err.message;
			} else if (!window.navigator.onLine) {
				errorMessage = __(
					'Network error. Please check your connection and try again.',
					'designsetgo'
				);
			}

			setError(errorMessage);
			setIsCreatingDraft(false);
		}
	};

	// Handle publish draft action.
	const handlePublishDraft = () => {
		setShowPublishConfirm(true);
	};

	// Confirm and execute publish.
	const confirmPublishDraft = async () => {
		setShowPublishConfirm(false);
		setIsActionLoading(true);
		setError(null);

		try {
			// Save any pending changes first.
			if (hasEdits) {
				await savePost();
			}

			const result = await publishDraft(postId);
			if (result.success && result.edit_url) {
				// Clear dirty state before navigating to prevent "Leave site?" warning.
				clearDirtyState();
				window.location.href = result.edit_url;
			} else {
				// Success but no edit URL - unexpected response.
				setError(
					__(
						'Changes published but unable to navigate. Please refresh the page.',
						'designsetgo'
					)
				);
				setIsActionLoading(false);
			}
		} catch (err) {
			// Categorize error types for better user feedback.
			let errorMessage = __('Failed to publish changes.', 'designsetgo');

			if (err.data?.status === 403) {
				errorMessage = __(
					'You do not have permission to publish this draft.',
					'designsetgo'
				);
			} else if (err.data?.status === 404) {
				errorMessage = __(
					'Draft or original page not found.',
					'designsetgo'
				);
			} else if (
				err.message &&
				err.message !== 'Failed to publish changes.'
			) {
				errorMessage = err.message;
			} else if (!window.navigator.onLine) {
				errorMessage = __(
					'Network error. Please check your connection and try again.',
					'designsetgo'
				);
			}

			setError(errorMessage);
			setIsActionLoading(false);
		}
	};

	// Handle discard draft action.
	const handleDiscardDraft = () => {
		setShowDiscardConfirm(true);
	};

	// Confirm and execute discard.
	const confirmDiscardDraft = async () => {
		setShowDiscardConfirm(false);
		setIsActionLoading(true);
		setError(null);

		try {
			const result = await discardDraft(postId);
			if (result.success && status?.original_edit_url) {
				// Clear dirty state before navigating to prevent "Leave site?" warning.
				clearDirtyState();
				window.location.href = status.original_edit_url;
			} else if (result.success) {
				// Success but no original URL - unexpected response.
				setError(
					__(
						'Draft discarded but unable to navigate. Please refresh the page.',
						'designsetgo'
					)
				);
				setIsActionLoading(false);
			}
		} catch (err) {
			// Categorize error types for better user feedback.
			let errorMessage = __('Failed to discard draft.', 'designsetgo');

			if (err.data?.status === 403) {
				errorMessage = __(
					'You do not have permission to discard this draft.',
					'designsetgo'
				);
			} else if (err.data?.status === 404) {
				errorMessage = __('Draft not found.', 'designsetgo');
			} else if (
				err.message &&
				err.message !== 'Failed to discard draft.'
			) {
				errorMessage = err.message;
			} else if (!window.navigator.onLine) {
				errorMessage = __(
					'Network error. Please check your connection and try again.',
					'designsetgo'
				);
			}

			setError(errorMessage);
			setIsActionLoading(false);
		}
	};

	return {
		// State
		status,
		isLoading,
		isCreatingDraft,
		isActionLoading,
		error,
		postId,
		postType,
		postStatus,
		hasEdits,

		// Modal state
		showPublishConfirm,
		showDiscardConfirm,
		showCreateConfirm,
		setShowPublishConfirm,
		setShowDiscardConfirm,
		setShowCreateConfirm,

		// Handlers
		handleCreateDraft,
		confirmCreateDraft,
		handlePublishDraft,
		confirmPublishDraft,
		handleDiscardDraft,
		confirmDiscardDraft,
	};
}
