import { useSelect, useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as coreStore } from '@wordpress/core-data';

import ResultsLayoutControls from './ResultsLayoutControls';

/**
 * Proxy panel: surfaces the child designsetgo/query-results block's layout
 * controls on the parent designsetgo/query inspector. Writes via
 * updateBlockAttributes so changes are reflected in the child's own inspector.
 *
 * @param {Object} root0
 * @param {string} root0.clientId Parent query block's clientId.
 */
export default function ResultsLayoutPanel({ clientId }) {
	const { updateBlockAttributes } = useDispatch(blockEditorStore);

	const child = useSelect(
		(select) => {
			const block = select(blockEditorStore).getBlock(clientId);
			const inner = block?.innerBlocks || [];
			return (
				inner.find((b) => b?.name === 'designsetgo/query-results') ||
				null
			);
		},
		[clientId]
	);

	const taxonomyOptions = useSelect((select) => {
		const taxes = select(coreStore).getTaxonomies({ per_page: -1 }) || [];
		return taxes
			.filter((t) => t.show_in_rest !== false)
			.map((t) => ({
				value: t.slug,
				label: t.labels?.singular_name || t.name || t.slug,
			}));
	}, []);

	if (!child) {
		return null;
	}

	return (
		<ResultsLayoutControls
			attributes={child.attributes || {}}
			set={(next) => updateBlockAttributes(child.clientId, next)}
			panelId={clientId}
			taxonomyOptions={taxonomyOptions}
		/>
	);
}
