/**
 * Overlay header clearance — regression guard.
 *
 * The overlay header is `position: fixed` (src/styles/utilities/_sticky-header.scss,
 * "Overlay Header"), so it occupies no flow space. A negative `margin-top` on the
 * element after it — which is what the old "Overlay Content Pull-Up" rule applied —
 * therefore reclaims space that was never taken, and cancels the hero clearance that
 * `applyOverlayHeroPadding()` adds:
 *
 *     content top = (T0 - H) + base + (H + gap) = T0 + base + gap
 *
 * H drops out entirely. Measured on a live overlay site, sweeping
 * `--dsgo-overlay-header-height` across 0 / 50 / 116.54 / 300px moved the hero box to
 * 32 / -18 / -84.5 / -268px while the content stayed at 52.6px in every case — 95.9px
 * behind a header whose bottom edge was at 148.5px.
 *
 * The failure is invisible in code review (both terms look individually reasonable and
 * live in different files, one SCSS and one JS) and invisible in jsdom (no layout), so
 * this asserts the contract against the COMPILED stylesheet instead.
 */

const fs = require('fs');
const path = require('path');

// The sticky-header partial does NOT compile into build/style-index.css — it has
// its own entry at build/utils/sticky-header.css. Asserting against style-index
// made every check below pass vacuously on a file that never contained the rule.
const STICKY_CSS = path.join(__dirname, '../../build/utils/sticky-header.css');

describe('overlay header clearance', () => {
	// Guard the guard. A "not.toMatch" assertion passes on any file that simply
	// doesn't contain the feature, so pin that this really is the stylesheet the
	// overlay header ships in before trusting the negative assertions below.
	it('is checking the stylesheet the overlay header actually ships in', () => {
		expect(fs.existsSync(STICKY_CSS)).toBe(true);
		const css = fs.readFileSync(STICKY_CSS, 'utf8');
		expect(css).toContain('dsgo-page-overlay-header');
	});

	it('never applies a negative margin keyed on the measured header height', () => {
		const css = fs.readFileSync(STICKY_CSS, 'utf8');

		// Any `calc(-1 * var(--dsgo-overlay-header-height...))`, whitespace-insensitive
		// and independent of which selector carries it.
		const negativePullUp =
			/calc\(\s*-1\s*\*\s*var\(\s*--dsgo-overlay-header-height/;

		expect(css).not.toMatch(negativePullUp);
	});

	it('composes the clearance as authored padding + header height', () => {
		// The authored term must come FIRST and be its own custom property. The
		// rule carries `!important` (it has to, because WordPress serializes
		// block spacing inline), so if the author's padding were not folded in
		// here it would be silently discarded on every overlay page.
		const css = fs.readFileSync(STICKY_CSS, 'utf8');

		const clearanceRule = css
			.split('}')
			.find((rule) => rule.includes('--dsgo-overlay-hero-base-pad'));

		expect(clearanceRule).toBeDefined();
		expect(clearanceRule).toContain(
			'var(--dsgo-overlay-hero-base-pad, 0px)'
		);
		expect(clearanceRule).toContain('--dsgo-overlay-header-height');
		expect(clearanceRule).toContain('!important');
	});

	it('applies the clearance INSIDE the pulled element, not on it', () => {
		// Putting the padding on the content wrapper itself would push the hero's
		// background down and destroy the overlay — the background running behind
		// the header is the entire feature. The `> :first-child` step is what
		// keeps the background at the top while moving only the content.
		const css = fs.readFileSync(STICKY_CSS, 'utf8');

		const clearanceRule = css
			.split('}')
			.find((rule) => rule.includes('--dsgo-overlay-hero-base-pad'));

		expect(clearanceRule).toContain('+*>:first-child');
	});

	it('still ships the fixed-position overlay header the geometry assumes', () => {
		// The reasoning above is only valid while the header is out of flow. If this
		// ever becomes in-flow (static/sticky), the clearance has to be rethought and
		// the two assertions above stop being correct.
		const css = fs.readFileSync(STICKY_CSS, 'utf8');
		const overlayHeaderRule = css
			.split('}')
			.find(
				(rule) =>
					rule.includes('.dsgo-page-overlay-header') &&
					rule.includes('header.wp-block-template-part') &&
					rule.includes('position:fixed')
			);

		expect(overlayHeaderRule).toBeDefined();
	});
});
