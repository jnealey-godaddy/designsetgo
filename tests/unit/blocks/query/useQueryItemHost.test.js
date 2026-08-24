/**
 * useQueryItemHost — editor-side resolution of a query's item host.
 *
 * The hook mirrors designsetgo_query_render_container()'s scan of the query's
 * direct children, so the editor can preview the decision the server will
 * make about a carousel host and infinite scroll.
 */
import { renderHook } from '@testing-library/react';

import useQueryItemHost, {
	hostSupportsInfiniteScroll,
	GRID_ITEM_HOST_BLOCK,
} from '../../../../src/blocks/query/hooks/useQueryItemHost';

jest.mock('@wordpress/data', () => ({
	useSelect: (mapSelect) => mapSelect(() => mockStore),
}));

jest.mock('@wordpress/block-editor', () => ({
	store: 'core/block-editor',
}));

let mockStore;

/**
 * Stand up a block tree: a query with the given direct children, and a
 * pagination block nested inside it.
 *
 * @param {string[]} childNames Direct child block names of the query.
 * @return {Object} A minimal block-editor store.
 */
function storeWithQueryChildren(childNames) {
	const query = {
		name: 'designsetgo/query',
		innerBlocks: childNames.map((name) => ({ name, innerBlocks: [] })),
	};
	const blocks = { 'query-1': query };

	return {
		getBlockParents: (clientId) =>
			clientId === 'pagination-1' ? ['query-1'] : [],
		getBlock: (clientId) => blocks[clientId] || null,
	};
}

describe('hostSupportsInfiniteScroll', () => {
	it('accepts the grid host and a query with no host block', () => {
		expect(hostSupportsInfiniteScroll(GRID_ITEM_HOST_BLOCK)).toBe(true);
		expect(hostSupportsInfiniteScroll('')).toBe(true);
	});

	it('rejects carousel-shaped hosts', () => {
		expect(hostSupportsInfiniteScroll('designsetgo/slider')).toBe(false);
		expect(hostSupportsInfiniteScroll('designsetgo/scroll-slides')).toBe(
			false
		);
	});
});

describe('useQueryItemHost', () => {
	it('finds a slider host among the query children', () => {
		mockStore = storeWithQueryChildren([
			'designsetgo/query-filter',
			'designsetgo/slider',
			'designsetgo/query-pagination',
		]);

		const { result } = renderHook(() => useQueryItemHost('pagination-1'));
		expect(result.current).toBe('designsetgo/slider');
	});

	it('finds the grid host', () => {
		mockStore = storeWithQueryChildren([
			'designsetgo/query-results',
			'designsetgo/query-pagination',
		]);

		const { result } = renderHook(() => useQueryItemHost('pagination-1'));
		expect(result.current).toBe(GRID_ITEM_HOST_BLOCK);
	});

	it('reports no host when the query has none', () => {
		mockStore = storeWithQueryChildren(['core/paragraph']);

		const { result } = renderHook(() => useQueryItemHost('pagination-1'));
		expect(result.current).toBe('');
	});

	it('reports no host outside a query', () => {
		mockStore = storeWithQueryChildren(['designsetgo/slider']);

		const { result } = renderHook(() => useQueryItemHost('orphan'));
		expect(result.current).toBe('');
	});
});
