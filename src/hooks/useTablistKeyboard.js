/**
 * useTablistKeyboard
 *
 * Returns a keydown handler implementing ARIA Authoring Practices tablist
 * keyboard navigation (ArrowLeft/Right or ArrowUp/Down + Home/End with
 * wraparound). Generalized from src/blocks/tabs/edit.js lines 141-176.
 *
 * Theme 5 uses this for tabs, slider, scroll-slides, accordion,
 * image-accordion.
 *
 * @param {Object}   params
 * @param {number}   params.count        Number of children.
 * @param {number}   params.activeIndex  Currently active index.
 * @param {Function} params.onChange     Called with new index.
 * @param {'horizontal'|'vertical'} [params.orientation='horizontal']
 * @return {{ onKeyDown: Function }}
 */
import { useCallback } from '@wordpress/element';

export function useTablistKeyboard({
	count,
	activeIndex,
	onChange,
	orientation = 'horizontal',
}) {
	const onKeyDown = useCallback(
		(event) => {
			if (count === 0) {
				return;
			}
			const prev = orientation === 'horizontal' ? 'ArrowLeft' : 'ArrowUp';
			const next = orientation === 'horizontal' ? 'ArrowRight' : 'ArrowDown';

			let newIndex = activeIndex;
			if (event.key === next) {
				newIndex = activeIndex < count - 1 ? activeIndex + 1 : 0;
			} else if (event.key === prev) {
				newIndex = activeIndex > 0 ? activeIndex - 1 : count - 1;
			} else if (event.key === 'Home') {
				newIndex = 0;
			} else if (event.key === 'End') {
				newIndex = count - 1;
			} else {
				return;
			}
			event.preventDefault();
			onChange(newIndex);
		},
		[count, activeIndex, onChange, orientation]
	);

	return { onKeyDown };
}
