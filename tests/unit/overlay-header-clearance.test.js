/**
 * Overlay header clearance — regression guard.
 *
 * The overlay header is `position: fixed`, so it occupies no flow space. A
 * negative `margin-top` on the element after it — which is what the old
 * "Overlay Content Pull-Up" rule applied — therefore reclaims space that was
 * never taken, and cancels the hero clearance exactly:
 *
 *     content top = (T0 - H) + base + (H + gap) = T0 + base + gap
 *
 * H drops out. Measured on a live overlay site, sweeping
 * `--dsgo-overlay-header-height` across 0 / 50 / 116.54 / 300px moved the hero
 * box to 32 / -18 / -84.5 / -268px while the content stayed at 52.6px in every
 * case — 95.9px behind a header whose bottom edge was at 148.5px.
 *
 * The failure is invisible in code review (both terms look individually
 * reasonable and lived in different files, one SCSS and one JS) and invisible
 * in jsdom (no layout engine), so this asserts the contract against the
 * stylesheet text.
 *
 * It reads the SOURCE rather than the compiled bundle on purpose. CI's
 * "Lint & Unit Tests" job runs `npm run test:unit` with no build step, so
 * anything asserted against `build/` fails there while passing locally.
 * Comments are stripped first, because the block comment above the deleted
 * rule quotes it verbatim — without stripping, the guard would be defeated by
 * its own documentation.
 */

const fs = require('fs');
const path = require('path');

const SOURCE = path.join(
	__dirname,
	'../../src/styles/utilities/_sticky-header.scss'
);

/**
 * Stylesheet source with comments removed, so assertions see only real rules.
 *
 * @return {string} Comment-free SCSS.
 */
function rules() {
	return fs
		.readFileSync(SOURCE, 'utf8')
		.replace(/\/\*[\s\S]*?\*\//g, '')
		.replace(/(^|\s)\/\/.*$/gm, '');
}

describe('overlay header clearance', () => {
	// Guard the guard. Every assertion below except this one is a negative or a
	// substring match, either of which passes happily against a file that was
	// renamed, emptied, or over-stripped.
	it('is reading a stylesheet that still defines the overlay header', () => {
		const css = rules();

		expect(css).toContain('dsgo-page-overlay-header');
		expect(css.length).toBeGreaterThan(1000);
	});

	it('never applies a negative margin keyed on the measured header height', () => {
		expect(rules()).not.toMatch(
			/calc\(\s*-1\s*\*\s*var\(\s*--dsgo-overlay-header-height/
		);
	});

	it('composes the clearance as authored padding + header height', () => {
		// The authored term must come FIRST and be its own custom property. The
		// rule carries `!important` (it has to, because WordPress serializes
		// block spacing inline), so if the author's padding were not folded in
		// here it would be silently discarded on every overlay page.
		const clearance = rules()
			.split('}')
			.find((rule) => rule.includes('--dsgo-overlay-hero-base-pad'));

		expect(clearance).toBeDefined();
		expect(clearance).toContain('var(--dsgo-overlay-hero-base-pad, 0px)');
		expect(clearance).toContain('--dsgo-overlay-header-height');
		expect(clearance).toContain('!important');
	});

	it('applies the clearance INSIDE the pulled element, not on it', () => {
		// Putting the padding on the content wrapper itself would push the hero's
		// background down and destroy the overlay — the background running behind
		// the header is the entire feature. The `> :first-child` step is what
		// keeps the background at the top while moving only the content.
		const clearance = rules()
			.split('}')
			.find((rule) => rule.includes('--dsgo-overlay-hero-base-pad'));

		expect(clearance).toContain('+ * > :first-child');
	});

	it('flushes the content to the top with a constant, not a measurement', () => {
		// The theme's top-level block gap is what actually needed removing, and
		// it is unrelated to the header height (57.4px vs 82.4px on Twenty
		// Twenty-Five). Being a constant is what lets this land at first paint.
		const flush = rules()
			.split('}')
			.find(
				(rule) =>
					rule.includes('header.wp-block-template-part + *') &&
					rule.includes('margin-top')
			);

		expect(flush).toBeDefined();
		expect(flush).toMatch(/margin-top:\s*0\s*!important/);
	});

	it('still ships the fixed-position overlay header the geometry assumes', () => {
		// The reasoning above is only valid while the header is out of flow. If
		// it ever becomes in-flow (static/sticky), the clearance has to be
		// rethought and the assertions above stop being correct.
		const overlayHeader = rules()
			.split('}')
			.find(
				(rule) =>
					rule.includes('.dsgo-page-overlay-header') &&
					rule.includes('header.wp-block-template-part') &&
					/position:\s*fixed/.test(rule)
			);

		expect(overlayHeader).toBeDefined();
	});
});
