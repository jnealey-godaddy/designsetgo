/**
 * Draft Mode Panel Component
 *
 * Displays draft info in sidebar with draft management controls.
 * Allows creating, publishing, and discarding drafts of published pages.
 *
 * @package
 * @since 1.4.0
 */

import { __ } from '@wordpress/i18n';
import { Notice, Spinner } from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import {
	PublishConfirmModal,
	DiscardConfirmModal,
	CreateConfirmModal,
	ConflictConfirmModal,
} from './components/DraftModeModals';
import {
	IsDraftView,
	HasDraftView,
	CanCreateDraftView,
	UnavailableView,
} from './components/DraftStatusViews';
import { useDraftModePanel } from './hooks/useDraftModePanel';

/**
 * Draft Mode Panel Component
 *
 * Displays draft info in sidebar with draft management controls.
 */
export default function DraftModePanel() {
	const {
		status,
		isLoading,
		isCreatingDraft,
		isActionLoading,
		error,
		postType,
		postStatus,
		showPublishConfirm,
		showDiscardConfirm,
		showCreateConfirm,
		showConflictConfirm,
		setShowConflictConfirm,
		setShowPublishConfirm,
		setShowDiscardConfirm,
		setShowCreateConfirm,
		handleCreateDraft,
		confirmCreateDraft,
		handlePublishDraft,
		confirmPublishDraft,
		handleDiscardDraft,
		confirmDiscardDraft,
	} = useDraftModePanel();

	// Only show for pages.
	if (postType !== 'page') {
		return null;
	}

	// Don't show if draft mode is disabled (after loading).
	if (!isLoading && status?.settings?.enabled === false) {
		return null;
	}

	// Show creating draft state.
	if (isCreatingDraft) {
		return (
			<PluginDocumentSettingPanel
				name="dsgo-draft-mode"
				title={__('Draft Mode', 'designsetgo')}
				className="dsgo-draft-mode-panel"
			>
				<div className="dsgo-draft-mode-panel__loading">
					<Spinner />
					<span>{__('Creating draft…', 'designsetgo')}</span>
				</div>
			</PluginDocumentSettingPanel>
		);
	}

	// Render loading state.
	if (isLoading) {
		return (
			<PluginDocumentSettingPanel
				name="dsgo-draft-mode"
				title={__('Draft Mode', 'designsetgo')}
				className="dsgo-draft-mode-panel"
			>
				<div className="dsgo-draft-mode-panel__loading">
					<Spinner />
					<span>{__('Loading…', 'designsetgo')}</span>
				</div>
			</PluginDocumentSettingPanel>
		);
	}

	// Render panel content based on status.
	return (
		<PluginDocumentSettingPanel
			name="dsgo-draft-mode"
			title={__('Draft Mode', 'designsetgo')}
			className="dsgo-draft-mode-panel"
		>
			{error && (
				<Notice
					status="error"
					isDismissible={false}
					className="dsgo-draft-mode-panel__notice"
				>
					{error}
				</Notice>
			)}

			{/* State 1: This IS a draft of another page */}
			{status?.is_draft && (
				<IsDraftView
					status={status}
					onPublish={handlePublishDraft}
					onDiscard={handleDiscardDraft}
					isLoading={isActionLoading}
				/>
			)}

			{/* State 2: This page HAS a pending draft */}
			{!status?.is_draft && status?.has_draft && (
				<HasDraftView status={status} />
			)}

			{/* State 3: Published page with no draft - show Create Draft button */}
			{!status?.is_draft &&
				!status?.has_draft &&
				postStatus === 'publish' && (
					<CanCreateDraftView onCreate={handleCreateDraft} />
				)}

			{/* State 4: Not a published page - feature not available */}
			{!status?.is_draft &&
				!status?.has_draft &&
				postStatus !== 'publish' && <UnavailableView />}

			{/* Confirmation modals */}
			<PublishConfirmModal
				isOpen={showPublishConfirm}
				onConfirm={() => confirmPublishDraft(false)}
				onCancel={() => setShowPublishConfirm(false)}
			/>

			<ConflictConfirmModal
				isOpen={showConflictConfirm}
				onConfirm={() => confirmPublishDraft(true)}
				onCancel={() => setShowConflictConfirm(false)}
			/>

			<DiscardConfirmModal
				isOpen={showDiscardConfirm}
				onConfirm={confirmDiscardDraft}
				onCancel={() => setShowDiscardConfirm(false)}
			/>

			<CreateConfirmModal
				isOpen={showCreateConfirm}
				onConfirm={confirmCreateDraft}
				onCancel={() => setShowCreateConfirm(false)}
			/>
		</PluginDocumentSettingPanel>
	);
}
