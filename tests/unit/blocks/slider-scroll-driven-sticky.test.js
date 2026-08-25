/**
 * Scroll-driven slider: sticky pinning must depend on the pin spacer.
 *
 * The pin spacer is created at runtime by ScrollDrivenController
 * (src/blocks/slider/view/scroll-driven.js), which never runs in the editor.
 * If `position: sticky` hangs off the `dsgo-slider--scroll-driven` class alone
 * it also applies in the editor canvas — where there is no spacer to scroll
 * through — so the block pins to the top of the canvas and overlaps whatever
 * follows it. Scoping the sticky to a descendant of the spacer keeps the
 * frontend behaviour identical and leaves the editor (and a no-JS frontend)
 * with a normally-flowed block.
 */
const path = require('path');
const sass = require('sass');

const STYLE = path.join(__dirname, '../../../src/blocks/slider/style.scss');

/**
 * Selectors in the compiled sheet that declare `position: sticky`.
 *
 * @param {string} css Compiled stylesheet.
 * @return {string[]} Matching selectors.
 */
function stickySelectors(css) {
	const found = [];
	const ruleRe = /([^{}]+)\{([^{}]*)\}/g;
	let match;
	while ((match = ruleRe.exec(css)) !== null) {
		if (/position\s*:\s*sticky/.test(match[2])) {
			found.push(match[1].trim().replace(/\s+/g, ' '));
		}
	}
	return found;
}

describe('scroll-driven slider sticky scoping', () => {
	const css = sass.compile(STYLE, { loadPaths: [path.dirname(STYLE)] }).css;

	it('never makes the scroll-driven slider sticky outside a pin spacer', () => {
		const offenders = stickySelectors(css)
			.filter((sel) => sel.includes('dsgo-slider--scroll-driven'))
			.filter((sel) => !sel.includes('dsgo-slider-pin-spacer'));

		expect(offenders).toEqual([]);
	});

	it('still pins the slider once the frontend spacer wraps it', () => {
		const pinned = stickySelectors(css).filter(
			(sel) =>
				sel.includes('dsgo-slider-pin-spacer') &&
				sel.includes('dsgo-slider--scroll-driven')
		);

		expect(pinned.length).toBeGreaterThan(0);
	});
});
