/**
 * Slider Block - Frontend JavaScript Unit Tests
 *
 * Tests for the DSGSlider class defined in src/blocks/slider/view.js.
 * Covers parseConfig, navigation, accessibility, swipe/keyboard handling,
 * reduced motion, image loading, and cleanup.
 *
 * @package
 */

/* global Document, KeyboardEvent, TouchEvent */

/**
 * Build a minimal slider DOM fixture.
 *
 * @param {Object}  options                - Configuration overrides.
 * @param {number}  options.slideCount     - Number of slide children (default 3).
 * @param {Object}  options.dataAttributes - Extra data-* attributes on the root.
 * @param {boolean} options.addImages      - Whether to place <img> elements inside slides.
 * @return {HTMLElement} The root `.dsgo-slider` element (already appended to body).
 */
function createSliderFixture(options = {}) {
	const { slideCount = 3, dataAttributes = {}, addImages = false } = options;

	const slider = document.createElement('div');
	slider.className = 'dsgo-slider';

	Object.entries(dataAttributes).forEach(([key, value]) => {
		slider.dataset[key] = String(value);
	});

	const viewport = document.createElement('div');
	viewport.className = 'dsgo-slider__viewport';

	const track = document.createElement('div');
	track.className = 'dsgo-slider__track';

	for (let i = 0; i < slideCount; i++) {
		const slide = document.createElement('div');
		slide.className = 'dsgo-slide';
		slide.textContent = `Slide ${i + 1}`;
		if (addImages) {
			const img = document.createElement('img');
			img.src = `https://example.com/image-${i}.jpg`;
			slide.appendChild(img);
		}
		track.appendChild(slide);
	}

	viewport.appendChild(track);
	slider.appendChild(viewport);
	document.body.appendChild(slider);

	return slider;
}

/**
 * Remove all slider fixtures from the document.
 */
function cleanupSliders() {
	document.querySelectorAll('.dsgo-slider').forEach((el) => el.remove());
	document
		.querySelectorAll('.dsgo-slider-pin-spacer')
		.forEach((el) => el.remove());
}

// ---------------------------------------------------------------------------
// Listener tracking -- intercept addEventListener on document and window so
// we can tear down everything the isolated module registered between tests.
// ---------------------------------------------------------------------------

/** @type {Array<{target: EventTarget, type: string, listener: Function, options: *}>} */
let trackedListeners = [];

const nativeDocAdd = Document.prototype.addEventListener;
const nativeDocRemove = Document.prototype.removeEventListener;
const nativeWinAdd = window.addEventListener.bind(window);
const nativeWinRemove = window.removeEventListener.bind(window);

/**
 * Patch addEventListener on doc/window to track registrations.
 */
function patchListeners() {
	trackedListeners = [];

	Document.prototype.addEventListener = function (type, listener, options) {
		trackedListeners.push({ target: this, type, listener, options });
		return nativeDocAdd.call(this, type, listener, options);
	};

	const _origWindowAdd = window.addEventListener;
	window.addEventListener = function (type, listener, options) {
		trackedListeners.push({ target: window, type, listener, options });
		return nativeWinAdd.call(window, type, listener, options);
	};
}

/**
 * Remove all listeners that were registered since the last patchListeners()
 * and restore the native methods.
 */
function unpatchListeners() {
	trackedListeners.forEach(({ target, type, listener, options }) => {
		try {
			if (target === window) {
				nativeWinRemove(type, listener, options);
			} else {
				nativeDocRemove.call(target, type, listener, options);
			}
		} catch (_) {
			// Best-effort cleanup
		}
	});
	trackedListeners = [];

	Document.prototype.addEventListener = nativeDocAdd;
}

/**
 * Require the slider view module inside jest.isolateModules, fire
 * DOMContentLoaded, and flush the requestAnimationFrame mock (16 ms)
 * so the slider instance is fully constructed.
 */
function requireAndInit() {
	jest.isolateModules(() => {
		require('../../../src/blocks/slider/view.js');
	});
	document.dispatchEvent(new Event('DOMContentLoaded'));
	jest.advanceTimersByTime(16);
}

// ---------------------------------------------------------------------------
// Test suites
// ---------------------------------------------------------------------------

