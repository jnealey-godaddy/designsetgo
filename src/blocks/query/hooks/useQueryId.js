import { useUniqueBlockId } from '../../../hooks';

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
	useUniqueBlockId({
		clientId,
		attributeName: 'queryId',
		value: queryId,
		setAttributes,
		prefix: 'q',
		length: 8,
	});
}
