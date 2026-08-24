/**
 * Slider — multi-slide, gesture and Loop Carousel behaviour.
 *
 * The cases here all involve state the runtime derives rather than reads:
 * how far the track may travel, how many clones the loop needs, where a drag
 * starts from, and what happens when the slide set changes underneath a live
 * instance (which is what a Loop Carousel's load-more does).
 *
 * @package
 */

/* global Document, KeyboardEvent, TouchEvent, MouseEvent */

const SLIDE_WIDTH = 100;
/** Wide enough that slidesPerView (rather than its tablet/mobile twin) applies. */
const DESKTOP_WIDTH = 1440;

/**
 * jsdom reports a 1024px viewport, which lands on the tablet breakpoint — so
 * a test that means to exercise the desktop slidesPerView has to say so.
 *
 * @param {number} width Viewport width in pixels.
 */
function setViewportWidth(width) {
	Object.defineProperty(window, 'innerWidth', {
		configurable: true,
		writable: true,
		value: width,
	});
}

/**
 * Build a slider fixture whose slides report a real width.
 *
 * jsdom reports offsetWidth as 0, and the runtime caches slide width to
 * position the track — without a stub every offset computes to zero and the
 * drag/offset assertions below cannot tell right from wrong.
 *
 * @param {Object} options                Fixture options.
 * @param {number} options.slideCount     Slides to create (default 3).
 * @param {Object} options.dataAttributes data-* attributes on the root.
 * @return {HTMLElement} The `.dsgo-slider` root, appended to the body.
 */
function createSlider({ slideCount = 3, dataAttributes = {} } = {}) {
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
		track.appendChild(createSlide(`Slide ${i + 1}`));
	}

	viewport.appendChild(track);
	slider.appendChild(viewport);
	document.body.appendChild(slider);
	return slider;
}

/**
 * @param {string} label Slide text content.
 * @return {HTMLElement} A `.dsgo-slide` with a stubbed offsetWidth.
 */
function createSlide(label) {
	const slide = document.createElement('div');
	slide.className = 'dsgo-slide';
	slide.textContent = label;
	Object.defineProperty(slide, 'offsetWidth', {
		configurable: true,
		value: SLIDE_WIDTH,
	});
	return slide;
}

/** @param {HTMLElement} slider Slider root. @return {HTMLElement} Its track. */
const trackOf = (slider) => slider.querySelector('.dsgo-slider__track');

/** @param {HTMLElement} slider Slider root. @return {NodeList} Its dots. */
const dotsOf = (slider) => slider.querySelectorAll('.dsgo-slider__dot');

/** Load the view module in isolation and let its RAF init run. */
function requireAndInit() {
	jest.isolateModules(() => {
		require('../../../src/blocks/slider/view.js');
	});
	document.dispatchEvent(new Event('DOMContentLoaded'));
	jest.advanceTimersByTime(16);
}

/**
 * @param {HTMLElement} slider Slider root.
 * @param {string}      key    Key name.
 */
function pressKey(slider, key) {
	slider.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
	jest.advanceTimersByTime(600);
}

// Listener bookkeeping, so an isolated module's document/window listeners do
// not survive into the next test.
let trackedListeners = [];
const nativeDocAdd = Document.prototype.addEventListener;
const nativeWinAdd = window.addEventListener.bind(window);
const nativeWinRemove = window.removeEventListener.bind(window);

const nativeInnerWidth = window.innerWidth;

beforeEach(() => {
	jest.useFakeTimers();
	jest.resetModules();
	setViewportWidth(nativeInnerWidth);
	trackedListeners = [];
	Document.prototype.addEventListener = function (type, listener, options) {
		trackedListeners.push({ target: this, type, listener, options });
		return nativeDocAdd.call(this, type, listener, options);
	};
	window.addEventListener = function (type, listener, options) {
		trackedListeners.push({ target: window, type, listener, options });
		return nativeWinAdd.call(window, type, listener, options);
	};
});

