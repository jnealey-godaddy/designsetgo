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

describe('useQueryPreview — relationship source', () => {
	beforeEach(() => useEntityRecords.mockReset());

	it('returns empty state when relationshipField is empty', () => {
		useEntityRecords.mockReturnValue({ records: [], hasResolved: true });
		const { result } = renderHook(() =>
			useQueryPreview({ source: 'relationship', relationshipField: '', perPage: 3 })
		);
		expect(result.current.records).toEqual([]);
		expect(result.current.hasResolved).toBe(true);
	});
});
