/**
 * Interaction Layers - Live selector feedback
 *
 * A CSS selector typed into a text field is the easiest thing in this panel
 * to get silently wrong. Counting matches inside the editor canvas turns a
 * typo into immediate feedback instead of a dead interaction discovered on
 * the frontend.
 *
 * @package
 */

import { useEffect, useState } from '@wordpress/element';

/**
 * Count how many elements a selector matches in the editor canvas.
 *
 * The canvas is an iframe in current WordPress, so the editor document is
 * not necessarily the panel's own document. Both are checked.
 *
 * @param {string}  selector Author-supplied CSS selector.
 * @param {boolean} enabled  Whether to run the query at all.
 * @return {number|null} Match count, or null when there is nothing to report.
 */
export function useSelectorMatchCount(selector, enabled = true) {
	const [count, setCount] = useState(null);

	useEffect(() => {
		if (!enabled || !selector) {
			setCount(null);
			return undefined;
		}

		// Debounce: the selector is queried on every keystroke otherwise, and
		// a partial selector like ".pa" matches half the canvas.
		const timer = setTimeout(() => {
			const canvas =
				document.querySelector('iframe[name="editor-canvas"]')
					?.contentDocument || document;

			try {
				setCount(canvas.querySelectorAll(selector).length);
			} catch (e) {
				// Invalid selector — report it as such rather than crashing.
				setCount(-1);
			}
		}, 300);

		return () => clearTimeout(timer);
	}, [selector, enabled]);

	return count;
}