afterEach(() => {
	trackedListeners.forEach(({ target, type, listener, options }) => {
		if (target === window) {
			nativeWinRemove(type, listener, options);
		} else {
			Document.prototype.removeEventListener.call(
				target,
				type,
				listener,
				options
			);
		}
	});
	trackedListeners = [];
	Document.prototype.addEventListener = nativeDocAdd;
	document.querySelectorAll('.dsgo-slider').forEach((el) => el.remove());
	jest.restoreAllMocks();
	jest.useRealTimers();
});

describe('multi-slide navigation bounds', () => {
	test('stops advancing once the last slide is on screen', () => {
		setViewportWidth(DESKTOP_WIDTH);
		const slider = createSlider({
			slideCount: 5,
			dataAttributes: { slidesPerView: '3', showArrows: 'true' },
		});
		requireAndInit();

		const events = [];
		slider.addEventListener('dsgo-slider-change', (e) =>
			events.push(e.detail)
		);

		// Five slides, three visible: index 2 is the last resting position.
		pressKey(slider, 'End');
		expect(events[events.length - 1].currentIndex).toBe(2);

		pressKey(slider, 'ArrowRight');
		expect(events[events.length - 1].currentIndex).toBe(2);

		expect(slider.querySelector('.dsgo-slider__arrow--next').disabled).toBe(
			true
		);
	});

	test('dots count resting positions, not slides', () => {
		setViewportWidth(DESKTOP_WIDTH);
		const slider = createSlider({
			slideCount: 5,
			dataAttributes: { slidesPerView: '3', showDots: 'true' },
		});
		requireAndInit();

		expect(dotsOf(slider).length).toBe(3);
	});

	test('every visible slide is exposed to assistive tech', () => {
		setViewportWidth(DESKTOP_WIDTH);
		const slider = createSlider({
			slideCount: 5,
			dataAttributes: { slidesPerView: '3' },
		});
		requireAndInit();

		const slides = slider.querySelectorAll('.dsgo-slide');
		[0, 1, 2].forEach((i) => {
			expect(slides[i].getAttribute('aria-hidden')).toBe('false');
			expect(slides[i].hasAttribute('inert')).toBe(false);
		});
		[3, 4].forEach((i) => {
			expect(slides[i].getAttribute('aria-hidden')).toBe('true');
			expect(slides[i].hasAttribute('inert')).toBe(true);
		});
	});

	test('never clones more slides than exist', () => {
		setViewportWidth(DESKTOP_WIDTH);
		const slider = createSlider({
			slideCount: 2,
			dataAttributes: {
				slidesPerView: '3',
				loop: 'true',
				effect: 'slide',
				showDots: 'true',
			},
		});
		requireAndInit();

		// Two real slides plus two clones either side.
		expect(slider.querySelectorAll('.dsgo-slide').length).toBe(6);
		expect(slider.querySelectorAll('.dsgo-slide--clone').length).toBe(4);
		expect(dotsOf(slider).length).toBe(2);
	});
});

describe('gestures', () => {
	test('drag continues from the current position, not from zero', () => {
		const slider = createSlider({
			slideCount: 4,
			dataAttributes: { draggable: 'true' },
		});
		requireAndInit();

		pressKey(slider, 'ArrowRight');
		const track = trackOf(slider);
		expect(track.style.transform).toBe(`translateX(-${SLIDE_WIDTH}px)`);

		track.dispatchEvent(
			new MouseEvent('mousedown', { clientX: 500, bubbles: true })
		);
		document.dispatchEvent(
			new MouseEvent('mousemove', { clientX: 480, bubbles: true })
		);

		// -100 (slide 1) plus the 20px of drag, not the bare -20px that
		// anchoring the gesture at zero used to produce.
		expect(track.style.transform).toBe(
			`translateX(-${SLIDE_WIDTH + 20}px)`
		);

		document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
		jest.advanceTimersByTime(600);
		expect(track.style.transform).toBe(`translateX(-${SLIDE_WIDTH}px)`);
	});

	test('a mostly-vertical swipe does not change slide', () => {
		const slider = createSlider({
			slideCount: 4,
			dataAttributes: { swipeable: 'true' },
		});
		requireAndInit();

		let changes = 0;
		slider.addEventListener('dsgo-slider-change', () => {
			changes++;
		});

		const track = trackOf(slider);
		track.dispatchEvent(
			new TouchEvent('touchstart', {
				touches: [{ clientX: 200, clientY: 100 }],
				bubbles: true,
			})
		);
		track.dispatchEvent(
			new TouchEvent('touchend', {
				changedTouches: [{ clientX: 100, clientY: 400 }],
				bubbles: true,
			})
		);
		jest.advanceTimersByTime(600);

		expect(changes).toBe(0);
	});
});

