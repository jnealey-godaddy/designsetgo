/**
 * Form Builder — list the fields of the form a block belongs to.
 *
 * Walks nested blocks so fields inside groups (or, later, step blocks) count.
 *
 * @since 2.10.0
 */

import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';

const EMPTY = [];

const TYPE_BY_BLOCK = {
	'designsetgo/form-text-field': 'text',
	'designsetgo/form-email-field': 'email',
	'designsetgo/form-textarea-field': 'textarea',
	'designsetgo/form-number-field': 'number',
	'designsetgo/form-phone-field': 'tel',
	'designsetgo/form-url-field': 'url',
	'designsetgo/form-date-field': 'date',
	'designsetgo/form-time-field': 'time',
	'designsetgo/form-select-field': 'select',
	'designsetgo/form-checkbox-field': 'checkbox',
	'designsetgo/form-hidden-field': 'hidden',
};

/**
 * @param {Array}  blocks          Block tree.
 * @param {string} excludeClientId Block to leave out (the field being edited).
 * @return {Array} Fields in document order.
 */
export function collectFormFields(blocks, excludeClientId) {
	const out = [];
	const walk = (list) => {
		(list || []).forEach((block) => {
			const type = TYPE_BY_BLOCK[block.name];
			const name = block.attributes?.fieldName;
			if (type && name && block.clientId !== excludeClientId) {
				out.push({
					clientId: block.clientId,
					name,
					label: block.attributes.label || name,
					type,
					options: Array.isArray(block.attributes.options)
						? block.attributes.options
						: [],
				});
			}
			walk(block.innerBlocks);
		});
	};
	walk(blocks);
	return out;
}

/**
 * @param {string} clientId Field block client ID.
 * @return {Array} The other fields in the same form.
 */
export default function useFormFields(clientId) {
	// Return raw store references from the selector and derive in useMemo:
	// a freshly built array per call would re-render on every store change.
	const formBlocks = useSelect(
		(select) => {
			const store = select('core/block-editor');
			const forms = store.getBlockParentsByBlockName(
				clientId,
				'designsetgo/form-builder'
			);
			return forms.length
				? store.getBlocks(forms[forms.length - 1])
				: EMPTY;
		},
		[clientId]
	);
	return useMemo(
		() => collectFormFields(formBlocks, clientId),
		[formBlocks, clientId]
	);
}