describe('DSGSlider - Frontend', () => {
	beforeEach(() => {
		jest.useFakeTimers();
		jest.resetModules();
		patchListeners();
	});

	afterEach(() => {
		unpatchListeners();
		cleanupSliders();
		jest.restoreAllMocks();
		jest.useRealTimers();
	});

	// ------------------------------------------------------------------
	// parseConfig
	// ------------------------------------------------------------------
	describe('parseConfig', () => {
		test('reads data attributes correctly', () => {
			const slider = createSliderFixture({
				dataAttributes: {
					effect: 'fade',
					slidesPerView: '3',
					transitionDuration: '1s',
					autoplay: 'true',
					autoplayInterval: '5000',
					loop: 'true',
					draggable: 'true',
					swipeable: 'true',
					showArrows: 'true',
					showDots: 'true',
					mobileBreakpoint: '600',
					tabletBreakpoint: '900',
					scrollDrivenSpeed: '2',
				},
			});

			requireAndInit();

			expect(slider.querySelector('.dsgo-slider__track')).not.toBeNull();
			expect(slider.querySelector('.dsgo-slider__arrows')).not.toBeNull();
			expect(slider.querySelector('.dsgo-slider__dots')).not.toBeNull();
		});

		test('uses defaults when no data attributes present', () => {
			const slider = createSliderFixture();
			requireAndInit();

			expect(slider.querySelector('.dsgo-slider__arrows')).toBeNull();
			expect(slider.querySelector('.dsgo-slider__dots')).toBeNull();
			expect(
				slider.querySelector('.dsgo-slider__announcer')
			).not.toBeNull();
		});

		test('single-slide effects force slidesPerView to 1', () => {
			const slider = createSliderFixture({
				slideCount: 4,
				dataAttributes: {
					effect: 'zoom',
					slidesPerView: '4',
					showDots: 'true',
				},
			});

			requireAndInit();

			const dots = slider.querySelectorAll('.dsgo-slider__dot');
			expect(dots.length).toBe(4);
		});

		test('scrollDriven disables autoplay, loop, arrows, dots', () => {
			const slider = createSliderFixture({
				dataAttributes: {
					scrollDriven: 'true',
					autoplay: 'true',
					loop: 'true',
					showArrows: 'true',
					showDots: 'true',
				},
			});

			requireAndInit();

			expect(slider.querySelector('.dsgo-slider__arrows')).toBeNull();
			expect(slider.querySelector('.dsgo-slider__dots')).toBeNull();
		});
	});

	// ------------------------------------------------------------------
	// getRealIndex
	// ------------------------------------------------------------------
	describe('getRealIndex', () => {
		test('returns index as-is when no clones', () => {
			const slider = createSliderFixture({ slideCount: 3 });
			requireAndInit();

			let eventDetail = null;
			slider.addEventListener('dsgo-slider-change', (e) => {
				eventDetail = e.detail;
			});

			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowRight',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			expect(eventDetail).not.toBeNull();
			expect(eventDetail.currentIndex).toBe(1);
		});

		test('adjusts for clone offset with loop and slide effect', () => {
			const slider = createSliderFixture({
				slideCount: 3,
				dataAttributes: {
					loop: 'true',
					effect: 'slide',
					showDots: 'true',
				},
			});

			requireAndInit();

			// Dots should only cover real slides, not clones
			const dots = slider.querySelectorAll('.dsgo-slider__dot');
			expect(dots.length).toBe(3);
		});

		test('wraps negative adjusted index', () => {
			const slider = createSliderFixture({
				slideCount: 3,
				dataAttributes: {
					loop: 'true',
					effect: 'slide',
				},
			});

			requireAndInit();

			let eventDetail = null;
			slider.addEventListener('dsgo-slider-change', (e) => {
				eventDetail = e.detail;
			});

			// Previous from the first real slide enters the before-clone zone
			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowLeft',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			expect(eventDetail).not.toBeNull();
			expect(eventDetail.currentIndex).toBe(2);
		});

		test('wraps index past real slide count', () => {
			// Use fade + loop so there are no clones and the index
			// arithmetic is straightforward: slides.length === realSlideCount.
			const slider = createSliderFixture({
				slideCount: 3,
				dataAttributes: {
					loop: 'true',
					effect: 'fade',
				},
			});

			requireAndInit();

			// Go to the last slide via End key
			slider.dispatchEvent(
				new KeyboardEvent('keydown', { key: 'End', bubbles: true })
			);
			jest.advanceTimersByTime(600);

			let eventDetail = null;
			slider.addEventListener('dsgo-slider-change', (e) => {
				eventDetail = e.detail;
			});

			// One more ArrowRight wraps from index 2 -> index 0
			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowRight',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			expect(eventDetail).not.toBeNull();
			expect(eventDetail.currentIndex).toBe(0);
		});
	});

	// ------------------------------------------------------------------
	// goToSlide
	// ------------------------------------------------------------------
	describe('goToSlide', () => {
		test('dispatches dsgo-slider-change event with correct detail', () => {
			const slider = createSliderFixture({ slideCount: 4 });
			requireAndInit();

			const events = [];
			slider.addEventListener('dsgo-slider-change', (e) => {
				events.push(e.detail);
			});

			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowRight',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			expect(events.length).toBeGreaterThan(0);
			const lastEvent = events[events.length - 1];
			expect(lastEvent).toHaveProperty('previousIndex');
			expect(lastEvent).toHaveProperty('currentIndex');
			expect(lastEvent.currentIndex).toBe(1);
		});

		test('clamps index without loop', () => {
			const slider = createSliderFixture({ slideCount: 3 });
			requireAndInit();

			const events = [];
			slider.addEventListener('dsgo-slider-change', (e) => {
				events.push(e.detail);
			});

			// Try going before index 0
			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowLeft',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);
			expect(events[events.length - 1].currentIndex).toBe(0);

			// Go to end
			slider.dispatchEvent(
				new KeyboardEvent('keydown', { key: 'End', bubbles: true })
			);
			jest.advanceTimersByTime(600);

			// Try going past the end
			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowRight',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);
			expect(events[events.length - 1].currentIndex).toBe(2);
		});

		test('wraps index with loop for non-slide effects', () => {
			const slider = createSliderFixture({
				slideCount: 3,
				dataAttributes: {
					loop: 'true',
					effect: 'fade',
				},
			});

			requireAndInit();

			const events = [];
			slider.addEventListener('dsgo-slider-change', (e) => {
				events.push(e.detail);
			});

			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowLeft',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			expect(events[events.length - 1].currentIndex).toBe(2);
		});
	});

	// ------------------------------------------------------------------
	// buildArrows
	// ------------------------------------------------------------------
	describe('buildArrows', () => {
		test('creates prev and next buttons with aria-labels', () => {
			const slider = createSliderFixture({
				dataAttributes: { showArrows: 'true' },
			});
			requireAndInit();

			const prevBtn = slider.querySelector('.dsgo-slider__arrow--prev');
			const nextBtn = slider.querySelector('.dsgo-slider__arrow--next');

			expect(prevBtn).not.toBeNull();
			expect(nextBtn).not.toBeNull();
			expect(prevBtn.getAttribute('aria-label')).toBe('Previous slide');
			expect(nextBtn.getAttribute('aria-label')).toBe('Next slide');
			expect(prevBtn.getAttribute('type')).toBe('button');
			expect(nextBtn.getAttribute('type')).toBe('button');
		});
	});

	// ------------------------------------------------------------------
	// buildDots
	// ------------------------------------------------------------------
	describe('buildDots', () => {
		test('creates correct number of dots for real slides', () => {
			const slider = createSliderFixture({
				slideCount: 5,
				dataAttributes: { showDots: 'true' },
			});
			requireAndInit();

			const dots = slider.querySelectorAll('.dsgo-slider__dot');
			expect(dots.length).toBe(5);

			// Dots are destinations, not tabs: there are no tabpanels for
			// them to control, so they are plain buttons in a labelled group
			// with aria-current marking the live one.
			expect(
				slider.querySelector('.dsgo-slider__dots').getAttribute('role')
			).toBe('group');
			dots.forEach((dot, i) => {
				expect(dot.getAttribute('role')).toBeNull();
				expect(dot.getAttribute('aria-label')).toBe(
					`Go to slide ${i + 1}`
				);
			});
			expect(dots[0].getAttribute('aria-current')).toBe('true');
			expect(dots[1].getAttribute('aria-current')).toBeNull();
		});

		test('creates dots only for real slides when loop + slide (clones present)', () => {
			const slider = createSliderFixture({
				slideCount: 4,
				dataAttributes: {
					showDots: 'true',
					loop: 'true',
					effect: 'slide',
				},
			});
			requireAndInit();

			const dots = slider.querySelectorAll('.dsgo-slider__dot');
			expect(dots.length).toBe(4);
		});
	});

	// ------------------------------------------------------------------
	// buildAnnouncementRegion
	// ------------------------------------------------------------------
	describe('buildAnnouncementRegion', () => {
		test('creates aria-live polite region', () => {
			const slider = createSliderFixture();
			requireAndInit();

			const announcer = slider.querySelector('.dsgo-slider__announcer');
			expect(announcer).not.toBeNull();
			expect(announcer.getAttribute('role')).toBe('status');
			expect(announcer.getAttribute('aria-live')).toBe('polite');
			expect(announcer.getAttribute('aria-atomic')).toBe('true');
		});
	});

	// ------------------------------------------------------------------
	// handleSwipe
	// ------------------------------------------------------------------
	describe('handleSwipe', () => {
		test('triggers next on left swipe (diff > 50)', () => {
			const slider = createSliderFixture({
				dataAttributes: { swipeable: 'true' },
			});
			requireAndInit();

			let eventDetail = null;
			slider.addEventListener('dsgo-slider-change', (e) => {
				eventDetail = e.detail;
			});

			const track = slider.querySelector('.dsgo-slider__track');

			track.dispatchEvent(
				new TouchEvent('touchstart', {
					touches: [{ clientX: 200 }],
					bubbles: true,
				})
			);
			track.dispatchEvent(
				new TouchEvent('touchend', {
					changedTouches: [{ clientX: 100 }],
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			expect(eventDetail).not.toBeNull();
			expect(eventDetail.currentIndex).toBe(1);
		});

		test('triggers prev on right swipe (diff < -50)', () => {
			const slider = createSliderFixture({
				slideCount: 3,
				dataAttributes: { swipeable: 'true' },
			});
			requireAndInit();

			// Advance to slide 1
			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowRight',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			let eventDetail = null;
			slider.addEventListener('dsgo-slider-change', (e) => {
				eventDetail = e.detail;
			});

			const track = slider.querySelector('.dsgo-slider__track');

			track.dispatchEvent(
				new TouchEvent('touchstart', {
					touches: [{ clientX: 100 }],
					bubbles: true,
				})
			);
			track.dispatchEvent(
				new TouchEvent('touchend', {
					changedTouches: [{ clientX: 200 }],
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			expect(eventDetail).not.toBeNull();
			expect(eventDetail.currentIndex).toBe(0);
		});

		test('ignores small swipes (abs diff < 50)', () => {
			const slider = createSliderFixture({
				dataAttributes: { swipeable: 'true' },
			});
			requireAndInit();

			// Flush all pending timers (the 100ms fallback goToSlide, etc.)
			// so that they do not interfere with the swipe assertions.
			jest.runAllTimers();

			let swipeEventCount = 0;
			slider.addEventListener('dsgo-slider-change', () => {
				swipeEventCount++;
			});

			const track = slider.querySelector('.dsgo-slider__track');

			track.dispatchEvent(
				new TouchEvent('touchstart', {
					touches: [{ clientX: 200 }],
					bubbles: true,
				})
			);
			track.dispatchEvent(
				new TouchEvent('touchend', {
					changedTouches: [{ clientX: 170 }],
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			expect(swipeEventCount).toBe(0);
		});
	});

	// ------------------------------------------------------------------
	// Keyboard navigation
	// ------------------------------------------------------------------
	describe('keyboard navigation', () => {
		test('ArrowRight advances slide', () => {
			const slider = createSliderFixture({ slideCount: 4 });
			requireAndInit();

			let eventDetail = null;
			slider.addEventListener('dsgo-slider-change', (e) => {
				eventDetail = e.detail;
			});

			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowRight',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			expect(eventDetail.currentIndex).toBe(1);
		});

		test('ArrowLeft goes to previous slide', () => {
			const slider = createSliderFixture({ slideCount: 4 });
			requireAndInit();

			// Advance to slide 2
			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowRight',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);
			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowRight',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			let eventDetail = null;
			slider.addEventListener('dsgo-slider-change', (e) => {
				eventDetail = e.detail;
			});

			slider.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowLeft',
					bubbles: true,
				})
			);
			jest.advanceTimersByTime(600);

			expect(eventDetail.currentIndex).toBe(1);
		});

		test('Home goes to first slide, End goes to last slide', () => {
			const slider = createSliderFixture({ slideCount: 5 });
			requireAndInit();

			const events = [];
			slider.addEventListener('dsgo-slider-change', (e) => {
				events.push(e.detail);
			});

			slider.dispatchEvent(
				new KeyboardEvent('keydown', { key: 'End', bubbles: true })
			);
			jest.advanceTimersByTime(600);
			expect(events[events.length - 1].currentIndex).toBe(4);

			slider.dispatchEvent(
				new KeyboardEvent('keydown', { key: 'Home', bubbles: true })
			);
			jest.advanceTimersByTime(600);
			expect(events[events.length - 1].currentIndex).toBe(0);
		});
	});

	// ------------------------------------------------------------------
	// respectReducedMotion
	// ------------------------------------------------------------------
	describe('respectReducedMotion', () => {
		test('sets transition to 0s when reduced motion is preferred', () => {
			window.matchMedia = jest.fn().mockImplementation((query) => ({
				matches: query === '(prefers-reduced-motion: reduce)',
				media: query,
				onchange: null,
				addListener: jest.fn(),
				removeListener: jest.fn(),
				addEventListener: jest.fn(),
				removeEventListener: jest.fn(),
				dispatchEvent: jest.fn(),
			}));

			const slider = createSliderFixture();
			requireAndInit();

			// respectReducedMotion sets transition: none on each slide.
			// The track's inline transition may be cleared by
			// applySlideTransition during the RAF callback, but individual
			// slides retain their inline style.
			const slides = slider.querySelectorAll('.dsgo-slide');
			slides.forEach((slide) => {
				expect(slide.style.transition).toBe('none');
			});
		});
	});

	// ------------------------------------------------------------------
	// getImageLoadState (tested via observable side-effects)
	// ------------------------------------------------------------------
	describe('getImageLoadState', () => {
		test('returns allLoaded true when no images', () => {
			const slider = createSliderFixture({ addImages: false });
			requireAndInit();

			expect(
				slider.querySelector('.dsgo-slider__announcer')
			).not.toBeNull();
		});

		test('returns allLoaded true when all images are complete', () => {
			const slider = createSliderFixture({ addImages: true });

			slider.querySelectorAll('img').forEach((img) => {
				Object.defineProperty(img, 'complete', {
					value: true,
					writable: true,
				});
				Object.defineProperty(img, 'naturalHeight', {
					value: 100,
					writable: true,
				});
			});

			requireAndInit();

			expect(
				slider.querySelector('.dsgo-slider__announcer')
			).not.toBeNull();
		});
	});

	// ------------------------------------------------------------------
	// Double initialisation prevention
	// ------------------------------------------------------------------
	describe('double initialisation prevention', () => {
		test('prevents double initialisation via WeakMap', () => {
			const slider = createSliderFixture({
				dataAttributes: { showArrows: 'true' },
			});

			requireAndInit();

			expect(slider.querySelectorAll('.dsgo-slider__arrows').length).toBe(
				1
			);

			// Fire load event (backup initialiser) - should NOT double-init
			window.dispatchEvent(new Event('load'));
			jest.advanceTimersByTime(16);

			expect(slider.querySelectorAll('.dsgo-slider__arrows').length).toBe(
				1
			);
		});
	});

	// ------------------------------------------------------------------
	// destroy
	// ------------------------------------------------------------------
	describe('destroy', () => {
		test('removes event listeners and cleans up', () => {
			const _slider = createSliderFixture({
				dataAttributes: {
					draggable: 'true',
					autoplay: 'true',
				},
			});

			requireAndInit();

			const winRemoveSpy = jest.fn();
			const docRemoveSpy = jest.fn();

			// Wrap removeEventListener to spy on calls
			const origWinRemove = window.removeEventListener;
			window.removeEventListener = function (...args) {
				winRemoveSpy(...args);
				return origWinRemove.apply(this, args);
			};

			const origDocRemove = document.removeEventListener;
			document.removeEventListener = function (...args) {
				docRemoveSpy(...args);
				return origDocRemove.apply(this, args);
			};

			// Trigger pagehide to invoke the destroy path. beforeunload would
			// make the page ineligible for the back/forward cache, which is
			// why the listener moved.
			window.dispatchEvent(new Event('pagehide'));

			const resizeCalls = winRemoveSpy.mock.calls.filter(
				([event]) => event === 'resize'
			);
			expect(resizeCalls.length).toBeGreaterThan(0);

			const mouseMoveCalls = docRemoveSpy.mock.calls.filter(
				([event]) => event === 'mousemove'
			);
			const mouseUpCalls = docRemoveSpy.mock.calls.filter(
				([event]) => event === 'mouseup'
			);
			expect(mouseMoveCalls.length).toBeGreaterThan(0);
			expect(mouseUpCalls.length).toBeGreaterThan(0);

			// Restore
			window.removeEventListener = origWinRemove;
			document.removeEventListener = origDocRemove;
		});
	});
});
