/**
 * Count resolved Grid tracks while ignoring optional named-line groups.
 *
 * @param {string} tracks   Computed grid-template-columns value.
 * @param {number} fallback Configured count if layout cannot be read.
 * @return {number} Number of rendered tracks.
 */
export function countRenderedColumns(tracks, fallback) {
	if (!tracks || tracks === 'none') {
		return fallback;
	}
	return (
		tracks
			.replace(/\[[^\]]*\]/g, '')
			.split(/\s+/)
			.filter(Boolean).length || fallback
	);
}

/**
 * Read authored tracks without implicit columns created by oversized child spans.
 * Inline styles are restored before returning, so this never persists changes.
 *
 * @param {HTMLElement} element  Grid layout element.
 * @param {number}      fallback Configured count if layout cannot be read.
 * @return {number} Rendered template track count.
 */
export function measureRenderedColumns(element, fallback) {
	const view = element.ownerDocument?.defaultView;
	if (!view) {
		return fallback;
	}
	const spans = Array.from(element.children)
		.filter((child) => child.style.gridColumn)
		.map((child) => [child, child.style.gridColumn]);
	spans.forEach(([child]) => {
		child.style.gridColumn = 'auto';
	});
	try {
		return countRenderedColumns(
			view.getComputedStyle(element).gridTemplateColumns,
			fallback
		);
	} finally {
		spans.forEach(([child, span]) => {
			child.style.gridColumn = span;
		});
	}
}
