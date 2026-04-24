import { renderHook } from '@testing-library/react';
import useQueryPreview, {
	normalizeRelationshipIds,
} from '../../../../src/blocks/query/hooks/useQueryPreview';

jest.mock('@wordpress/core-data', () => ({
	store: 'core',
	useEntityRecords: jest.fn(),
}));
jest.mock('@wordpress/data', () => ({
	useSelect: jest.fn(() => null),
}));

const { useEntityRecords } = require('@wordpress/core-data');
const { useSelect } = require('@wordpress/data');

// ---------------------------------------------------------------------------
// normalizeRelationshipIds — unit tests for each storage shape
// ---------------------------------------------------------------------------

describe('normalizeRelationshipIds', () => {
	it('handles array of plain integers', () => {
		expect(normalizeRelationshipIds([12, 34, 56])).toEqual([12, 34, 56]);
	});

	it('filters out zero / falsy ints in an array', () => {
		expect(normalizeRelationshipIds([0, 12, null, 34])).toEqual([12, 34]);
	});

	it('handles array of WP_Post-style objects with .ID', () => {
		expect(normalizeRelationshipIds([{ ID: 12 }, { ID: 34 }])).toEqual([
			12, 34,
		]);
	});

	it('handles array of REST-style objects with .id (lowercase)', () => {
		expect(normalizeRelationshipIds([{ id: 12 }, { id: 34 }])).toEqual([
			12, 34,
		]);
	});

	it('handles comma-separated string', () => {
		expect(normalizeRelationshipIds('12, 34, 56')).toEqual([12, 34, 56]);
	});

	it('handles single numeric string', () => {
		expect(normalizeRelationshipIds('42')).toEqual([42]);
	});

	it('returns empty array for non-numeric string', () => {
		expect(normalizeRelationshipIds('not-a-number')).toEqual([]);
	});

	it('handles plain number', () => {
		expect(normalizeRelationshipIds(99)).toEqual([99]);
	});

	it('returns empty array for zero', () => {
		expect(normalizeRelationshipIds(0)).toEqual([]);
	});

	it('returns empty array for null', () => {
		expect(normalizeRelationshipIds(null)).toEqual([]);
	});

	it('returns empty array for undefined', () => {
		expect(normalizeRelationshipIds(undefined)).toEqual([]);
	});

	it('returns empty array for empty string', () => {
		expect(normalizeRelationshipIds('')).toEqual([]);
	});

	it('returns empty array for empty array', () => {
		expect(normalizeRelationshipIds([])).toEqual([]);
	});
});

// ---------------------------------------------------------------------------
// useQueryPreview hook — relationship source
// ---------------------------------------------------------------------------

describe('useQueryPreview — relationship source', () => {
	beforeEach(() => {
		useEntityRecords.mockReset();
		useSelect.mockReset();
		useSelect.mockImplementation(() => null);
	});

	it('returns empty state when relationshipField is empty', () => {
		useEntityRecords.mockReturnValue({ records: [], hasResolved: true });
		const { result } = renderHook(() =>
			useQueryPreview({
				source: 'relationship',
				relationshipField: '',
				perPage: 3,
			})
		);
		expect(result.current.records).toEqual([]);
		expect(result.current.hasResolved).toBe(true);
	});

	it('reads relationship meta from the current editor post type', () => {
		useEntityRecords.mockReturnValue({ records: [], hasResolved: true });
		useSelect.mockImplementation((selector) =>
			selector((store) => {
				if (store === 'core/editor') {
					return {
						getCurrentPostId: () => 42,
						getCurrentPostType: () => 'page',
					};
				}

				if (store === 'core') {
					return {
						getEntityRecord: (kind, name, id) => {
							if (
								kind === 'postType' &&
								name === 'page' &&
								id === 42
							) {
								return {
									meta: {
										related_posts: [101, 202],
									},
								};
							}
							return null;
						},
					};
				}

				return {};
			})
		);

		renderHook(() =>
			useQueryPreview({
				source: 'relationship',
				relationshipField: 'related_posts',
				perPage: 3,
			})
		);

		// Must use 'post' — 'any' is not a valid WP REST entity type.
		expect(useEntityRecords).toHaveBeenCalledWith('postType', 'post', {
			include: [101, 202],
			per_page: 3,
			orderby: 'include',
		});
	});
});
