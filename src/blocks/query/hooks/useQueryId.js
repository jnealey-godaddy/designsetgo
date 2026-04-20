import { useUniqueBlockId } from '../../../hooks';
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';

function findFirstQueryBlockClientId(blocks, queryId) {
	for (const block of blocks || []) {
		if (
			block?.name === 'designsetgo/query' &&
			block?.attributes?.queryId === queryId
		) {
			return block.clientId;
		}

		const nestedMatch = findFirstQueryBlockClientId(
			block?.innerBlocks,
			queryId
		);
		if (nestedMatch) {
			return nestedMatch;
		}
	}

	return null;
}

/**
 * Seeds a stable `queryId` attribute on the block's first render so pagination,
 * filter, and no-results sibling blocks can bind to this query via `queryId`.
 *
 * @param {Object}   params
 * @param {string}   params.clientId      Block clientId.
 * @param {string}   params.queryId       Current value of the queryId attribute.
 * @param {Function} params.setAttributes Block's setAttributes callback.
 */
export default function useQueryId({ clientId, queryId, setAttributes }) {
	const hasDuplicateQueryId = useSelect(
		(select) => {
			if (!queryId) {
				return false;
			}

			const firstOwnerClientId = findFirstQueryBlockClientId(
				select(blockEditorStore).getBlocks(),
				queryId
			);

			return !!firstOwnerClientId && firstOwnerClientId !== clientId;
		},
		[clientId, queryId]
	);

	useEffect(() => {
		if (!hasDuplicateQueryId) {
			return;
		}

		setAttributes({ queryId: '' });
	}, [hasDuplicateQueryId, setAttributes]);

	useUniqueBlockId({
		clientId,
		attributeName: 'queryId',
		value: queryId,
		setAttributes,
		prefix: 'q',
		length: 8,
	});
}
