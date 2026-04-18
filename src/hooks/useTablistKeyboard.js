/**
 * useTablistKeyboard
 *
 * WAI-ARIA tablist keyboard navigation for editor parent blocks that manage
 * a list of selectable children (tabs, slides, accordion-items, etc.).
 *
 * Ported from the original inline implementation in `blocks/tabs/edit.js` so
 * every compound block can opt in with one import. Horizontal orientation
 * binds ArrowLeft/Right; vertical binds ArrowUp/Down. Home/End jump to the
 * first/last child in both orientations. Movement wraps.
 *
 * Focus is re-anchored to the target child after the parent's active-index
 * update via a `data-*` selector the caller supplies — the handler scopes
 * the `querySelector` to the parent container element so duplicate blocks on
 * the same page don't collide on matching selectors.
 */

import { useCallback } from '@wordpress/element';

const HORIZONTAL_KEYS = new Set(['ArrowLeft', 'ArrowRight']);
const VERTICAL_KEYS = new Set(['ArrowUp', 'ArrowDown']);

/**
 * @param {Object}   options
 * @param {number}   options.itemCount                  Number of tab-like children.
 * @param {Function} options.onIndexChange              Called with the new index after a navigation key.
 * @param {string}   [options.orientation='horizontal'] 'horizontal' | 'vertical'.
 * @param {Function} [options.focusItem]                Optional custom focus handler `(index) => void`.
 *                                                      When omitted, no DOM focus is moved — callers
 *                                                      that render controlled inputs should pass one.
 * @return {(event: KeyboardEvent, index: number) => void} Keydown handler.
 */
export default function useTablistKeyboard({
	itemCount,
	onIndexChange,
	orientation = 'horizontal',
	focusItem,
}) {
	return useCallback(
		(event, index) => {
			if (itemCount <= 1) {
				return;
			}

			const isHorizontal = orientation !== 'vertical';
			const prevKey = isHorizontal ? 'ArrowLeft' : 'ArrowUp';
			const nextKey = isHorizontal ? 'ArrowRight' : 'ArrowDown';
			const orientationKeys = isHorizontal
				? HORIZONTAL_KEYS
				: VERTICAL_KEYS;

			// Keys from the opposing axis are ignored so they pass through to
			// the browser (e.g. caret movement inside a nested text input).
			if (
				!orientationKeys.has(event.key) &&
				event.key !== 'Home' &&
				event.key !== 'End'
			) {
				return;
			}

			let newIndex = index;
			if (event.key === prevKey) {
				newIndex = index > 0 ? index - 1 : itemCount - 1;
			} else if (event.key === nextKey) {
				newIndex = index < itemCount - 1 ? index + 1 : 0;
			} else if (event.key === 'Home') {
				newIndex = 0;
			} else if (event.key === 'End') {
				newIndex = itemCount - 1;
			}

			event.preventDefault();

			if (newIndex === index) {
				return;
			}

			onIndexChange(newIndex);

			if (typeof focusItem === 'function') {
				// Defer until after state flush so the child we're focusing
				// has rendered its `tabIndex=0` / aria-selected state.
				setTimeout(() => focusItem(newIndex), 0);
			}
		},
		[itemCount, orientation, onIndexChange, focusItem]
	);
}
