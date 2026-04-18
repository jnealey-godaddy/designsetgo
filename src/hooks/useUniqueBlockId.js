/**
 * useUniqueBlockId
 *
 * Seeds a block attribute with a stable id derived from clientId on first render.
 * Replaces the duplicated `useEffect(() => { if (!attr) setAttributes({ attr: clientId.substring(0, 8) }); }, [])`
 * pattern that lived in tabs, form-builder, and modal.
 *
 * @param {Object}      params
 * @param {string}      params.clientId      The block clientId.
 * @param {string}      params.attributeName Name of the attribute to seed.
 * @param {string|undefined} params.value    Current value of the attribute.
 * @param {Function}    params.setAttributes The block's setAttributes callback.
 * @param {string}      [params.prefix='']   Optional prefix prepended to the id.
 * @param {number|null} [params.length=8]    Substring length, or null to use the full clientId.
 */
import { useEffect } from '@wordpress/element';

export function useUniqueBlockId({
	clientId,
	attributeName,
	value,
	setAttributes,
	prefix = '',
	length = 8,
}) {
	useEffect(() => {
		if (value) {
			return;
		}
		const base = length === null ? clientId : clientId.substring(0, length);
		setAttributes({ [attributeName]: `${prefix}${base}` });
		// We intentionally exclude clientId/setAttributes from deps:
		// re-seeding on clientId change would overwrite saved ids.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [value, attributeName]);
}
