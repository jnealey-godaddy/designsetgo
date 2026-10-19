/**
 * useUniqueBlockId
 *
 * Seeds a block attribute with a stable id derived from clientId on first render.
 * Replaces the duplicated `useEffect(() => { if (!attr) setAttributes({ attr: clientId.substring(0, 8) }); }, [])`
 * pattern that lived in tabs, form-builder, and modal.
 *
 * Also re-seeds a block that was just inserted (duplicate, paste, a pattern
 * inserted twice) when another block of the same type already holds its id:
 * duplication copies every attribute, so without this both copies render the
 * same HTML id and their ARIA references point at the original. Blocks loaded
 * with the post are left alone, so opening a post never rewrites it.
 *
 * @param {Object}                params
 * @param {string}                params.clientId      The block clientId.
 * @param {string}                params.attributeName Name of the attribute to seed.
 * @param {string|undefined|null} params.value         Current value of the attribute.
 * @param {Function}              params.setAttributes The block's setAttributes callback.
 * @param {string}                [params.prefix='']   Optional prefix prepended to the id.
 * @param {number|null}           [params.length=8]    Substring length, or null to use the full clientId.
 */
import { useEffect } from '@wordpress/element';
import { useRegistry } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { wasJustInserted } from './useWasJustInserted';

function hasDuplicate(select, clientId, attributeName, value) {
	const { getBlockName, getBlockAttributes, getClientIdsWithDescendants } =
		select(blockEditorStore);
	const name = getBlockName(clientId);
	return getClientIdsWithDescendants().some(
		(id) =>
			id !== clientId &&
			getBlockName(id) === name &&
			getBlockAttributes(id)?.[attributeName] === value
	);
}

export function useUniqueBlockId({
	clientId,
	attributeName,
	value,
	setAttributes,
	prefix = '',
	length = 8,
}) {
	const registry = useRegistry();

	useEffect(() => {
		const isEmpty = value === undefined || value === null || value === '';
		if (
			!isEmpty &&
			!(
				wasJustInserted(registry.select, clientId) &&
				hasDuplicate(registry.select, clientId, attributeName, value)
			)
		) {
			return;
		}
		const base = length === null ? clientId : clientId.substring(0, length);
		setAttributes({ [attributeName]: `${prefix}${base}` });
		// We intentionally exclude clientId/setAttributes from deps:
		// re-seeding on clientId change would overwrite saved ids.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [value, attributeName]);
}