describe('autoplay', () => {
	test('keeps running past the first tick with pauseOnInteraction on', () => {
		const observers = [];
		const NativeObserver = window.IntersectionObserver;
		window.IntersectionObserver = class extends NativeObserver {
			constructor(callback, options) {
				super(callback, options);
				observers.push(this);
			}
		};

		const slider = createSlider({
			slideCount: 4,
			dataAttributes: {
				autoplay: 'true',
				autoplayInterval: '1000',
				pauseOnInteraction: 'true',
			},
		});
		requireAndInit();

		const events = [];
		slider.addEventListener('dsgo-slider-change', (e) =>
			events.push(e.detail)
		);

		observers[0].simulateIntersection([{ isIntersecting: true }]);

		jest.advanceTimersByTime(1000);
		jest.advanceTimersByTime(1000);

		// Two ticks: pauseOnInteraction must not treat autoplay's own advance
		// as an interaction, which used to stop the slider after one slide.
		expect(events[events.length - 1].currentIndex).toBe(2);

		window.IntersectionObserver = NativeObserver;
	});
});

describe('Loop Carousel re-sync', () => {
	test('rebuilds around slides appended by the query', () => {
		const slider = createSlider({
			slideCount: 3,
			dataAttributes: { showDots: 'true' },
		});
		requireAndInit();

		expect(dotsOf(slider).length).toBe(3);

		const track = trackOf(slider);
		track.appendChild(createSlide('Slide 4'));
		track.appendChild(createSlide('Slide 5'));
		track.dispatchEvent(
			new CustomEvent('dsgo-query-items-appended', {
				bubbles: true,
				detail: { queryId: 'abc', added: 2 },
			})
		);
		jest.advanceTimersByTime(16);

		expect(dotsOf(slider).length).toBe(5);
	});

	test('advances onto the slides a Load more just added', () => {
		const slider = createSlider({ slideCount: 3 });
		requireAndInit();

		const events = [];
		slider.addEventListener('dsgo-slider-change', (e) =>
			events.push(e.detail)
		);

		const track = trackOf(slider);
		track.appendChild(createSlide('Slide 4'));
		track.appendChild(createSlide('Slide 5'));
		track.dispatchEvent(
			new window.CustomEvent('dsgo-query-items-appended', {
				bubbles: true,
				detail: { queryId: 'abc', added: 2 },
			})
		);
		jest.advanceTimersByTime(600);

		// Slide 4 is index 3. Query view.js hands focus to the first new item
		// right after this event, and an off-screen slide is inert — which
		// refuses focus — so the carousel has to come to it.
		expect(events[events.length - 1].currentIndex).toBe(3);

		const slides = slider.querySelectorAll('.dsgo-slide');
		expect(slides[3].hasAttribute('inert')).toBe(false);
	});

	test('a slider with no slides is still safe to tear down', () => {
		const slider = createSlider({ slideCount: 0 });
		requireAndInit();

		slider.remove();

		expect(() =>
			document.dispatchEvent(new Event('dsgo-content-loaded'))
		).not.toThrow();
	});

	test('tears down instances whose element left the document', () => {
		const slider = createSlider({
			slideCount: 3,
			dataAttributes: { draggable: 'true' },
		});
		requireAndInit();

		const removeSpy = jest.spyOn(document, 'removeEventListener');
		slider.remove();

		// A filter refresh replaces the region's markup and then announces it.
		document.dispatchEvent(new Event('dsgo-content-loaded'));

		expect(
			removeSpy.mock.calls.some(([type]) => type === 'mousemove')
		).toBe(true);
	});
});
