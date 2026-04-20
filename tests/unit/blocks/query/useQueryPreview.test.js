import { renderHook } from '@testing-library/react';
import useQueryPreview from '../../../../src/blocks/query/hooks/useQueryPreview';

jest.mock('@wordpress/core-data', () => ({
	store: 'core',
	useEntityRecords: jest.fn(),
}));
jest.mock('@wordpress/data', () => ({
	useSelect: jest.fn(() => null),
}));

const { useEntityRecords } = require('@wordpress/core-data');
const { useSelect } = require('@wordpress/data');

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

		expect(useEntityRecords).toHaveBeenCalledWith('postType', 'any', {
			include: [101, 202],
			per_page: 3,
			orderby: 'include',
		});
	});
});
