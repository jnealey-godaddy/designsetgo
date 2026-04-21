import { renderHook } from '@testing-library/react';
import useQueryHostPreview from '../../../../src/blocks/query/hooks/useQueryHostPreview';

jest.mock('@wordpress/core-data', () => ({
	useEntityRecords: jest.fn(),
}));

jest.mock('@wordpress/api-fetch', () => jest.fn());

jest.mock('@wordpress/url', () => ({
	addQueryArgs: jest.fn((path) => path),
}));

jest.mock('@wordpress/i18n', () => ({
	__: (text) => text,
	sprintf: (text, value) => text.replace('%d', value),
}));

jest.mock(
	'../../../../src/blocks/query/hooks/useQueryPreview',
	() =>
		jest.fn(() => ({
			records: [],
			hasResolved: true,
		}))
);

jest.mock(
	'../../../../src/blocks/query/hooks/useRenderedItems',
	() =>
		jest.fn(() => ({
			items: null,
			loading: false,
			error: null,
		}))
);

const { useEntityRecords } = require('@wordpress/core-data');
const apiFetch = require('@wordpress/api-fetch');

describe('useQueryHostPreview', () => {
	beforeEach(() => {
		jest.clearAllMocks();
		useEntityRecords.mockReturnValue({
			records: [],
			hasResolved: true,
		});
	});

	it.each(['manual', 'current'])(
		'treats %s as a posts-family source for editor previews',
		(source) => {
			renderHook(() =>
				useQueryHostPreview({
					attributes: {
						source,
						postType: 'post',
						perPage: 4,
						orderBy: 'date',
						order: 'DESC',
					},
					queryId: 'query-preview',
					innerBlocks: [],
				})
			);

			expect(useEntityRecords).toHaveBeenCalledWith(
				'postType',
				'post',
				expect.objectContaining({
					per_page: 4,
					orderby: 'date',
					order: 'desc',
				})
			);
			expect(apiFetch).not.toHaveBeenCalled();
		}
	);
});
