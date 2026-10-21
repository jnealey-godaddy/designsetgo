/**
 * useUniqueBlockId Tests
 *
 * @package
 */

import { renderHook } from '@testing-library/react';
import { RegistryProvider } from '@wordpress/data';
import { useUniqueBlockId } from '../../../src/hooks/useUniqueBlockId';
import { wasJustInserted } from '../../../src/hooks/useWasJustInserted';

jest.mock('../../../src/hooks/useWasJustInserted', () => ({
	wasJustInserted: jest.fn(() => false),
}));

/**
 * A registry whose block-editor store holds the given blocks.
 *
 * @param {Array<{clientId: string, name: string, attributes: Object}>} blocks
 */
function registryWith(blocks) {
	const byId = Object.fromEntries(blocks.map((b) => [b.clientId, b]));
	const selectors = {
		getBlockName: (id) => byId[id]?.name,
		getBlockAttributes: (id) => byId[id]?.attributes,
		getClientIdsWithDescendants: () => Object.keys(byId),
	};
	return { select: () => selectors };
}

function renderWithBlocks(blocks, props) {
	const registry = registryWith(blocks);
	return renderHook(() => useUniqueBlockId(props), {
		wrapper: ({ children }) => (
			<RegistryProvider value={registry}>{children}</RegistryProvider>
		),
	});
}

describe('useUniqueBlockId', () => {
	test('seeds attribute with clientId substring when empty', () => {
		const setAttributes = jest.fn();
		renderHook(() =>
			useUniqueBlockId({
				clientId: 'abcdef1234567890',
				attributeName: 'uniqueId',
				value: undefined,
				setAttributes,
			})
		);
		expect(setAttributes).toHaveBeenCalledWith({ uniqueId: 'abcdef12' });
	});

	test('does not call setAttributes when value already set', () => {
		const setAttributes = jest.fn();
		renderHook(() =>
			useUniqueBlockId({
				clientId: 'abcdef1234567890',
				attributeName: 'uniqueId',
				value: 'existing',
				setAttributes,
			})
		);
		expect(setAttributes).not.toHaveBeenCalled();
	});

	test('seeds when value is empty string', () => {
		const setAttributes = jest.fn();
		renderHook(() =>
			useUniqueBlockId({
				clientId: 'abcdef1234567890',
				attributeName: 'uniqueId',
				value: '',
				setAttributes,
			})
		);
		expect(setAttributes).toHaveBeenCalledWith({ uniqueId: 'abcdef12' });
	});

	test('seeds when value is null', () => {
		const setAttributes = jest.fn();
		renderHook(() =>
			useUniqueBlockId({
				clientId: 'abcdef1234567890',
				attributeName: 'uniqueId',
				value: null,
				setAttributes,
			})
		);
		expect(setAttributes).toHaveBeenCalledWith({ uniqueId: 'abcdef12' });
	});

	test('honors prefix option (full clientId, with prefix)', () => {
		const setAttributes = jest.fn();
		renderHook(() =>
			useUniqueBlockId({
				clientId: 'abcdef1234567890',
				attributeName: 'modalId',
				value: undefined,
				setAttributes,
				prefix: 'dsgo-modal-',
				length: null,
			})
		);
		expect(setAttributes).toHaveBeenCalledWith({
			modalId: 'dsgo-modal-abcdef1234567890',
		});
	});

	test('honors custom length option', () => {
		const setAttributes = jest.fn();
		renderHook(() =>
			useUniqueBlockId({
				clientId: 'abcdef1234567890',
				attributeName: 'uniqueId',
				value: undefined,
				setAttributes,
				length: 6,
			})
		);
		expect(setAttributes).toHaveBeenCalledWith({ uniqueId: 'abcdef' });
	});

	test('does not re-seed when clientId changes after value is set', () => {
		const setAttributes = jest.fn();
		const { rerender } = renderHook(
			({ clientId, value }) =>
				useUniqueBlockId({
					clientId,
					attributeName: 'uniqueId',
					value,
					setAttributes,
				}),
			{ initialProps: { clientId: 'aaaaaaaa11111111', value: undefined } }
		);
		expect(setAttributes).toHaveBeenCalledTimes(1);
		setAttributes.mockClear();
		rerender({ clientId: 'bbbbbbbb22222222', value: 'aaaaaaaa' });
		expect(setAttributes).not.toHaveBeenCalled();
	});

	describe('duplicated blocks', () => {
		const original = {
			clientId: 'original11111111',
			name: 'designsetgo/accordion-item',
			attributes: { uniqueId: 'accordion-item-abc' },
		};
		const copy = {
			clientId: 'copy222222222222',
			name: 'designsetgo/accordion-item',
			attributes: { uniqueId: 'accordion-item-abc' },
		};
		const props = (setAttributes) => ({
			clientId: copy.clientId,
			attributeName: 'uniqueId',
			value: 'accordion-item-abc',
			setAttributes,
			prefix: 'accordion-item-',
		});

		afterEach(() => wasJustInserted.mockReset());

		test('re-seeds a just-inserted copy whose id another block holds', () => {
			wasJustInserted.mockReturnValue(true);
			const setAttributes = jest.fn();
			renderWithBlocks([original, copy], props(setAttributes));
			expect(setAttributes).toHaveBeenCalledWith({
				uniqueId: 'accordion-item-copy2222',
			});
		});

		test('leaves duplicates loaded with the post alone', () => {
			wasJustInserted.mockReturnValue(false);
			const setAttributes = jest.fn();
			renderWithBlocks([original, copy], props(setAttributes));
			expect(setAttributes).not.toHaveBeenCalled();
		});

		test('keeps the id of a just-inserted block that is unique', () => {
			wasJustInserted.mockReturnValue(true);
			const setAttributes = jest.fn();
			renderWithBlocks([copy], props(setAttributes));
			expect(setAttributes).not.toHaveBeenCalled();
		});

		test('ignores the same id on a different block type', () => {
			wasJustInserted.mockReturnValue(true);
			const setAttributes = jest.fn();
			renderWithBlocks(
				[{ ...original, name: 'designsetgo/counter' }, copy],
				props(setAttributes)
			);
			expect(setAttributes).not.toHaveBeenCalled();
		});
	});
});
