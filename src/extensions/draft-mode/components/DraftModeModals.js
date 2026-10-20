/**
 * Draft Mode Confirmation Modals
 *
 * Reusable confirmation modals for draft operations.
 *
 * @package
 * @since 1.4.0
 */

import { __ } from '@wordpress/i18n';
import { Modal, Flex, FlexItem, Button } from '@wordpress/components';

/**
 * Publish Draft Confirmation Modal
 *
 * @param {Object}   props           Component props.
 * @param {boolean}  props.isOpen    Whether the modal is open.
 * @param {Function} props.onConfirm Callback when user confirms.
 * @param {Function} props.onCancel  Callback when user cancels.
 */
export function PublishConfirmModal({ isOpen, onConfirm, onCancel }) {
	if (!isOpen) {
		return null;
	}

	return (
		<Modal
			title={__('Publish Changes?', 'designsetgo')}
			onRequestClose={onCancel}
			size="small"
		>
			<Flex direction="column" gap={4}>
				<FlexItem>
					<p style={{ margin: 0 }}>
						{__(
							'This will replace the current live content with your draft changes.',
							'designsetgo'
						)}
					</p>
				</FlexItem>
				<Flex justify="flex-end" gap={3}>
					<FlexItem>
						<Button variant="tertiary" onClick={onCancel}>
							{__('Cancel', 'designsetgo')}
						</Button>
					</FlexItem>
					<FlexItem>
						<Button variant="primary" onClick={onConfirm}>
							{__('Publish', 'designsetgo')}
						</Button>
					</FlexItem>
				</Flex>
			</Flex>
		</Modal>
	);
}

/**
 * Publish Conflict Modal
 *
 * Shown when the live page was edited after the draft was created.
 *
 * @param {Object}   props           Component props.
 * @param {boolean}  props.isOpen    Whether the modal is open.
 * @param {Function} props.onConfirm Callback when user chooses to publish anyway.
 * @param {Function} props.onCancel  Callback when user cancels.
 */
export function ConflictConfirmModal({ isOpen, onConfirm, onCancel }) {
	if (!isOpen) {
		return null;
	}

	return (
		<Modal
			title={__('Live Page Has Changed', 'designsetgo')}
			onRequestClose={onCancel}
			size="small"
		>
			<Flex direction="column" gap={4}>
				<FlexItem>
					<p style={{ margin: 0 }}>
						{__(
							'The live page was edited after this draft was created. Publishing now will overwrite those edits with your draft.',
							'designsetgo'
						)}
					</p>
				</FlexItem>
				<Flex justify="flex-end" gap={3}>
					<FlexItem>
						<Button variant="tertiary" onClick={onCancel}>
							{__('Cancel', 'designsetgo')}
						</Button>
					</FlexItem>
					<FlexItem>
						<Button
							variant="primary"
							isDestructive
							onClick={onConfirm}
						>
							{__('Publish Anyway', 'designsetgo')}
						</Button>
					</FlexItem>
				</Flex>
			</Flex>
		</Modal>
	);
}

/**
 * Discard Draft Confirmation Modal
 *
 * @param {Object}   props           Component props.
 * @param {boolean}  props.isOpen    Whether the modal is open.
 * @param {Function} props.onConfirm Callback when user confirms.
 * @param {Function} props.onCancel  Callback when user cancels.
 */
export function DiscardConfirmModal({ isOpen, onConfirm, onCancel }) {
	if (!isOpen) {
		return null;
	}

	return (
		<Modal
			title={__('Discard Draft?', 'designsetgo')}
			onRequestClose={onCancel}
			size="small"
		>
			<Flex direction="column" gap={4}>
				<FlexItem>
					<p style={{ margin: 0 }}>
						{__(
							'All changes will be lost and cannot be recovered. The live page will remain unchanged.',
							'designsetgo'
						)}
					</p>
				</FlexItem>
				<Flex justify="flex-end" gap={3}>
					<FlexItem>
						<Button variant="tertiary" onClick={onCancel}>
							{__('Cancel', 'designsetgo')}
						</Button>
					</FlexItem>
					<FlexItem>
						<Button
							variant="primary"
							isDestructive
							onClick={onConfirm}
						>
							{__('Discard', 'designsetgo')}
						</Button>
					</FlexItem>
				</Flex>
			</Flex>
		</Modal>
	);
}

/**
 * Create Draft Confirmation Modal
 *
 * @param {Object}   props           Component props.
 * @param {boolean}  props.isOpen    Whether the modal is open.
 * @param {Function} props.onConfirm Callback when user confirms.
 * @param {Function} props.onCancel  Callback when user cancels.
 */
export function CreateConfirmModal({ isOpen, onConfirm, onCancel }) {
	if (!isOpen) {
		return null;
	}

	return (
		<Modal
			title={__('Create Draft?', 'designsetgo')}
			onRequestClose={onCancel}
			size="small"
		>
			<Flex direction="column" gap={4}>
				<FlexItem>
					<p style={{ margin: 0 }}>
						{__(
							'This will create a draft version of this page with your current edits. The live page will remain unchanged.',
							'designsetgo'
						)}
					</p>
				</FlexItem>
				<Flex justify="flex-end" gap={3}>
					<FlexItem>
						<Button variant="tertiary" onClick={onCancel}>
							{__('Cancel', 'designsetgo')}
						</Button>
					</FlexItem>
					<FlexItem>
						<Button variant="primary" onClick={onConfirm}>
							{__('Create Draft', 'designsetgo')}
						</Button>
					</FlexItem>
				</Flex>
			</Flex>
		</Modal>
	);
}
