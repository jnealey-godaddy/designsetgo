/**
 * useFixedOrientation Tests
 *
 * Section and Row hide the orientation toggle, so a layout stored with the
 * wrong orientation has to be corrected by the block itself, without an undo
 * step and without touching the rest of the layout.
 *
 * @package
 */

import { renderHook } from '@testing-library/react';
import { useFixedOrientation } from '../../../src/hooks/useFixedOrientation';

const mockMarkNotPersistent = jest.fn();

jest.mock('@wordpress/data', () => ({
	useDispatch: () => ({
		__unstableMarkNextChangeAsNotPersistent: mockMarkNotPersistent,
	}),
}));

jest.mock('@wordpress/block-editor', () => ({
	store: 'core/block-editor',
}));

describe('useFixedOrientation', () => {
	beforeEach(() => mockMarkNotPersistent.mockClear());

	test('corrects the wrong orientation and keeps the rest of the layout', () => {
		const setAttributes = jest.fn();
		const layout = {
			type: 'flex',
			orientation: 'vertical',
			justifyContent: 'left',
		};

		renderHook(() =>
			useFixedOrientation(layout, 'horizontal', setAttributes)
		);

		expect(mockMarkNotPersistent).toHaveBeenCalledTimes(1);
		expect(setAttributes).toHaveBeenCalledWith({
			layout: { ...layout, orientation: 'horizontal' },
		});
	});

	test('leaves the right orientation alone', () => {
		const setAttributes = jest.fn();

		renderHook(() =>
			useFixedOrientation(
				{ type: 'flex', orientation: 'horizontal' },
				'horizontal',
				setAttributes
			)
		);

		expect(setAttributes).not.toHaveBeenCalled();
	});

	test('leaves an unset layout to the block default', () => {
		const setAttributes = jest.fn();

		renderHook(() =>
			useFixedOrientation(undefined, 'vertical', setAttributes)
		);

		expect(setAttributes).not.toHaveBeenCalled();
		expect(mockMarkNotPersistent).not.toHaveBeenCalled();
	});
});
