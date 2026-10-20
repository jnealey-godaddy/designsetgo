/**
 * Draft Mode Controls Component
 *
 * Intercepts the native WordPress Publish button when editing a draft
 * version of a published page, redirecting it to use the custom
 * publishDraft() API that merges changes back to the original page.
 *
 * Also adds "Save Draft" link in the editor header for saving without publishing.
 *
 * @package
 * @since 1.4.0
 */

import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	useState,
	useEffect,
	useCallback,
	createPortal,
} from '@wordpress/element';
import { getDraftStatus, createDraft } from './api';
import { clearDirtyState } from './utils';
import { usePublishIntercept } from './hooks/usePublishIntercept';
import { useHeaderContainer } from './hooks/useHeaderContainer';
import DraftModeHeaderControl from './components/DraftModeHeaderControl';
import DraftModeBanner from './components/DraftModeBanner';
import ErrorModal from './components/ErrorModal';
import {
	CreateConfirmModal,
	ConflictConfirmModal,
} from './components/DraftModeModals';

/**
 * Draft Mode Controls Component
 *
 * Intercepts native Publish button to use custom merge API,
 * and adds Save Draft link in header.
 */
export default function DraftModeControls() {
	const [status, setStatus] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [isCreatingDraft, setIsCreatingDraft] = useState(false);
	const [errorMessage, setErrorMessage] = useState(null);
	const [showErrorModal, setShowErrorModal] = useState(false);
	const [showCreateConfirm, setShowCreateConfirm] = useState(false);
	const [retryPublish, setRetryPublish] = useState(null);

	// Find header container for portaling controls.
	const headerContainer = useHeaderContainer();

	// Get current post data from the editor store.
	const {
		postId,
		postType,
		postStatus,
		hasEdits,
		isSavingPost,
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
			isSavingPost: editor.isSavingPost(),
			currentContent: editor.getEditedPostContent(),
			currentTitle: editor.getEditedPostAttribute('title'),
			currentExcerpt: editor.getEditedPostAttribute('excerpt'),
		};
	}, []);

	// Get editor dispatch functions.
	const { savePost } = useDispatch('core/editor');

	// Use publish intercept hook for drafts.
	const handlePublishError = useCallback((message) => {
		setErrorMessage(message);
		setShowErrorModal(true);
	}, []);

	// The live page changed since the draft was made: hold the retry until the
	// author picks "Publish Anyway".
	const handlePublishConflict = useCallback((retry) => {
		setRetryPublish(() => retry);
	}, []);

	usePublishIntercept(
		postId,
		status?.is_draft,
		handlePublishError,
		handlePublishConflict
	);

	// Fetch draft status.
	const fetchStatus = useCallback(async () => {
		if (!postId) {
			return;
		}

		setIsLoading(true);
		try {
			const result = await getDraftStatus(postId);
			setStatus(result);
		} catch {
			// Silently fail.
		} finally {
			setIsLoading(false);
		}
	}, [postId]);

	useEffect(() => {
		fetchStatus();
	}, [fetchStatus]);

	// Add/remove class on body to change Save button text via CSS.
	useEffect(() => {
		if (!isLoading && status?.is_draft && postType === 'page') {
			document.body.classList.add('dsgo-draft-mode-active');
		} else {
			document.body.classList.remove('dsgo-draft-mode-active');
		}

		return () => {
			document.body.classList.remove('dsgo-draft-mode-active');
		};
	}, [isLoading, status?.is_draft, postType]);

	// Handle save draft action (for when editing a draft).
	const handleSaveDraft = () => {
		savePost();
	};

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
				setErrorMessage(
					__(
						'Draft created but unable to navigate. Please refresh the page.',
						'designsetgo'
					)
				);
				setShowErrorModal(true);
				setIsCreatingDraft(false);
			}
		} catch (err) {
			// If draft already exists, redirect to it.
			if (err.data?.draft_id && err.data?.edit_url) {
				clearDirtyState();
				window.location.href = err.data.edit_url;
				return;
			}

			// Categorize error types for better user feedback.
			let messageText = __('Failed to create draft.', 'designsetgo');

			if (err.data?.status === 403) {
				messageText = __(
					'You do not have permission to create drafts.',
					'designsetgo'
				);
			} else if (err.data?.status === 404) {
				messageText = __(
					'The original page was not found.',
					'designsetgo'
				);
			} else if (
				err.message &&
				err.message !== 'Failed to create draft.'
			) {
				messageText = err.message;
			} else if (!window.navigator.onLine) {
				messageText = __(
					'Network error. Please check your connection and try again.',
					'designsetgo'
				);
			}

			setErrorMessage(messageText);
			setShowErrorModal(true);
			setIsCreatingDraft(false);
		}
	};

	// Don't render if not a page or still loading.
	if (postType !== 'page' || isLoading) {
		return null;
	}

	// Don't render if draft mode is disabled.
	if (status?.settings?.enabled === false) {
		return null;
	}

	// Render header control and banner.
	const canRenderHeaderControls = !!headerContainer;
	const headerControl = (
		<DraftModeHeaderControl
			status={status}
			postStatus={postStatus}
			hasEdits={hasEdits}
			isSavingPost={isSavingPost}
			isCreatingDraft={isCreatingDraft}
			onSaveDraft={handleSaveDraft}
			onCreateDraft={handleCreateDraft}
		/>
	);

	const bottomBanner = <DraftModeBanner status={status} />;

	// Don't render if there's nothing to show.
	if (!headerControl && !status?.is_draft) {
		return null;
	}

	return (
		<>
			{headerControl &&
				canRenderHeaderControls &&
				createPortal(headerControl, headerContainer)}
			{bottomBanner && createPortal(bottomBanner, document.body)}

			<ErrorModal
				isOpen={showErrorModal}
				message={errorMessage}
				onClose={() => setShowErrorModal(false)}
			/>

			<ConflictConfirmModal
				isOpen={!!retryPublish}
				onConfirm={() => {
					const retry = retryPublish;
					setRetryPublish(null);
					retry?.();
				}}
				onCancel={() => setRetryPublish(null)}
			/>

			<CreateConfirmModal
				isOpen={showCreateConfirm}
				onConfirm={confirmCreateDraft}
				onCancel={() => setShowCreateConfirm(false)}
			/>
		</>
	);
}
