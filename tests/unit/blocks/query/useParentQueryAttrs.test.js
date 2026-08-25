/**
 * useParentQueryAttrs — editor-side lookup of the enclosing query's config.
 *
 * Layout hosts (slider, scroll-slides) read this so their editor preview uses
 * the same postType/perPage/order the front end will. "Enclosing" has to mean
 * the nearest query, not any query, or a nested composition previews the wrong
 * posts.
 */
import { renderHook } from '@testing-library/react';

import useParentQueryAttrs from '../../../../src/blocks/query/hooks/useParentQueryAttrs';

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
 * one.
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

describe('useParentQueryAttrs', () => {
	it('returns the enclosing query attributes', () => {
		mockStore = storeWithAncestors(
			{
				'query-1': {
					name: 'designsetgo/query',
					attributes: { postType: 'post', perPage: 4 },
				},
			},
			['query-1']
		);

		const { result } = renderHook(() => useParentQueryAttrs('target'));
		expect(result.current).toEqual({ postType: 'post', perPage: 4 });
	});

	it('returns null outside a query', () => {
		mockStore = storeWithAncestors(
			{
				'group-1': { name: 'core/group', attributes: {} },
			},
			['group-1']
		);

		const { result } = renderHook(() => useParentQueryAttrs('target'));
		expect(result.current).toBeNull();
	});

	it('returns null when disabled', () => {
		mockStore = storeWithAncestors(
			{
				'query-1': {
					name: 'designsetgo/query',
					attributes: { postType: 'post' },
				},
			},
			['query-1']
		);

		const { result } = renderHook(() =>
			useParentQueryAttrs('target', false)
		);
		expect(result.current).toBeNull();
	});

	it('resolves the nearest enclosing query, not the outermost', () => {
		// A products query whose per-item template holds a second query of
		// related posts, presented by a slider. Walking ancestors root-first
		// answers with the outer query, so the carousel previews the outer
		// query's posts while the front end renders the inner query's.
		mockStore = storeWithAncestors(
			{
				'query-outer': {
					name: 'designsetgo/query',
					attributes: { postType: 'product', perPage: 12 },
				},
				'query-inner': {
					name: 'designsetgo/query',
					attributes: { postType: 'post', perPage: 3 },
				},
			},
			['query-inner', 'query-outer']
		);

		const { result } = renderHook(() => useParentQueryAttrs('target'));
		expect(result.current).toEqual({ postType: 'post', perPage: 3 });
	});
});
