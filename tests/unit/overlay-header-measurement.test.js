/**
 * Overlay header measurement — sticky-header.js DOM logic
 *
 * Pins the two bugs that made the overlay header unusable:
 *
 * 1. `--dsgo-overlay-header-height` was written by every element the sticky
 *    selector matched. That selector includes
 *    `.wp-block-template-part:has(.wp-block-navigation)`, which matches the
 *    footer on most themes, so the last writer in DOM order won and content
 *    was pulled up by the footer's height instead of the header's.
 * 2. The first content section needs the header height added back as top
 *    padding, or the overlay pull-up leaves its content under the header.
 */

const HEADER_HEIGHT = 92;
const FOOTER_HEIGHT = 410;

/**
 * Build a site structure matching a block theme: a header template part and a
 * footer template part that BOTH contain a navigation, plus a content wrapper
 * whose first child is the hero.
 *
 * @param {Object} options                   Options.
 * @param {string} options.heroInlinePadding Inline padding-top on the hero.
 * @return {Object} References to the created elements.
 */
function buildSite({ heroInlinePadding = '' } = {}) {
	document.body.className = 'dsgo-page-overlay-header';
	document.body.innerHTML = `
		<div class="wp-site-blocks">
			<header class="wp-block-template-part">
				<div class="wp-block-group">
					<nav class="wp-block-navigation"></nav>
				</div>
			</header>
			<div class="entry-content">
				<div class="wp-block-cover"${
					heroInlinePadding
						? ` style="padding-top:${heroInlinePadding}"`
						: ''
				}></div>
			</div>
			<footer class="wp-block-template-part">
				<div class="wp-block-group">
					<nav class="wp-block-navigation"></nav>
				</div>
			</footer>
		</div>
	`;

	const header = document.querySelector('.wp-site-blocks > header');
	const footer = document.querySelector('.wp-site-blocks > footer');
	const hero = document.querySelector('.wp-block-cover');

	// jsdom has no layout, so heights come from stubs. The footer is
	// deliberately taller — that difference is the whole regression.
	header.getBoundingClientRect = () => ({ height: HEADER_HEIGHT, top: 0 });
	footer.getBoundingClientRect = () => ({ height: FOOTER_HEIGHT, top: 0 });

	return { header, footer, hero };
}

/**
 * Load sticky-header.js fresh. The module is an IIFE that runs its init on
 * import, so the DOM must be in place before it is required.
 */
function loadStickyHeader() {
	jest.isolateModules(() => {
		require('../../src/utils/sticky-header.js');
	});
}

describe('overlay header measurement', () => {
	beforeEach(() => {
		document.documentElement.removeAttribute('style');
		document.body.className = '';
		document.body.innerHTML = '';
		window.dsgStickyHeaderSettings = {
			enable: true,
			mobileBreakpoint: 768,
		};
	});

	const readHeightVar = () =>
		document.documentElement.style.getPropertyValue(
			'--dsgo-overlay-header-height'
		);

	it('measures the header, not a footer that also contains a navigation', () => {
		buildSite();

		loadStickyHeader();

		expect(readHeightVar()).toBe(`${HEADER_HEIGHT}px`);
		expect(readHeightVar()).not.toBe(`${FOOTER_HEIGHT}px`);
	});

	it('adds the header clearance to the hero without discarding authored padding', () => {
		const { hero } = buildSite({
			heroInlinePadding: 'var(--wp--preset--spacing--60)',
		});

		loadStickyHeader();

		// The authored value survives as a CSS string, so a fluid preset stays
		// fluid, and the clearance term stays live via the custom property.
		expect(hero.style.paddingTop).toBe(
			'calc(var(--wp--preset--spacing--60) + var(--dsgo-overlay-hero-clearance, var(--dsgo-overlay-header-height, 0px)))'
		);
	});

	it('does not compound the clearance when init runs more than once', () => {
		const { hero } = buildSite({ heroInlinePadding: '40px' });

		loadStickyHeader();
		const afterFirst = hero.style.paddingTop;

		// A soft navigation re-runs initAll via this event.
		document.dispatchEvent(new Event('dsgo-content-loaded'));

		expect(hero.style.paddingTop).toBe(afterFirst);
		expect(hero.dataset.dsgoOverlayBasePaddingTop).toBe('40px');
	});

	it('leaves the height variable alone when the page has no overlay header', () => {
		buildSite();
		document.body.className = '';

		loadStickyHeader();

		expect(readHeightVar()).toBe('');
	});
});
