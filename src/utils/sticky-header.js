/**
 * Sticky Header Enhancements
 *
 * Provides dynamic sticky header functionality:
 * - Shadow on scroll
 * - Shrink on scroll
 * - Hide on scroll down/show on scroll up
 * - Background color on scroll
 * - Mobile responsive behavior
 *
 * @package
 * @since 1.0.0
 */

// Import SCSS so webpack extracts build/utils/sticky-header.css
import './sticky-header.scss';

(function () {
	'use strict';

	// Get settings from localized script data
	const settings = window.dsgStickyHeaderSettings || {
		enable: true,
		customSelector: '',
		zIndex: 100,
		shadowOnScroll: true,
		shadowSize: 'medium',
		shrinkOnScroll: true,
		shrinkAmount: 50,
		mobileEnabled: true,
		mobileBreakpoint: 768,
		transitionSpeed: 300,
		scrollThreshold: 50,
		hideOnScrollDown: false,
		backgroundOnScroll: false,
		backgroundScrollColor: '',
		backgroundScrollOpacity: 100,
		textScrollColor: '',
	};

	// Early exit if sticky header is disabled
	if (!settings.enable) {
		return;
	}

	// Default selector — used when the admin-provided customSelector is empty
	// or invalid (a typo would otherwise throw DOMException and kill all JS).
	const defaultSelector =
		'body:not(.block-editor-page) .wp-block-template-part:first-of-type, body:not(.block-editor-page) header.wp-block-template-part, body:not(.block-editor-page) .wp-block-template-part:has(.wp-block-navigation), body:not(.block-editor-page) .wp-block-template-part:has(.is-position-sticky)';

	const selector = settings.customSelector || defaultSelector;

	/**
	 * Run document.querySelectorAll without letting an invalid selector throw.
	 * sanitize_text_field on the server doesn't validate CSS selector syntax,
	 * so a stray bracket from an admin typo would otherwise break the page.
	 *
	 * @param {string} sel CSS selector
	 * @return {NodeList} Matching elements (empty when invalid)
	 */
	function safeQueryAll(sel) {
		try {
			return document.querySelectorAll(sel);
		} catch (e) {
			if (sel !== defaultSelector) {
				try {
					return document.querySelectorAll(defaultSelector);
				} catch (_) {
					// fall through to empty NodeList
				}
			}
			return document.querySelectorAll(':not(*)');
		}
	}

	// State tracking
	let lastScrollY = window.scrollY;
	let ticking = false;

	// The element whose height the overlay pull-up in `_sticky-header.scss`
	// consumes — the template part that is a direct child of `.wp-site-blocks`.
	// The sticky selector above is deliberately broader (it matches any template
	// part containing a navigation), so on most themes it also matches the FOOTER.
	// Without this narrower check every matched part would write the shared
	// `--dsgo-overlay-header-height` custom property and the last one in DOM order
	// — the footer — would win, pulling page content up by the footer's height
	// instead of the header's.
	const overlayTargetSelector =
		'.wp-site-blocks > header.wp-block-template-part, .wp-site-blocks > .wp-block-template-part:not(footer):first-of-type';

	/**
	 * Resolve the single element the overlay header height should be measured from.
	 *
	 * @return {HTMLElement|null} Overlay header element, or null when absent.
	 */
	function getOverlayTarget() {
		return document.querySelector(overlayTargetSelector);
	}

	/**
	 * Pad the first content section so it clears the overlay header.
	 *
	 * The overlay pull-up in `_sticky-header.scss` slides the content block up
	 * by the header height so the first section's *background* runs behind the
	 * header — that is the point of the overlay. This padding is the exact
	 * counterpart: it applies to the same element the negative margin does, so
	 * the section's *content* lands back where it started instead of being
	 * occluded. That invariant is why the clearance is applied unconditionally
	 * rather than sniffing for a "hero"-shaped block; whatever comes first has
	 * already been pulled under the header and needs the same amount back.
	 *
	 * Authors who do want the content under the header (a full-bleed section
	 * that deliberately runs behind a transparent nav, say) opt out by setting
	 * `--dsgo-overlay-hero-clearance: 0px` on the block.
	 *
	 * The header height is added on top of whatever padding the pattern already
	 * set, so authored spacing survives. Both terms of the calc() stay live —
	 * the clearance reads the custom property JS keeps updated, so a resize
	 * needs no re-run. The authored term keeps its original CSS string, so when
	 * the block serializes its padding inline (how WordPress writes block
	 * spacing) a fluid preset stays fluid. Padding inherited from a stylesheet
	 * instead has no inline string to preserve, so it falls back to a resolved
	 * pixel snapshot taken at init.
	 *
	 * @param {HTMLElement} header Overlay header element
	 */
	function applyOverlayHeroPadding(header) {
		// `header.nextElementSibling` is exactly what the CSS pull-up targets
		// (`.wp-site-blocks > header.wp-block-template-part + *`), so the two stay
		// anchored to the same element by construction. The clearance then goes one
		// level in, onto that element's first child, and that step is deliberate:
		// padding the pulled-up element itself would push its whole box back down
		// and cancel the overlay, whereas padding the first block inside it moves
		// only the content while the block's background stays under the header.
		//
		// That assumes the standard block-theme shape — content wrapper, then the
		// first block. A theme with an extra wrapper level lands the clearance on
		// that wrapper instead; the overlay degrades to "background no longer runs
		// under the header" rather than misplacing content, so it is left
		// unguarded rather than heuristically sniffing for the painted block.
		const hero = header.nextElementSibling?.firstElementChild;
		if (!hero) {
			return;
		}

		// Cache the authored padding before overwriting it — otherwise a second
		// run would nest our calc() inside itself and compound the clearance.
		if (typeof hero.dataset.dsgoOverlayBasePaddingTop !== 'string') {
			hero.dataset.dsgoOverlayBasePaddingTop =
				hero.style.paddingTop ||
				window.getComputedStyle(hero).paddingTop ||
				'0px';
		}

		hero.style.paddingTop = `calc(${hero.dataset.dsgoOverlayBasePaddingTop} + var(--dsgo-overlay-hero-clearance, var(--dsgo-overlay-header-height, 0px)))`;
	}

	/**
	 * Set up overlay header height measurement for a header element.
	 * Measures the header so CSS can pull content up by the right amount.
	 *
	 * @param {HTMLElement} header Header element
	 */
	function setupOverlayHeaderHeight(header) {
		if (!document.body.classList.contains('dsgo-page-overlay-header')) {
			return;
		}

		if (header !== getOverlayTarget()) {
			return;
		}

		const isOverlaySkipTopBar = document.body.classList.contains(
			'dsgo-page-overlay-skip-top-bar'
		);

		const setHeaderHeight = () => {
			let h = header.getBoundingClientRect().height;

			if (isOverlaySkipTopBar) {
				// Find the top bar (first child of container) and subtract its height
				// so only the nav row height is used for the content pull-up.
				let container = header;
				if (
					header.children.length === 1 &&
					header.children[0].children.length >= 2
				) {
					container = header.children[0];
				}
				if (container.children.length >= 2) {
					const topBarHeight =
						container.children[0].getBoundingClientRect().height;
					h = Math.max(0, h - topBarHeight);
				}
			}

			document.documentElement.style.setProperty(
				'--dsgo-overlay-header-height',
				`${h}px`
			);
		};
		setHeaderHeight();
		applyOverlayHeroPadding(header);
		window.addEventListener('resize', setHeaderHeight);
		window.addEventListener('load', setHeaderHeight, { once: true });
	}

	/**
	 * Check if we're on mobile
	 */
	function isMobile() {
		return window.innerWidth < settings.mobileBreakpoint;
	}

	/**
	 * Apply top bar offset for sticky headers that have a top bar
	 * Measures the first child element and sets a negative top value so the
	 * top bar scrolls away before the nav row snaps to the top.
	 * Only applies when the header has 2+ direct children.
	 *
	 * @param {HTMLElement} header Header element
	 */
	function applyTopBarOffset(header) {
		if (!header.classList.contains('dsgo-sticky-skip-top-bar')) {
			return;
		}

		// Overlay headers use position:fixed — skip-top-bar offset would conflict
		if (document.body.classList.contains('dsgo-page-overlay-header')) {
			return;
		}

		// Template parts often have a single outer wrapper group; look through it
		let container = header;
		if (
			header.children.length === 1 &&
			header.children[0].children.length >= 2
		) {
			container = header.children[0];
		}

		if (container.children.length < 2) {
			return;
		}

		const adminBar = document.getElementById('wpadminbar');
		const adminBarHeight = adminBar
			? adminBar.getBoundingClientRect().height
			: 0;
		const topBarHeight =
			container.children[0].getBoundingClientRect().height;
		header.style.top = `${adminBarHeight - topBarHeight}px`;
	}

	/**
	 * Measure the natural rendered size of each logo image inside the header
	 * and store it as CSS custom properties on the element. The CSS rule for
	 * .dsgo-sticky-shrink-logo reads these when applying shrunk max-width /
	 * max-height on scroll, preserving aspect ratio for wide or tall logos.
	 *
	 * @param {HTMLElement} header Header element
	 */
	function measureLogos(header) {
		if (!header.classList.contains('dsgo-sticky-shrink-logo')) {
			return;
		}

		const logos = header.querySelectorAll(
			'.wp-block-image img, .wp-block-site-logo img'
		);

		logos.forEach((img) => {
			const store = () => {
				const w = img.offsetWidth;
				const h = img.offsetHeight;
				if (w > 0 && h > 0) {
					img.style.setProperty(
						'--dsgo-sticky-logo-original-width',
						`${w}px`
					);
					img.style.setProperty(
						'--dsgo-sticky-logo-original-height',
						`${h}px`
					);
				}
			};

			if (img.complete && img.naturalWidth > 0) {
				store();
			} else if (!img.dataset.dsgoLogoLoadBound) {
				// Only attach the load listener once per image — measureLogos
				// may run several times (init, window.load, resize) before the
				// image actually finishes loading.
				img.dataset.dsgoLogoLoadBound = 'true';
				img.addEventListener('load', store, { once: true });
			}
		});
	}

	/**
	 * Apply CSS custom properties
	 *
	 * @param {HTMLElement} header Header element
	 */
	function applyCustomProperties(header) {
		header.style.setProperty(
			'--dsgo-sticky-header-z-index',
			settings.zIndex
		);
		header.style.setProperty(
			'--dsgo-sticky-header-transition-speed',
			`${settings.transitionSpeed}ms`
		);

		// Logo-shrink: per-block amount from FSE controls, fall back to global.
		// Amount is a % reduction — 40% means the logo scales to 60% of original.
		const hasLogoShrink = header.classList.contains(
			'dsgo-sticky-shrink-logo'
		);
		const blockShrinkAmount =
			header.dataset.dsgoShrinkAmount ??
			header.dataset.dsgShrinkAmount ??
			(hasLogoShrink || settings.shrinkOnScroll
				? (settings.shrinkAmount ?? 50)
				: null);

		if (blockShrinkAmount) {
			const shrinkDecimal = parseInt(blockShrinkAmount, 10) / 100;
			const scaleAmount = Math.max(0.1, 1 - shrinkDecimal);
			header.style.setProperty('--dsgo-sticky-logo-scale', scaleAmount);
		}

		// Apply background and text color CSS vars when global setting is enabled
		// OR when the block has FSE-level bg-on-scroll class (per-template-part override)
		const needsBgVars =
			settings.backgroundOnScroll ||
			header.classList.contains('dsgo-sticky-bg-on-scroll');

		if (needsBgVars) {
			if (settings.backgroundScrollColor) {
				const opacity = settings.backgroundScrollOpacity / 100;
				// Convert hex to rgba if needed
				let bgColor = settings.backgroundScrollColor;
				if (bgColor.startsWith('#')) {
					const r = parseInt(bgColor.slice(1, 3), 16);
					const g = parseInt(bgColor.slice(3, 5), 16);
					const b = parseInt(bgColor.slice(5, 7), 16);
					bgColor = `rgba(${r}, ${g}, ${b}, ${opacity})`;
				}
				header.style.setProperty(
					'--dsgo-sticky-scroll-bg-color',
					bgColor
				);
			} else {
				// No color set — override CSS fallback so no background applies
				header.style.setProperty(
					'--dsgo-sticky-scroll-bg-color',
					'transparent'
				);
			}

			if (settings.textScrollColor) {
				header.style.setProperty(
					'--dsgo-sticky-scroll-text-color',
					settings.textScrollColor
				);
			} else {
				header.style.setProperty(
					'--dsgo-sticky-scroll-text-color',
					'inherit'
				);
			}
		}
	}

	/**
	 * Apply configuration classes to header
	 * Respects FSE-configured classes (takes precedence over global settings)
	 *
	 * @param {HTMLElement} header Header element
	 */
	function applyConfigurationClasses(header) {
		// Check if this header has FSE controls enabled
		const hasFSEControls = header.classList.contains(
			'dsgo-sticky-header-enabled'
		);

		// If FSE controls are active, classes are already applied in block save
		// Only add global settings if no FSE controls present
		if (!hasFSEControls) {
			// Shadow on scroll
			if (settings.shadowOnScroll) {
				header.classList.add(
					`dsgo-sticky-shadow-${settings.shadowSize}`
				);
			}

			// Shrink logo on scroll
			if (settings.shrinkOnScroll) {
				header.classList.add('dsgo-sticky-shrink-logo');
			}

			// Hide on scroll down
			if (settings.hideOnScrollDown) {
				header.classList.add('dsgo-sticky-hide-on-scroll-down');
			}

			// Background on scroll
			if (settings.backgroundOnScroll) {
				header.classList.add('dsgo-sticky-bg-on-scroll');
			}

			// Skip top bar (on by default, respects settings.skipTopBar)
			if (settings.skipTopBar !== false) {
				header.classList.add('dsgo-sticky-skip-top-bar');
			}

			// Mobile disabled
			if (!settings.mobileEnabled) {
				header.classList.add('dsgo-sticky-mobile-disabled');
			}
		}
	}

	/**
	 * Handle scroll events
	 *
	 * @param {HTMLElement} header Header element
	 */
	function handleScroll(header) {
		const scrollY = window.scrollY;

		// Check if we should disable on mobile
		if (!settings.mobileEnabled && isMobile()) {
			return;
		}

		// Add/remove scrolled class based on threshold
		if (scrollY > settings.scrollThreshold) {
			header.classList.add('dsgo-scrolled');
		} else {
			header.classList.remove('dsgo-scrolled');
		}

		// Overlay header uses position:fixed via CSS — no position swap needed.
		// The dsgo-scrolled class (toggled above) triggers the background transition.

		// Handle hide on scroll down
		if (settings.hideOnScrollDown && scrollY > settings.scrollThreshold) {
			if (scrollY > lastScrollY) {
				// Scrolling down
				header.classList.add('dsgo-scroll-down');
				header.classList.remove('dsgo-scroll-up');
			} else {
				// Scrolling up
				header.classList.add('dsgo-scroll-up');
				header.classList.remove('dsgo-scroll-down');
			}
		}

		lastScrollY = scrollY;
	}

	/**
	 * Request animation frame wrapper for scroll handling
	 *
	 * @param {HTMLElement} header Header element
	 */
	function onScroll(header) {
		if (!ticking) {
			window.requestAnimationFrame(() => {
				handleScroll(header);
				ticking = false;
			});
			ticking = true;
		}
	}

	/**
	 * Initialize sticky header
	 *
	 * @param {HTMLElement} header Header element
	 */
	function initStickyHeader(header) {
		// Guard against duplicate initialization (e.g. bfcache restore)
		if (header.dataset.dsgoInitialized) {
			return;
		}
		header.dataset.dsgoInitialized = 'true';

		// Set up overlay header height measurement (if applicable)
		setupOverlayHeaderHeight(header);

		// Apply custom properties
		applyCustomProperties(header);

		// Apply configuration classes
		applyConfigurationClasses(header);

		// Back-compat: legacy saved template parts may carry the old class
		// (dsgo-sticky-shrink) from before the feature was renamed to logo-only.
		// Treat it as an alias so existing sites keep working after upgrade.
		if (
			header.classList.contains('dsgo-sticky-shrink') &&
			!header.classList.contains('dsgo-sticky-shrink-logo')
		) {
			header.classList.add('dsgo-sticky-shrink-logo');
		}

		// Measure logos AFTER configuration classes are applied so the class
		// check inside measureLogos passes for global-settings-driven headers.
		measureLogos(header);

		// Apply top bar offset (negative top so top bar scrolls away first)
		applyTopBarOffset(header);

		// Handle initial state
		handleScroll(header);

		// Add scroll listener
		window.addEventListener('scroll', () => onScroll(header), {
			passive: true,
		});

		// Handle resize for mobile breakpoint changes
		let resizeTimeout;
		window.addEventListener('resize', () => {
			clearTimeout(resizeTimeout);
			resizeTimeout = setTimeout(() => {
				handleScroll(header);
				applyTopBarOffset(header);
				// Re-measure logos only when not currently scrolled so we
				// capture the true natural size, not the shrunk size.
				if (!header.classList.contains('dsgo-scrolled')) {
					measureLogos(header);
				}
			}, 150);
		});
	}

	/**
	 * Initialize all sticky headers found in the DOM
	 */
	function initAll() {
		const headers = safeQueryAll(selector);
		headers.forEach(initStickyHeader);
	}

	/**
	 * Initialize all sticky headers
	 */
	function init() {
		// Wait for DOM to be fully loaded
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initAll);
		} else {
			initAll();
		}

		// Re-run top bar offset and logo measurement after all resources load.
		// Late-loading fonts/images can change the top bar height and the
		// logo's rendered size measured at DOMContentLoaded.
		window.addEventListener(
			'load',
			() => {
				const headers = safeQueryAll(selector);
				headers.forEach((header) => {
					applyTopBarOffset(header);
					if (!header.classList.contains('dsgo-scrolled')) {
						measureLogos(header);
					}
				});
			},
			{ once: true }
		);

		// Re-initialize on soft navigation (bfcache restore, AJAX transitions)
		document.addEventListener('dsgo-content-loaded', initAll);
	}

	// Initialize
	init();
})();
