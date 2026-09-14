/**
 * Keep form IDs unique.
 *
 * The server resolves a form's email settings and field schema by its ID, so
 * two forms sharing one can be validated and emailed with the other's settings.
 * DSGo patterns ship fixed IDs and duplicating a block copies its ID, so a
 * form whose ID is new to this post, or already used by an earlier form in the
 * editor, gets a fresh one.
 */
import { useEffect } from '@wordpress/element';
import { useRegistry } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

/**
 * Blocks whose inner forms belong to another entity. Changing a form ID there
 * would edit a synced pattern or template part from inside this post.
 */
const SHARED_CONTENT_BLOCKS = ['core/block', 'core/template-part'];

/**
 * Serialize a form ID the way WordPress writes it into a block comment.
 *
 * Mirrors serializeAttributes() in @wordpress/blocks.
 *
 * @param {string} formId Form ID.
 * @return {string} The `"formId":…` fragment as stored.
 */
function serializedFormId(formId) {
	const value = JSON.stringify(formId)
		.replace(/--/g, '\\u002d\\u002d')
		.replace(/</g, '\\u003c')
		.replace(/>/g, '\\u003e')
		.replace(/&/g, '\\u0026')
		.replace(/\\"/g, '\\u0022');

	return `"formId":${value}`;
}

/**
 * Decide whether a form needs a new ID.
 *
 * @param {Object}      params
 * @param {string}      params.clientId     The form block's clientId.
 * @param {string}      params.formId       Its current form ID.
 * @param {Array}       params.formBlocks   Every form in the editor, in document order, as { clientId, formId }.
 * @param {string|null} params.savedContent The post's saved content, or null when unknown.
 * @return {boolean} Whether to assign a new ID.
 */
export function shouldReplaceFormId({
	clientId,
	formId,
	formBlocks,
	savedContent,
}) {
	if (!formId) {
		return true;
	}

	for (const block of formBlocks) {
		if (block.clientId === clientId) {
			break;
		}
		if (block.formId === formId) {
			return true;
		}
	}

	return (
		typeof savedContent === 'string' &&
		!savedContent.includes(serializedFormId(formId))
	);
}

/**
 * Give a form block a unique ID when it mounts.
 *
 * Runs once per mount: a newly assigned ID is by definition absent from the
 * saved content, so re-running on change would replace it forever.
 *
 * @param {Object}   params
 * @param {string}   params.clientId      The form block's clientId.
 * @param {Function} params.setAttributes The block's setAttributes.
 */
export function useUniqueFormId({ clientId, setAttributes }) {
	const registry = useRegistry();

	useEffect(() => {
		const blockEditor = registry.select(blockEditorStore);
		// Not every editor has a post: the widgets editor registers no core/editor.
		const editor = registry.select('core/editor');
		const formId = blockEditor.getBlockAttributes(clientId)?.formId;

		if (formId) {
			const hasParent = (names) =>
				blockEditor.getBlockParentsByBlockName(clientId, names).length >
				0;
			// With the template shown around the post, only forms inside Post
			// Content belong to the post whose saved content is compared below.
			// Editing mode can't stand in for this: blocks of a freshly inserted
			// pattern also read as 'disabled' until the pattern is edited.
			const isTemplateForm =
				editor?.getRenderingMode?.() === 'template-locked' &&
				!hasParent(['core/post-content']);
			if (hasParent(SHARED_CONTENT_BLOCKS) || isTemplateForm) {
				return;
			}
		}

		const savedContent =
			editor?.getCurrentPostAttribute?.('content') ?? null;

		const formBlocks = blockEditor
			.getBlocksByName('designsetgo/form-builder')
			.map((id) => ({
				clientId: id,
				formId: blockEditor.getBlockAttributes(id)?.formId,
			}));

		if (
			shouldReplaceFormId({
				clientId,
				formId,
				formBlocks,
				savedContent:
					typeof savedContent === 'string' ? savedContent : null,
			})
		) {
			setAttributes({ formId: clientId.substring(0, 8) });
		}
		// Mount only — see the docblock.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);
}
