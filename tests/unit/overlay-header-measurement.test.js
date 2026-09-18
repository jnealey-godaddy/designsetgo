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

	it('publishes the authored padding for the stylesheet to compose', () => {
		const { hero } = buildSite({
			heroInlinePadding: 'var(--wp--preset--spacing--60)',
		});

		loadStickyHeader();

		// The clearance itself now lives in _sticky-header.scss so that it can
		// apply at FIRST PAINT — a JS-written inline padding could not, which is
		// what made the hero snap down a full header height. This script's job is
		// only to hand over the authored term, which the rule adds to the
		// clearance. It stays a CSS string so a fluid preset stays fluid.
		expect(
			hero.style.getPropertyValue('--dsgo-overlay-hero-base-pad')
		).toBe('var(--wp--preset--spacing--60)');
	});

	it('does not compound the clearance when init runs more than once', () => {
		const { hero } = buildSite({ heroInlinePadding: '40px' });

		loadStickyHeader();
		const afterFirst = hero.style.getPropertyValue(
			'--dsgo-overlay-hero-base-pad'
		);

		// A soft navigation re-runs initAll via this event.
		document.dispatchEvent(new Event('dsgo-content-loaded'));

		expect(
			hero.style.getPropertyValue('--dsgo-overlay-hero-base-pad')
		).toBe(afterFirst);
		expect(hero.dataset.dsgoOverlayBasePaddingTop).toBe('40px');
	});

	it('caches both clearance terms so a warm load reserves the exact space', () => {
		// Caching the height alone still left the authored padding unreserved at
		// first paint, so the content kept shifting by it. Both terms together are
		// what let the head script land on the final geometry before first paint.
		const { hero } = buildSite({ heroInlinePadding: '40px' });

		loadStickyHeader();

		const cached = JSON.parse(
			window.localStorage.getItem('dsgoOverlayHeaderHeight')
		);
		const bucket = Object.keys(cached)[0];

		expect(cached[bucket].h).toBe(`${HEADER_HEIGHT}px`);
		expect(cached[bucket].b).toBe('40px');
		expect(hero.dataset.dsgoOverlayBasePaddingTop).toBe('40px');
	});

	it('survives localStorage throwing, as it does in Safari private mode', () => {
		// Storage ACCESS throws there rather than returning null, and this runs
		// inside the header setup — an uncaught error would abandon the rest of it.
		const { hero } = buildSite({ heroInlinePadding: '40px' });

		// jsdom's localStorage is an own-property Storage instance, so spyOn
		// against its prototype finds nothing — replace the accessor instead.
		const real = window.localStorage;
		Object.defineProperty(window, 'localStorage', {
			configurable: true,
			get() {
				throw new Error('SecurityError: storage is disabled');
			},
		});

		try {
			expect(() => loadStickyHeader()).not.toThrow();
			expect(
				hero.style.getPropertyValue('--dsgo-overlay-hero-base-pad')
			).toBe('40px');
		} finally {
			Object.defineProperty(window, 'localStorage', {
				configurable: true,
				value: real,
				writable: true,
			});
		}
	});

	it('leaves the height variable alone when the page has no overlay header', () => {
		buildSite();
		document.body.className = '';

		loadStickyHeader();

		expect(readHeightVar()).toBe('');
	});
});
