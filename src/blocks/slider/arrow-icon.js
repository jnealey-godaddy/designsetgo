/**
 * Slider — arrow chevron.
 *
 * The arrows used to be the typographic characters ‹ and › in a <span>. Those
 * are single-angle quotation marks, not icons: their weight, size and optical
 * centring come from whatever font the theme happens to load, so the same
 * slider rendered differently on every site and often sat visibly off-centre
 * in its button. A path renders identically everywhere.
 *
 * Defined once and consumed twice — view/chrome.js builds the frontend arrows
 * with DOM APIs, edit.js renders the editor's inert placeholders as JSX — so
 * the two cannot drift apart.
 *
 * @since 2.7.0
 */

/** Chevron path data, keyed by the direction the arrow points. */
export const ARROW_PATHS = {
	prev: 'M15 5 L8 12 L15 19',
	next: 'M9 5 L16 12 L9 19',
};

/** Attributes shared by both renderers, so the two stay identical. */
export const ARROW_SVG_ATTRS = {
	viewBox: '0 0 24 24',
	fill: 'none',
	stroke: 'currentColor',
	strokeWidth: '2',
	strokeLinecap: 'round',
	strokeLinejoin: 'round',
};

/**
 * Build an arrow chevron as a DOM node.
 *
 * Sized in `em` so the block's existing `--dsgo-slider-arrow-size` custom
 * property — which drove the glyph's font-size — keeps working untouched.
 *
 * @param {string} direction Either `prev` or `next`.
 * @return {Element} The chevron, hidden from assistive tech (the button
 *                   carries the accessible name).
 */
export function createArrowIcon(direction) {
	const ns = 'http://www.w3.org/2000/svg';
	const svg = document.createElementNS(ns, 'svg');
	svg.setAttribute('viewBox', ARROW_SVG_ATTRS.viewBox);
	svg.setAttribute('fill', ARROW_SVG_ATTRS.fill);
	svg.setAttribute('stroke', ARROW_SVG_ATTRS.stroke);
	svg.setAttribute('stroke-width', ARROW_SVG_ATTRS.strokeWidth);
	svg.setAttribute('stroke-linecap', ARROW_SVG_ATTRS.strokeLinecap);
	svg.setAttribute('stroke-linejoin', ARROW_SVG_ATTRS.strokeLinejoin);
	svg.setAttribute('width', '1em');
	svg.setAttribute('height', '1em');
	svg.setAttribute('aria-hidden', 'true');
	// IE/Edge legacy put SVG in the tab order; harmless elsewhere, and cheap.
	svg.setAttribute('focusable', 'false');

	const path = document.createElementNS(ns, 'path');
	path.setAttribute('d', ARROW_PATHS[direction] || ARROW_PATHS.next);
	svg.appendChild(path);

	return svg;
}
