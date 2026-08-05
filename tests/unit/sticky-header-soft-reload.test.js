/**
 * Sticky header survival across a soft reload — sticky-header.js scroll wiring
 *
 * Airo's AJAX soft reload swaps the DOM in place. When the page template exposes
 * no content wrapper (`main`, `#content`, `.site`…) the swap replaces the whole
 * `<body>`, so the header template part is destroyed and rebuilt.
 *
 * That used to leave the header permanently dead:
 *
 * 1. Each header got its own `scroll` listener, but they shared one
 *    module-scoped rAF gate. The first listener registered claimed the gate on
 *    every scroll event and only released it after its own callback, starving
 *    every listener registered later.
 * 2. Nothing dropped the listener belonging to a header the swap had detached —
 *    and that dead listener, being the oldest, was exactly the one holding the
 *    gate. The live header never received `dsgo-scrolled`, so the scrolled
 *    background never appeared until a full page reload.
 *
 * The same shared gate silently starved the second match on any page where the
 * selector matched twice, which is every theme whose footer holds a navigation.
 */

const HEADER_HEIGHT = 80;

/**
 * Build a block-theme body: header template part, content, and a footer that
 * also contains a navigation.
 *
 * @return {HTMLElement} The header template part.
 */
function buildSite() {
	document.body.className = 'dsgo-page-overlay-header';
	document.body.innerHTML = `
		<div class="wp-site-blocks">
			<header class="wp-block-template-part">
				<div class="wp-block-group">
					<nav class="wp-block-navigation"></nav>
				</div>
			</header>
			<div class="entry-content">
				<div class="wp-block-cover"></div>
			</div>
			<footer class="wp-block-template-part">
				<div class="wp-block-group">
					<nav class="wp-block-navigation"></nav>
				</div>
			</footer>
		</div>
	`;

	const header = document.querySelector('.wp-site-blocks > header');
	header.getBoundingClientRect = () => ({ height: HEADER_HEIGHT, top: 0 });
	return header;
}

/**
 * Replace the whole `<body>`, the way softReload() does when no content
 * wrapper matches, and fire the event the swap dispatches afterwards.
 *
 * @return {HTMLElement} The rebuilt header template part.
 */
function softReloadFullBody() {
	buildSite();
	document.dispatchEvent(new Event('dsgo-content-loaded'));
	return document.querySelector('.wp-site-blocks > header');
}

/**
 * Scroll and let the rAF-gated handler run.
 *
 * @param {number} y Target scroll position.
 */
function scrollTo(y) {
	window.scrollY = y;
	window.dispatchEvent(new Event('scroll'));
	jest.runOnlyPendingTimers();
}

/**
 * Load sticky-header.js fresh. The module is an IIFE that inits on import, so
 * the DOM has to be in place first.
 */
function loadStickyHeader() {
	jest.isolateModules(() => {
		require('../../src/utils/sticky-header.js');
	});
}

describe('sticky header across a soft reload', () => {
	beforeEach(() => {
		jest.useFakeTimers();
		// Run rAF callbacks off the timer queue so the gate can be stepped.
		window.requestAnimationFrame = (cb) => setTimeout(cb, 0);
		window.scrollY = 0;
		document.documentElement.removeAttribute('style');
		document.body.className = '';
		document.body.innerHTML = '';
		window.dsgStickyHeaderSettings = {
			enable: true,
			mobileEnabled: true,
			mobileBreakpoint: 768,
			scrollThreshold: 50,
		};
	});

	afterEach(() => {
		jest.useRealTimers();
	});

	it('toggles dsgo-scrolled on the live header before any swap', () => {
		const header = buildSite();
		loadStickyHeader();

		scrollTo(400);
		expect(header.classList.contains('dsgo-scrolled')).toBe(true);

		scrollTo(0);
		expect(header.classList.contains('dsgo-scrolled')).toBe(false);
	});

	it('services the rebuilt header after a full-body swap', () => {
		const original = buildSite();
		loadStickyHeader();

		scrollTo(400);
		expect(original.classList.contains('dsgo-scrolled')).toBe(true);
		scrollTo(0);

		const rebuilt = softReloadFullBody();
		expect(rebuilt).not.toBe(original);

		scrollTo(400);
		expect(rebuilt.classList.contains('dsgo-scrolled')).toBe(true);

		scrollTo(0);
		expect(rebuilt.classList.contains('dsgo-scrolled')).toBe(false);
	});

	it('stops servicing a header the swap detached', () => {
		const original = buildSite();
		loadStickyHeader();

		softReloadFullBody();
		scrollTo(400);

		expect(original.isConnected).toBe(false);
		expect(original.classList.contains('dsgo-scrolled')).toBe(false);
	});

	it('keeps working across repeated swaps', () => {
		buildSite();
		loadStickyHeader();

		for (let i = 0; i < 4; i++) {
			const header = softReloadFullBody();
			scrollTo(400);
			expect(header.classList.contains('dsgo-scrolled')).toBe(true);
			scrollTo(0);
			expect(header.classList.contains('dsgo-scrolled')).toBe(false);
		}
	});

	it('leaves the footer template part out of the scrolled state', () => {
		buildSite();
		loadStickyHeader();

		scrollTo(400);

		// The footer holds a navigation, so the pre-fix selector matched it.
		// Handing it dsgo-scrolled paints the header's shadow and shrinks its
		// logo — the stylesheet excludes footers from every sticky rule.
		const footer = document.querySelector('.wp-site-blocks > footer');
		expect(footer.classList.contains('dsgo-scrolled')).toBe(false);
	});
});
