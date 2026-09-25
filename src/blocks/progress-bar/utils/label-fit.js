/**
 * Progress Bar — does the inside label fit its fill?
 *
 * The inside label sits in the fill, right-aligned, and the track clips
 * anything past its edge. On a narrow fill the label therefore showed as a
 * clipped fragment ("– 12%"). CSS cannot compare a label's width with its
 * fill's, so the front end (view.js) and the editor (edit.js) measure it and
 * add LABEL_CLIPPED_CLASS, which hides the label visually but keeps it for
 * screen readers. MEASURED_CLASS on the block root switches off the
 * container-query fallback in style.scss once a real measurement exists.
 *
 * Shared by view.js and edit.js so both apply the same rule.
 */

export const LABEL_CLIPPED_CLASS = 'dsgo-progress-bar__label--clipped';
export const MEASURED_CLASS = 'dsgo-progress-bar--label-measured';

/**
 * Whether the inside label's full text fits in the fill's content box.
 *
 * Measures the text with a Range rather than the label box, so the result is
 * the same whether or not the label is currently hidden (the hidden state is
 * a 1px box, but its text is still laid out at full width).
 *
 * @param {Element} fill  The `.dsgo-progress-bar__fill` element.
 * @param {Element} label The `.dsgo-progress-bar__label--inside` element.
 * @return {boolean} Whether the label fits.
 */
export function insideLabelFits(fill, label) {
	const doc = fill.ownerDocument;
	const view = doc.defaultView;
	const fillStyle = view.getComputedStyle(fill);
	const labelStyle = view.getComputedStyle(label);

	const available =
		fill.clientWidth -
		(parseFloat(fillStyle.paddingLeft) || 0) -
		(parseFloat(fillStyle.paddingRight) || 0);

	const range = doc.createRange();
	range.selectNodeContents(label);
	const needed =
		range.getBoundingClientRect().width +
		(parseFloat(labelStyle.paddingLeft) || 0) +
		(parseFloat(labelStyle.paddingRight) || 0);

	// Half a pixel of slack absorbs sub-pixel rounding at the boundary.
	return needed <= available + 0.5;
}
