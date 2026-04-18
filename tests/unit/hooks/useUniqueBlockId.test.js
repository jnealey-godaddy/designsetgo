/**
 * useUniqueBlockId Tests
 *
 * @package
 */

import { renderHook } from '@testing-library/react';
import { useUniqueBlockId } from '../../../src/hooks/useUniqueBlockId';

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
});
