/**
 * Scroll an element into view below any fixed or sticky bar at the top of
 * the viewport (a sticky site header, the admin bar).
 *
 * `scrollIntoView()` and `focus()` only honour `scroll-padding-top`, which
 * themes rarely set to their sticky header's height, so a revealed element
 * can land underneath the header.
 */

/** Space kept between the top bar and the revealed element, in px. */
const GAP = 16;

/**
 * Bottom edge of the fixed or sticky boxes covering the top of the viewport.
 *
 * Samples three points along a line and walks up from each hit to the
 * nearest fixed/sticky ancestor, starting at the top edge and moving the line
 * just below each bar found, so stacked bars (the admin bar above a sticky
 * header) add up. Boxes taller than half the viewport (a full-screen
 * overlay, a sticky sidebar) are not treated as a top bar.
 *
 * @param {Object} win Window to measure.
 * @return {number} Covered height in px, 0 when nothing covers the top.
 */
export function topObstruction(win = window) {
	const doc = win.document;
	if (typeof doc.elementsFromPoint !== 'function') {
		return 0;
	}
	const width = doc.documentElement.clientWidth || win.innerWidth;
	let bottom = 0;
	// A few rounds cover any realistic stack of top bars.
	for (let round = 0; round < 4; round++) {
		const line = bottom + 1;
		let next = bottom;
		[0.25, 0.5, 0.75].forEach((fraction) => {
			doc.elementsFromPoint(width * fraction, line).forEach((hit) => {
				for (
					let node = hit;
					node && node !== doc.body && node !== doc.documentElement;
					node = node.parentElement
				) {
					const { position } = win.getComputedStyle(node);
					if (position === 'fixed' || position === 'sticky') {
						const rect = node.getBoundingClientRect();
						if (
							rect.top <= line &&
							rect.height < win.innerHeight / 2 &&
							rect.bottom > next
						) {
							next = rect.bottom;
						}
						return;
					}
				}
			});
		});
		if (next <= bottom) {
			break;
		}
		bottom = next;
	}
	return bottom;
}

/**
 * Bring `start` and `end` into view, top-aligned below the top bar.
 *
 * Does nothing when `start`'s top edge already sits below the bar and
 * `end`'s bottom edge is inside the viewport, so a step change near the top
 * of the page doesn't jump.
 *
 * @param {Element} start First element that must be visible (e.g. the progress indicator).
 * @param {Element} end   Last element that must be visible (e.g. the step heading).
 * @param {Object}  win   Window to scroll.
 */
export function revealBelowHeader(start, end = start, win = window) {
	if (!start || typeof start.getBoundingClientRect !== 'function') {
		return;
	}
	const limit = topObstruction(win) + GAP;
	const top = start.getBoundingClientRect().top;
	const bottom = (end || start).getBoundingClientRect().bottom;
	if (top >= limit && bottom <= win.innerHeight) {
		return;
	}
	if (typeof win.scrollBy === 'function') {
		win.scrollBy({ top: top - limit, left: 0, behavior: 'auto' });
	}
}
