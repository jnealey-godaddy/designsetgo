import { renderHook } from '@testing-library/react';

const mockUseUniqueBlockId = jest.fn();
const mockUseSelect = jest.fn();

jest.mock('../../../../src/hooks', () => ({
	useUniqueBlockId: (...args) => mockUseUniqueBlockId(...args),
}));

jest.mock('@wordpress/data', () => ({
	useSelect: (...args) => mockUseSelect(...args),
}));

jest.mock('@wordpress/block-editor', () => ({
	store: 'core/block-editor',
}));

import useQueryId from '../../../../src/blocks/query/hooks/useQueryId';

describe('useQueryId', () => {
	beforeEach(() => {
		mockUseUniqueBlockId.mockReset();
		mockUseSelect.mockReset();
	});

	it('clears the copied block queryId when another query block already owns it', () => {
		mockUseSelect.mockImplementation((selector) =>
			selector(() => ({
				getBlocks: () => [
					{
						name: 'designsetgo/query',
						clientId: 'original',
						attributes: { queryId: 'dup-id' },
						innerBlocks: [],
					},
					{
						name: 'designsetgo/query',
						clientId: 'copy',
						attributes: { queryId: 'dup-id' },
						innerBlocks: [],
					},
				],
			}))
		);

		const setAttributes = jest.fn();
		renderHook(() =>
			useQueryId({
				clientId: 'copy',
				queryId: 'dup-id',
				setAttributes,
			})
		);

		expect(setAttributes).toHaveBeenCalledWith({ queryId: '' });
	});

	it('leaves the first block with a given queryId unchanged', () => {
		mockUseSelect.mockImplementation((selector) =>
			selector(() => ({
				getBlocks: () => [
					{
						name: 'designsetgo/query',
						clientId: 'original',
						attributes: { queryId: 'dup-id' },
						innerBlocks: [],
					},
					{
						name: 'designsetgo/query',
						clientId: 'copy',
						attributes: { queryId: 'dup-id' },
						innerBlocks: [],
					},
				],
			}))
		);

		const setAttributes = jest.fn();
		renderHook(() =>
			useQueryId({
				clientId: 'original',
				queryId: 'dup-id',
				setAttributes,
			})
		);

		expect(setAttributes).not.toHaveBeenCalledWith({ queryId: '' });
		expect(mockUseUniqueBlockId).toHaveBeenCalled();
	});
});
