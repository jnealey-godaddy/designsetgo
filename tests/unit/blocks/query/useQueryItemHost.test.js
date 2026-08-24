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
 * Build a store over a fixed ancestor chain.
 *
 * `getBlockParents` reproduces the real selector's contract: the walk collects
 * nearest-first and is reversed to root-first unless `ascending` is set. A mock
 * that ignores the flag cannot tell a nearest-first lookup from an outermost
 * one, which is how the ordering bug reached review.
 *
 * @param {Object}   blocks    clientId -> block, for every ancestor.
 * @param {string[]} ancestors Ancestor clientIds of 'target', nearest first.
 * @return {Object} A minimal block-editor store.
 */
function storeWithAncestors(blocks, ancestors) {
	return {
		getBlockParents: (clientId, ascending = false) => {
			if (clientId !== 'target') {
				return [];
			}
			return ascending ? [...ancestors] : [...ancestors].reverse();
		},
		getBlock: (clientId) => blocks[clientId] || null,
	};
}

/**
 * Stand up the common case: one query with the given direct children, holding
 * the block under test.
 *
 * @param {string[]} childNames Direct child block names of the query.
 * @return {Object} A minimal block-editor store.
 */
function storeWithQueryChildren(childNames) {
	return storeWithAncestors(
		{
			'query-1': {
				name: 'designsetgo/query',
				innerBlocks: childNames.map((name) => ({
					name,
					innerBlocks: [],
				})),
			},
		},
		['query-1']
	);
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

		const { result } = renderHook(() => useQueryItemHost('target'));
		expect(result.current).toBe('designsetgo/slider');
	});

	it('finds the grid host', () => {
		mockStore = storeWithQueryChildren([
			'designsetgo/query-results',
			'designsetgo/query-pagination',
		]);

		const { result } = renderHook(() => useQueryItemHost('target'));
		expect(result.current).toBe(GRID_ITEM_HOST_BLOCK);
	});

	it('reports no host when the query has none', () => {
		mockStore = storeWithQueryChildren(['core/paragraph']);

		const { result } = renderHook(() => useQueryItemHost('target'));
		expect(result.current).toBe('');
	});

	it('reports no host outside a query', () => {
		mockStore = storeWithQueryChildren(['designsetgo/slider']);

		const { result } = renderHook(() => useQueryItemHost('orphan'));
		expect(result.current).toBe('');
	});

	it('resolves the nearest enclosing query, not the outermost', () => {
		// A grid query whose per-item template holds a second query presenting
		// its own results in a carousel. The server registry is keyed per
		// queryId and resolves the inner host; scanning ancestors root-first
		// would answer with the outer grid instead, so the editor would preview
		// a live sentinel for a host the front end is about to degrade.
		mockStore = storeWithAncestors(
			{
				'query-outer': {
					name: 'designsetgo/query',
					innerBlocks: [
						{ name: 'designsetgo/query-results', innerBlocks: [] },
					],
				},
				'query-inner': {
					name: 'designsetgo/query',
					innerBlocks: [
						{ name: 'designsetgo/slider', innerBlocks: [] },
					],
				},
			},
			['query-inner', 'query-outer']
		);

		const { result } = renderHook(() => useQueryItemHost('target'));
		expect(result.current).toBe('designsetgo/slider');
	});
});
