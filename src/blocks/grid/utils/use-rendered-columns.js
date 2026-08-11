/**
 * Grid — measure the column count the grid is ACTUALLY rendering.
 *
 * The configured desktop column count is an upper bound, not a promise: the
 * column min width builds an `auto-fill` track list that drops a column rather
 * than overflow its container (see ../grid-columns.js). "Align Rows" has to
 * key off what rendered, because a card spanning `--dsgo-row-count` row tracks
 * in a grid that has wrapped to a single column absorbs the row gaps between
 * those tracks and grows taller for nothing — there is no sibling beside it to
 * align to.
 *
 * The frontend does the same measurement in view.js (`getRenderedColumns`).
 */

import { useState, useLayoutEffect } from '@wordpress/element';

/**
 * Read the resolved track count off an element's computed style.
 *
 * @param {HTMLElement} element  Grid container.
 * @param {number}      fallback Count to use when the track list is unreadable.
 * @return {number} Rendered column count.
 */
function readColumnCount(element, fallback) {
	const view = element.ownerDocument?.defaultView;
	if (!view) {
		return fallback;
	}

	const tracks = view.getComputedStyle(element).gridTemplateColumns;
	if (!tracks || tracks === 'none') {
		return fallback;
	}

	return tracks.split(/\s+/).filter(Boolean).length || fallback;
}

/**
 * Track the rendered column count of the element held by `ref`.
 *
 * @param {Object} ref       Ref holding the grid container element.
 * @param {number} fallback  Count to report before the first measurement, and
 *                           whenever the track list can't be read.
 * @param {string} trackList The authored `grid-template-columns` value. Never
 *                           read — it's the re-measure trigger. Editing the
 *                           column min width or the gap changes the resolved
 *                           track count without necessarily resizing the
 *                           container itself, so the observer alone can leave
 *                           the count stale.
 * @return {number} Rendered column count.
 */
export function useRenderedColumns(ref, fallback, trackList) {
	const [columns, setColumns] = useState(fallback);

	// Layout effect, not a plain effect: this runs after the DOM is in place but
	// BEFORE paint, so a grid that actually wraps never paints a frame with row
	// matching on. State starts at the configured count, which is only correct
	// for a grid that doesn't wrap.
	useLayoutEffect(() => {
		const element = ref.current;
		if (!element) {
			return undefined;
		}

		const measure = () => {
			const next = readColumnCount(element, fallback);
			// Only the width drives the track count, so this can't oscillate
			// with the height change that activating row matching causes — but
			// bail on an unchanged value anyway to avoid a wasted render.
			setColumns((current) => (current === next ? current : next));
		};

		// Measure BEFORE subscribing, so the count is right even where
		// ResizeObserver is missing. The observer only adds the ability to keep
		// the count right; going without it must not pin the value at the
		// configured count forever, which would leave row matching active on a
		// grid that has actually wrapped to one column.
		measure();

		const view = element.ownerDocument?.defaultView;
		if (!view?.ResizeObserver) {
			return undefined;
		}

		const observer = new view.ResizeObserver(measure);
		observer.observe(element);
		return () => observer.disconnect();
	}, [ref, fallback, trackList]);

	return columns;
}
