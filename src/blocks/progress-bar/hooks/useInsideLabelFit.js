/**
 * Progress Bar — editor measurement of the inside label.
 *
 * The editor twin of view.js's fitInsideLabels(): measures the label against
 * its fill with the same helper, so an author sees the label hidden exactly
 * where a visitor will.
 */

import { useLayoutEffect, useRef, useState } from '@wordpress/element';
import { insideLabelFits } from '../utils/label-fit';

/**
 * @param {boolean} enabled Whether an inside label is rendered.
 * @param {string}  text    The label text, so a text change re-measures.
 * @return {[Object, boolean]} A ref for the fill element, and whether the
 *                             label is clipped.
 */
export default function useInsideLabelFit(enabled, text) {
	const fillRef = useRef(null);
	const [clipped, setClipped] = useState(false);

	useLayoutEffect(() => {
		const fill = fillRef.current;
		const label = fill?.querySelector('.dsgo-progress-bar__label--inside');
		if (!enabled || !label) {
			setClipped(false);
			return undefined;
		}

		const update = () => setClipped(!insideLabelFits(fill, label));
		update();

		// The editor canvas is an iframe: use its window's ResizeObserver.
		const { ResizeObserver: CanvasResizeObserver } =
			fill.ownerDocument.defaultView;
		if (!CanvasResizeObserver) {
			return undefined;
		}
		const observer = new CanvasResizeObserver(update);
		observer.observe(fill);
		return () => observer.disconnect();
	}, [enabled, text]);

	return [fillRef, clipped];
}
