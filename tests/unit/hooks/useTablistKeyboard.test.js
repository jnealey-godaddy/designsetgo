/**
 * useTablistKeyboard — Hook Unit Tests
 *
 * Exercises the shared ARIA tablist keyboard navigation ported from
 * tabs/edit.js as part of Theme 5. Hook consumers: tabs, slider,
 * scroll-slides, accordion, image-accordion.
 */

/* global KeyboardEvent */

import { renderHook } from '@testing-library/react';
import useTablistKeyboard from '../../../src/hooks/useTablistKeyboard';

function createKeyEvent(key) {
	const event = new KeyboardEvent('keydown', {
		key,
		bubbles: true,
		cancelable: true,
	});
	// preventDefault is fired inside the hook — jsdom's KeyboardEvent
	// supports it but we spy so we can assert.
	jest.spyOn(event, 'preventDefault');
	return event;
}

describe('useTablistKeyboard', () => {
	describe('horizontal orientation', () => {
		test('ArrowRight advances to next index', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 3,
					orientation: 'horizontal',
					onIndexChange,
				})
			);

			const event = createKeyEvent('ArrowRight');
			result.current(event, 0);

			expect(onIndexChange).toHaveBeenCalledWith(1);
			expect(event.preventDefault).toHaveBeenCalled();
		});

		test('ArrowLeft moves to previous index', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 3,
					orientation: 'horizontal',
					onIndexChange,
				})
			);

			result.current(createKeyEvent('ArrowLeft'), 2);
			expect(onIndexChange).toHaveBeenCalledWith(1);
		});

		test('ArrowRight wraps from last to first', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 3,
					orientation: 'horizontal',
					onIndexChange,
				})
			);

			result.current(createKeyEvent('ArrowRight'), 2);
			expect(onIndexChange).toHaveBeenCalledWith(0);
		});

		test('ArrowLeft wraps from first to last', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 3,
					orientation: 'horizontal',
					onIndexChange,
				})
			);

			result.current(createKeyEvent('ArrowLeft'), 0);
			expect(onIndexChange).toHaveBeenCalledWith(2);
		});

		test('ArrowUp/ArrowDown are ignored on horizontal (pass through)', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 3,
					orientation: 'horizontal',
					onIndexChange,
				})
			);

			const up = createKeyEvent('ArrowUp');
			const down = createKeyEvent('ArrowDown');
			result.current(up, 0);
			result.current(down, 0);

			expect(onIndexChange).not.toHaveBeenCalled();
			expect(up.preventDefault).not.toHaveBeenCalled();
			expect(down.preventDefault).not.toHaveBeenCalled();
		});
	});

	describe('vertical orientation', () => {
		test('ArrowDown advances, ArrowUp retreats', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 4,
					orientation: 'vertical',
					onIndexChange,
				})
			);

			result.current(createKeyEvent('ArrowDown'), 1);
			expect(onIndexChange).toHaveBeenCalledWith(2);

			result.current(createKeyEvent('ArrowUp'), 2);
			expect(onIndexChange).toHaveBeenCalledWith(1);
		});

		test('ArrowLeft/ArrowRight are ignored on vertical', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 3,
					orientation: 'vertical',
					onIndexChange,
				})
			);

			result.current(createKeyEvent('ArrowLeft'), 1);
			result.current(createKeyEvent('ArrowRight'), 1);

			expect(onIndexChange).not.toHaveBeenCalled();
		});
	});

	describe('Home and End', () => {
		test('Home jumps to first index regardless of orientation', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 5,
					orientation: 'horizontal',
					onIndexChange,
				})
			);

			result.current(createKeyEvent('Home'), 3);
			expect(onIndexChange).toHaveBeenCalledWith(0);
		});

		test('End jumps to last index', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 5,
					orientation: 'vertical',
					onIndexChange,
				})
			);

			result.current(createKeyEvent('End'), 1);
			expect(onIndexChange).toHaveBeenCalledWith(4);
		});
	});

	describe('edge cases', () => {
		test('single-item lists do not move', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 1,
					onIndexChange,
				})
			);

			result.current(createKeyEvent('ArrowRight'), 0);
			result.current(createKeyEvent('End'), 0);
			expect(onIndexChange).not.toHaveBeenCalled();
		});

		test('single-item lists still consume navigation keys (no page scroll)', () => {
			// WAI-ARIA tablist semantics: the widget "owns" arrow/Home/End
			// regardless of item count, so a reduced tablist doesn't let
			// those keys bubble up and trigger page-level scroll. Regression
			// guard for the original inline handler's behavior.
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 1,
					onIndexChange,
				})
			);

			const arrow = createKeyEvent('ArrowRight');
			const end = createKeyEvent('End');
			const home = createKeyEvent('Home');
			result.current(arrow, 0);
			result.current(end, 0);
			result.current(home, 0);

			expect(arrow.preventDefault).toHaveBeenCalled();
			expect(end.preventDefault).toHaveBeenCalled();
			expect(home.preventDefault).toHaveBeenCalled();
			expect(onIndexChange).not.toHaveBeenCalled();
		});

		test('invokes focusItem after a navigation key', () => {
			jest.useFakeTimers();
			const focusItem = jest.fn();
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 3,
					orientation: 'horizontal',
					onIndexChange,
					focusItem,
				})
			);

			result.current(createKeyEvent('ArrowRight'), 0);
			jest.runAllTimers();

			expect(focusItem).toHaveBeenCalledWith(1);
			jest.useRealTimers();
		});

		test('does not invoke onIndexChange if index would not change', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 3,
					orientation: 'horizontal',
					onIndexChange,
				})
			);

			result.current(createKeyEvent('Home'), 0);
			expect(onIndexChange).not.toHaveBeenCalled();
		});

		test('non-navigation keys are ignored', () => {
			const onIndexChange = jest.fn();
			const { result } = renderHook(() =>
				useTablistKeyboard({
					itemCount: 3,
					onIndexChange,
				})
			);

			const event = createKeyEvent('a');
			result.current(event, 1);

			expect(onIndexChange).not.toHaveBeenCalled();
			expect(event.preventDefault).not.toHaveBeenCalled();
		});
	});
});
