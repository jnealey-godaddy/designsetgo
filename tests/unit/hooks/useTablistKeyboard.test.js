/**
 * useTablistKeyboard Tests
 *
 * @package
 */

import { renderHook } from '@testing-library/react';
import { useTablistKeyboard } from '../../../src/hooks/useTablistKeyboard';

describe('useTablistKeyboard', () => {
	const makeEvent = (key) => ({ key, preventDefault: jest.fn() });

	test('ArrowRight advances index', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 3, activeIndex: 0, onChange })
		);
		const evt = makeEvent('ArrowRight');
		result.current.onKeyDown(evt);
		expect(evt.preventDefault).toHaveBeenCalled();
		expect(onChange).toHaveBeenCalledWith(1);
	});

	test('ArrowRight wraps from last to first', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 3, activeIndex: 2, onChange })
		);
		result.current.onKeyDown(makeEvent('ArrowRight'));
		expect(onChange).toHaveBeenCalledWith(0);
	});

	test('ArrowLeft decrements index', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 3, activeIndex: 1, onChange })
		);
		result.current.onKeyDown(makeEvent('ArrowLeft'));
		expect(onChange).toHaveBeenCalledWith(0);
	});

	test('ArrowLeft wraps from first to last', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 3, activeIndex: 0, onChange })
		);
		result.current.onKeyDown(makeEvent('ArrowLeft'));
		expect(onChange).toHaveBeenCalledWith(2);
	});

	test('Home jumps to 0, End jumps to last', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 5, activeIndex: 2, onChange })
		);
		result.current.onKeyDown(makeEvent('Home'));
		expect(onChange).toHaveBeenLastCalledWith(0);
		result.current.onKeyDown(makeEvent('End'));
		expect(onChange).toHaveBeenLastCalledWith(4);
	});

	test('vertical orientation swaps Arrow keys', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({
				count: 3,
				activeIndex: 0,
				onChange,
				orientation: 'vertical',
			})
		);
		result.current.onKeyDown(makeEvent('ArrowDown'));
		expect(onChange).toHaveBeenCalledWith(1);
		result.current.onKeyDown(makeEvent('ArrowUp'));
		expect(onChange).toHaveBeenCalledWith(2);
	});

	test('does nothing for unrelated keys', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 3, activeIndex: 1, onChange })
		);
		result.current.onKeyDown(makeEvent('Enter'));
		expect(onChange).not.toHaveBeenCalled();
	});

	test('does nothing when count is 0', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 0, activeIndex: 0, onChange })
		);
		result.current.onKeyDown(makeEvent('ArrowRight'));
		expect(onChange).not.toHaveBeenCalled();
	});
});
