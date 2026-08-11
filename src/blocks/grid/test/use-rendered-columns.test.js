/**
 * Grid — useRenderedColumns tests.
 *
 * jsdom does no layout and ships no ResizeObserver, so both are stubbed: the
 * hook's contract is "read the resolved track list, keep it current", and both
 * halves of that are worth pinning independently.
 */

import { renderHook, act } from '@testing-library/react';
import { useRenderedColumns } from '../utils/use-rendered-columns';

/**
 * Stub the resolved track list a browser would report for the grid element.
 *
 * @param {number} count Rendered column count to report.
 */
function stubTracks(count) {
	jest.spyOn(window, 'getComputedStyle').mockReturnValue({
		gridTemplateColumns: '364px '.repeat(count).trim(),
	});
}

/**
 * Install a controllable ResizeObserver stub.
 *
 * @return {Object} Handle exposing `fire()` and the disconnect spy.
 */
function stubResizeObserver() {
	const handle = { callbacks: [], disconnect: jest.fn() };
	window.ResizeObserver = class {
		constructor(cb) {
			handle.callbacks.push(cb);
		}
		observe() {}
		disconnect() {
			handle.disconnect();
		}
	};
	handle.fire = () => act(() => handle.callbacks.forEach((cb) => cb()));
	return handle;
}

describe('useRenderedColumns', () => {
	let element;

	beforeEach(() => {
		element = document.createElement('div');
		document.body.appendChild(element);
	});

	afterEach(() => {
		document.body.innerHTML = '';
		delete window.ResizeObserver;
		jest.restoreAllMocks();
	});

	test('measures the resolved track count on mount', () => {
		stubResizeObserver();
		stubTracks(2);
		const { result } = renderHook(() =>
			useRenderedColumns({ current: element }, 3, 'repeat(3, 1fr)')
		);
		expect(result.current).toBe(2);
	});

	test('measures once even without ResizeObserver support', () => {
		// No observer stub installed: the count must still be accurate, not
		// pinned at the configured fallback (which would leave row matching
		// active on a grid that has wrapped to a single column).
		stubTracks(1);
		const { result } = renderHook(() =>
			useRenderedColumns({ current: element }, 3, 'repeat(3, 1fr)')
		);
		expect(result.current).toBe(1);
	});

	test('re-measures when the track list changes, without any resize', () => {
		// Editing the column min width or gap can change the resolved track
		// count while the container's own box size stays put, so the observer
		// alone would leave the count stale.
		stubResizeObserver();
		stubTracks(3);
		const { result, rerender } = renderHook(
			({ tracks }) => useRenderedColumns({ current: element }, 3, tracks),
			{ initialProps: { tracks: 'repeat(3, 1fr)' } }
		);
		expect(result.current).toBe(3);

		stubTracks(1);
		rerender({ tracks: 'repeat(auto-fill, minmax(480px, 1fr))' });
		expect(result.current).toBe(1);
	});

	test('re-measures when the observer fires', () => {
		const observer = stubResizeObserver();
		stubTracks(3);
		const { result } = renderHook(() =>
			useRenderedColumns({ current: element }, 3, 'repeat(3, 1fr)')
		);
		expect(result.current).toBe(3);

		stubTracks(2);
		observer.fire();
		expect(result.current).toBe(2);
	});

	test('falls back to the configured count when the track list is unreadable', () => {
		stubResizeObserver();
		jest.spyOn(window, 'getComputedStyle').mockReturnValue({
			gridTemplateColumns: 'none',
		});
		const { result } = renderHook(() =>
			useRenderedColumns({ current: element }, 4, 'repeat(4, 1fr)')
		);
		expect(result.current).toBe(4);
	});

	test('disconnects the observer on unmount', () => {
		const observer = stubResizeObserver();
		stubTracks(3);
		const { unmount } = renderHook(() =>
			useRenderedColumns({ current: element }, 3, 'repeat(3, 1fr)')
		);
		unmount();
		expect(observer.disconnect).toHaveBeenCalled();
	});
});
