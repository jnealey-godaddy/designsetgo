/**
 * Slider — frontend runtime.
 *
 * Owns slide navigation and the state that follows from it: which slide is
 * current, where the track sits, what assistive tech is told, and which nav
 * chrome is enabled. Configuration parsing, chrome construction, gestures,
 * autoplay and scroll-driven mode live in ./view/.
 *
 * The same runtime drives an authored slider and a Loop Carousel — a slider
 * acting as the item host inside designsetgo/query. In that mode the track
 * doubles as the query's item container, so its children can change after
 * init (load-more appends slides); `refresh()` is how the slider is told.
 */

/* global requestAnimationFrame, ResizeObserver */

import { parseSliderConfig, slidesPerViewFor } from './view/config';
import {
	buildArrows,
	buildDots,
	updateDots,
	buildAnnouncer,
} from './view/chrome';
import { initSwipe, initDrag } from './view/gestures';
import AutoplayController from './view/autoplay';
import ScrollDrivenController from './view/scroll-driven';

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';
const RESIZE_DEBOUNCE_MS = 250;

class DSGSlider {
	constructor(element) {
		this.slider = element;
		this.viewport = element.querySelector('.dsgo-slider__viewport');
		this.track = element.querySelector('.dsgo-slider__track');
		this.originalSlides = this.readSlides();
		// Set before the bail-out below: an instance that never initialised is
		// still registered, so destroy() has to be safe to call on it.
		this.teardowns = [];

		if (!this.track || this.originalSlides.length === 0) {
			return;
		}

		this.config = parseSliderConfig(element);
		this.baseTransitionDuration = this.config.transitionDuration;
		this.baseTransitionDurationMs = this.config.transitionDurationMs;

		this.currentIndex = this.config.activeSlide;
		this.currentOffset = 0;
		this.isAnimating = false;
		this.isDestroyed = false;
		this.cloneCount = 0;
		this.realSlideCount = this.originalSlides.length;
		this.cachedSlideWidth = 0;
		this.cachedGap = 0;
		this.slidesPerView = slidesPerViewFor(this.config, window.innerWidth);

		if (this.usesClones()) {
			this.setupInfiniteLoop();
			// activeSlide counts real slides; the prepended clones shift it.
			this.currentIndex += this.cloneCount;
		}
		this.slides = this.readSlides();

		this.init();
	}

	/** @return {HTMLElement[]} Slides currently in the track, clones included. */
	readSlides() {
		return Array.from(this.track?.querySelectorAll('.dsgo-slide') || []);
	}

	/** @return {boolean} Whether this configuration duplicates slides to loop. */
	usesClones() {
		return this.config.loop && this.config.effect === 'slide';
	}

	/** @return {number} Whole slides visible at the current viewport width. */
	perView() {
		return Math.max(1, Math.ceil(this.slidesPerView));
	}

	/**
	 * The first and last index the track may rest on.
	 *
	 * Without this clamp a slider showing three slides at a time keeps
	 * advancing until the last slide is flush left, scrolling two slots of
	 * empty track into view and leaving the next arrow enabled the whole way.
	 *
	 * @return {{min: number, max: number}} Inclusive navigation bounds.
	 */
	navBounds() {
		if (this.cloneCount > 0) {
			return {
				min: this.cloneCount,
				max: this.cloneCount + this.realSlideCount - 1,
			};
		}
		return {
			min: 0,
			max: Math.max(0, this.slides.length - this.perView()),
		};
	}

	/** @return {number} How many dots represent the reachable positions. */
	dotCount() {
		if (this.cloneCount > 0 || this.config.loop) {
			return this.realSlideCount;
		}
		return Math.max(1, this.realSlideCount - this.perView() + 1);
	}

	setupInfiniteLoop() {
		// Never clone more slides than exist: `slidesPerView: 3` over two
		// authored slides used to walk the source index negative and throw on
		// `undefined.cloneNode`, killing the slider outright.
		const slidesToClone = Math.min(
			Math.ceil(this.slidesPerView),
			this.originalSlides.length
		);
		this.cloneCount = slidesToClone;

		const addClone = (source, position) => {
			const clone = source.cloneNode(true);
			clone.classList.add(
				'dsgo-slide--clone',
				`dsgo-slide--clone-${position}`
			);
			clone.setAttribute('aria-hidden', 'true');
			clone.removeAttribute('id');
			// Duplicated anchors would otherwise appear twice in the tab order.
			clone
				.querySelectorAll('[id]')
				.forEach((node) => node.removeAttribute('id'));
			return clone;
		};

		const tail = this.originalSlides.slice(-slidesToClone);
		tail.forEach((slide) => {
			this.track.insertBefore(
				addClone(slide, 'before'),
				this.track.firstChild
			);
		});
		this.originalSlides.slice(0, slidesToClone).forEach((slide) => {
			this.track.appendChild(addClone(slide, 'after'));
		});
	}

	init() {
		if (this.config.showArrows) {
			this.buildArrowChrome();
		}
		if (this.config.showDots) {
			this.buildDotChrome();
		}
		this.announcer = buildAnnouncer(this.slider);

		if (!this.config.scrollDriven) {
			if (this.config.swipeable) {
				this.teardowns.push(
					initSwipe(this.track, {
						onNext: () => this.userNext(),
						onPrev: () => this.userPrev(),
					})
				);
			}
			if (this.config.draggable) {
				this.teardowns.push(
					initDrag(this.track, {
						getOffset: () => this.currentOffset,
						setOffset: (px) => this.setRawOffset(px),
						onNext: () => this.userNext(),
						onPrev: () => this.userPrev(),
						onCancel: () => this.goToSlide(this.currentIndex),
					})
				);
			}
			if (this.config.autoplay) {
				this.autoplay = new AutoplayController(this.slider, {
					interval: this.config.autoplayInterval,
					pauseOnHover: this.config.pauseOnHover,
					advance: () => this.next(),
				});
			}
			this.initKeyboard();
			// A Loop Carousel's track is the query's item container: load-more
			// appends slides to this very element, so the instance survives but
			// its clone count, dots and dimensions do not.
			this.listen(this.slider, 'dsgo-query-items-appended', (event) =>
				this.onItemsAppended(Number(event.detail?.added) || 0)
			);
		}

		requestAnimationFrame(() => {
			this.updateDimensions();

			if (this.config.scrollDriven) {
				this.scrollDriven = new ScrollDrivenController(this);
			} else {
				this.goToSlide(this.currentIndex, false);
			}

			// Zero width means CSS had not applied yet; re-measure shortly.
			if (this.cachedSlideWidth === 0) {
				setTimeout(() => this.remeasure(), 100);
			}
		});

		this.initResponsive();
		this.initReducedMotion();
	}

	/**
	 * Register a listener and remember how to remove it.
	 *
	 * @param {EventTarget} target  Listener target.
	 * @param {string}      type    Event name.
	 * @param {Function}    handler Listener.
	 * @param {Object}      options addEventListener options.
	 */
	listen(target, type, handler, options) {
		target.addEventListener(type, handler, options);
		this.teardowns.push(() =>
			target.removeEventListener(type, handler, options)
		);
	}

	buildArrowChrome() {
		const { container, prev, next } = buildArrows(this.slider, {
			onPrev: () => this.userPrev(),
			onNext: () => this.userNext(),
		});
		this.arrowsContainer = container;
		this.prevArrow = prev;
		this.nextArrow = next;
		this.updateArrows();
	}

	buildDotChrome() {
		const { container, dots } = buildDots(
			this.slider,
			this.dotCount(),
			this.activeDotIndex(),
			(dotIndex) => this.userGoTo(this.slideIndexForDot(dotIndex)),
			this.dotsContainer
		);
		this.dotsContainer = container;
		this.dots = dots;
	}

	/**
	 * @param {number} dotIndex Zero-based dot.
	 * @return {number} The slide index that dot navigates to.
	 */
	slideIndexForDot(dotIndex) {
		return this.cloneCount > 0 ? dotIndex + this.cloneCount : dotIndex;
	}

	/** @return {number} The dot representing the current position. */
	activeDotIndex() {
		if (this.cloneCount > 0) {
			return this.getRealIndex(this.currentIndex);
		}
		return Math.min(this.currentIndex, this.dotCount() - 1);
	}

	updateDimensions() {
		this.slidesPerView = slidesPerViewFor(this.config, window.innerWidth);
		if (this.slides.length === 0) {
			return;
		}
		// Batch the layout reads together so we thrash at most once.
		this.cachedSlideWidth = this.slides[0].offsetWidth;
		this.cachedGap =
			parseFloat(window.getComputedStyle(this.track).gap) || 0;
	}

	/** Re-measure and re-apply the current position without rebuilding. */
	remeasure() {
		if (this.isDestroyed) {
			return;
		}
		this.updateDimensions();
		if (this.scrollDriven) {
			this.scrollDriven.resize();
		} else {
			this.goToSlide(this.currentIndex, false);
		}
	}

	/**
	 * Rebuild everything derived from the slide set, keeping the reader's place.
	 *
	 * Called when the track's children change (a Loop Carousel loading another
	 * page) and when a breakpoint change alters how many slides are on screen,
	 * since both invalidate the clone count and the dot count.
	 */
	refresh() {
		if (this.isDestroyed || this.config.scrollDriven) {
			return;
		}

		const authored = this.readSlides().filter(
			(slide) => !slide.classList.contains('dsgo-slide--clone')
		);
		if (authored.length === 0) {
			return;
		}

		// Capture the reader's position before the clone bookkeeping resets.
		const realIndex = this.getRealIndex(this.currentIndex);

		this.track
			.querySelectorAll('.dsgo-slide--clone')
			.forEach((clone) => clone.remove());
		this.cloneCount = 0;
		this.originalSlides = authored;
		this.realSlideCount = authored.length;
		if (this.usesClones()) {
			this.setupInfiniteLoop();
		}
		this.slides = this.readSlides();

		const bounds = this.navBounds();
		this.currentIndex = Math.max(
			bounds.min,
			Math.min(
				this.cloneCount > 0 ? realIndex + this.cloneCount : realIndex,
				bounds.max
			)
		);

		if (this.config.showDots) {
			this.buildDotChrome();
		}

		this.updateDimensions();
		this.goToSlide(this.currentIndex, false);

		// New slides arrive without the inline transition suppression the
		// existing ones carry.
		if (this.reducedMotionQuery) {
			this.applyReducedMotion(this.reducedMotionQuery.matches);
		}
	}

	/**
	 * Take up the slides a Load more click just added.
	 *
	 * Advancing is what the reader asked for — they pressed a button below a
	 * carousel they had already worked through — and it is also what makes the
	 * new slides reachable: query view.js hands focus to the first new item for
	 * the screen-reader handoff, and an off-screen slide is `inert`, which
	 * refuses focus outright.
	 *
	 * @param {number} added How many slides were appended.
	 */
	onItemsAppended(added) {
		this.refresh();
		if (added <= 0 || this.isDestroyed) {
			return;
		}

		const firstNew = Math.max(0, this.realSlideCount - added);
		const target =
			this.cloneCount > 0 ? firstNew + this.cloneCount : firstNew;
		const { min, max } = this.navBounds();
		this.goToSlide(Math.max(min, Math.min(target, max)));
	}

	goToSlide(index, animate = true) {
		if (this.isAnimating && animate) {
			return;
		}

		const previousIndex = this.currentIndex;

		if (this.cloneCount > 0) {
			// Clones make every index reachable; applySlideTransition jumps
			// back to the matching real slide once the transition finishes.
			this.currentIndex = index;
		} else if (this.config.loop) {
			const count = this.slides.length;
			this.currentIndex = ((index % count) + count) % count;
		} else {
			const { min, max } = this.navBounds();
			this.currentIndex = Math.max(min, Math.min(index, max));
		}

		this.isAnimating = animate;

		if (this.config.effect === 'slide') {
			this.applySlideTransition(animate);
		} else {
			this.applyActiveClass();
		}

		this.updateArrows();
		updateDots(this.dots || [], this.activeDotIndex());
		this.updateARIA();

		const realIndex = this.getRealIndex(this.currentIndex);
		this.slider.dispatchEvent(
			new CustomEvent('dsgo-slider-change', {
				detail: {
					previousIndex: this.getRealIndex(previousIndex),
					currentIndex: realIndex,
				},
			})
		);

		if (animate) {
			setTimeout(() => {
				this.isAnimating = false;
			}, this.config.transitionDurationMs);
		}
	}

	/**
	 * Write a track translation without disturbing the settled position.
	 *
	 * Drag uses this for every intermediate frame; `currentOffset` keeps
	 * pointing at the slide we would snap back to if the drag is cancelled.
	 *
	 * @param {number} px Translation in pixels.
	 */
	setRawOffset(px) {
		if (this.config.effect !== 'slide') {
			return;
		}
		this.track.style.transform = `translateX(${px}px)`;
	}

	applySlideTransition(animate = true) {
		const offset = -(
			this.currentIndex *
			(this.cachedSlideWidth + this.cachedGap)
		);
		this.currentOffset = offset;

		if (!animate) {
			this.track.style.transition = 'none';
			this.track.style.transform = `translateX(${offset}px)`;
			// Force a style flush so the next transition starts from here.
			void this.track.offsetHeight;
			this.track.style.transition = '';
			return;
		}

		this.track.style.transform = `translateX(${offset}px)`;

		if (this.cloneCount === 0) {
			return;
		}

		setTimeout(() => {
			const jumped = this.jumpTargetForClone();
			if (jumped === null || this.isDestroyed) {
				return;
			}
			this.currentIndex = jumped;
			this.currentOffset = -(
				jumped *
				(this.cachedSlideWidth + this.cachedGap)
			);
			this.track.style.transition = 'none';
			this.track.style.transform = `translateX(${this.currentOffset}px)`;
			void this.track.offsetHeight;
			this.track.style.transition = '';
			updateDots(this.dots || [], this.activeDotIndex());
			this.updateARIA();
		}, this.config.transitionDurationMs);
	}

	/**
	 * @return {number|null} The real-slide index to snap to, or null when the
	 *                       track is already resting on a real slide.
	 */
	jumpTargetForClone() {
		const firstReal = this.cloneCount;
		const afterLastReal = this.cloneCount + this.realSlideCount;

		if (this.currentIndex >= afterLastReal) {
			return firstReal + (this.currentIndex - afterLastReal);
		}
		if (this.currentIndex < firstReal) {
			return afterLastReal - (firstReal - this.currentIndex);
		}
		return null;
	}

	applyActiveClass() {
		this.slides.forEach((slide, index) => {
			slide.classList.toggle(
				'dsgo-slide--active',
				index === this.currentIndex
			);
		});
	}

	next() {
		this.goToSlide(this.currentIndex + 1);
	}

	prev() {
		this.goToSlide(this.currentIndex - 1);
	}

	/**
	 * Navigation the reader asked for, as opposed to an autoplay tick.
	 *
	 * `pauseOnInteraction` used to be checked inside goToSlide, where autoplay's
	 * own advance satisfied it — so an autoplaying slider stopped for good after
	 * one tick. Only these entry points count as interaction.
	 *
	 * @param {number} index Slide index to move to.
	 */
	userGoTo(index) {
		if (this.config.pauseOnInteraction) {
			this.autoplay?.stop();
		}
		this.goToSlide(index);
		this.announce();
	}

	userNext() {
		this.userGoTo(this.currentIndex + 1);
	}

	userPrev() {
		this.userGoTo(this.currentIndex - 1);
	}

	/** Tell screen readers where the reader has landed. */
	announce() {
		if (!this.announcer) {
			return;
		}
		const total =
			this.cloneCount > 0 ? this.realSlideCount : this.slides.length;
		this.announcer.textContent = `Slide ${
			this.getRealIndex(this.currentIndex) + 1
		} of ${total}`;
	}

	updateArrows() {
		if (!this.prevArrow || !this.nextArrow) {
			return;
		}
		if (this.config.loop) {
			this.prevArrow.disabled = false;
			this.nextArrow.disabled = false;
			return;
		}
		const { min, max } = this.navBounds();
		this.prevArrow.disabled = this.currentIndex <= min;
		this.nextArrow.disabled = this.currentIndex >= max;
	}

	getRealIndex(index) {
		if (this.cloneCount === 0) {
			return index;
		}
		const adjusted = index - this.cloneCount;
		if (adjusted < 0) {
			return this.realSlideCount + adjusted;
		}
		if (adjusted >= this.realSlideCount) {
			return adjusted - this.realSlideCount;
		}
		return adjusted;
	}

	updateARIA() {
		// Scroll-driven mode shows the whole strip at once, so nothing in it is
		// hidden from assistive tech.
		if (this.config.scrollDriven) {
			return;
		}

		const first = this.currentIndex;
		const last = this.currentIndex + this.perView() - 1;

		this.slides.forEach((slide, index) => {
			// Clones duplicate real slides, so they stay hidden from assistive
			// tech for their whole life — the original is always in the DOM too.
			if (slide.classList.contains('dsgo-slide--clone')) {
				return;
			}
			const isVisible = index >= first && index <= last;
			slide.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
			// tabindex on the slide would not keep Tab out of the links inside
			// it, which is how focus used to land in an aria-hidden subtree.
			slide.toggleAttribute('inert', !isVisible);
		});
	}

	initKeyboard() {
		this.listen(this.slider, 'keydown', (event) => {
			const target = event.target;
			if (target !== this.slider && !this.slider.contains(target)) {
				return;
			}
			// Arrow keys belong to the field the reader is typing in.
			if (
				target?.closest?.(
					'input, textarea, select, [contenteditable="true"]'
				)
			) {
				return;
			}

			const bounds = this.navBounds();
			switch (event.key) {
				case 'ArrowLeft':
					event.preventDefault();
					this.userPrev();
					break;
				case 'ArrowRight':
					event.preventDefault();
					this.userNext();
					break;
				case 'Home':
					event.preventDefault();
					this.userGoTo(bounds.min);
					break;
				case 'End':
					event.preventDefault();
					this.userGoTo(bounds.max);
					break;
				default:
					break;
			}
		});

		if (!this.slider.hasAttribute('tabindex')) {
			this.slider.setAttribute('tabindex', '0');
		}
	}

	initResponsive() {
		let timer;
		const onResize = () => {
			clearTimeout(timer);
			timer = setTimeout(() => {
				if (this.isDestroyed) {
					return;
				}
				const before = this.perView();
				this.updateDimensions();
				if (this.scrollDriven) {
					this.scrollDriven.resize();
				} else if (this.perView() !== before) {
					// A breakpoint change alters the clone count and how many
					// dots there are, neither of which re-measuring fixes.
					this.refresh();
				} else {
					this.goToSlide(this.currentIndex, false);
				}
			}, RESIZE_DEBOUNCE_MS);
		};

		this.listen(window, 'resize', onResize);
		this.teardowns.push(() => clearTimeout(timer));

		// A slider inside a grid or a collapsing sidebar changes width without
		// the window ever resizing, and the cached slide width goes stale.
		if (typeof ResizeObserver !== 'undefined' && this.viewport) {
			this.resizeObserver = new ResizeObserver(onResize);
			this.resizeObserver.observe(this.viewport);
		}
	}

	initReducedMotion() {
		const query = window.matchMedia(REDUCED_MOTION_QUERY);
		this.reducedMotionQuery = query;
		this.applyReducedMotion(query.matches);

		const onChange = (event) => this.applyReducedMotion(event.matches);
		// Safari below 14 only has the deprecated listener API.
		if (typeof query.addEventListener === 'function') {
			query.addEventListener('change', onChange);
			this.teardowns.push(() =>
				query.removeEventListener('change', onChange)
			);
		} else if (typeof query.addListener === 'function') {
			query.addListener(onChange);
			this.teardowns.push(() => query.removeListener(onChange));
		}
	}

	/**
	 * @param {boolean} prefersReduced Whether motion should be suppressed.
	 */
	applyReducedMotion(prefersReduced) {
		this.config.transitionDuration = prefersReduced
			? '0s'
			: this.baseTransitionDuration;
		this.config.transitionDurationMs = prefersReduced
			? 0
			: this.baseTransitionDurationMs;

		const transition = prefersReduced ? 'none' : '';
		this.track.style.transition = transition;
		this.slides.forEach((slide) => {
			slide.style.transition = transition;
		});

		// Suspending rather than stopping means turning the OS setting back off
		// resumes autoplay instead of leaving the slider permanently frozen.
		this.autoplay?.setSuspended('reduced-motion', prefersReduced);
	}

	destroy() {
		this.isDestroyed = true;
		this.autoplay?.destroy();
		this.scrollDriven?.destroy();
		this.resizeObserver?.disconnect();
		this.teardowns.forEach((teardown) => teardown());
		this.teardowns = [];
	}
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

/** Instance per slider element, so re-init passes skip live sliders. */
const sliderInstances = new WeakMap();
/** Strong refs, so detached instances can be found and torn down. */
const liveInstances = new Set();
/** Pending image-wait timeouts, cleared when the slider initialises. */
const sliderTimeouts = new WeakMap();

/**
 * Tear down instances whose element has left the document.
 *
 * A Dynamic Query filter refresh replaces the whole region's innerHTML, so a
 * carousel host is swapped for a fresh element. Without this the old
 * instance's document- and window-level listeners live on for the rest of the
 * page's life, one leaked set per filter change.
 */
function pruneDetachedSliders() {
	liveInstances.forEach((instance) => {
		if (!instance.slider.isConnected) {
			instance.destroy();
			liveInstances.delete(instance);
		}
	});
}

/**
 * @param {HTMLElement} slider Slider root.
 * @return {{images: HTMLImageElement[], allLoaded: boolean}} Image load state.
 */
function getImageLoadState(slider) {
	const images = Array.from(slider.querySelectorAll('img'));
	const allLoaded =
		images.length === 0 ||
		images.every((img) => img.complete && img.naturalHeight !== 0);
	return { images, allLoaded };
}

/**
 * @param {HTMLElement} slider Slider root.
 */
function initializeSlider(slider) {
	if (sliderInstances.has(slider)) {
		return;
	}

	const timeoutId = sliderTimeouts.get(slider);
	if (timeoutId) {
		clearTimeout(timeoutId);
		sliderTimeouts.delete(slider);
	}

	const instance = new DSGSlider(slider);
	sliderInstances.set(slider, instance);
	liveInstances.add(instance);
}

/**
 * Initialise every slider on the page, waiting on images where it matters.
 *
 * Slide width is read from layout, so initialising before images have sized
 * their slides caches a width of zero and the track never moves.
 */
function initializeSliders() {
	pruneDetachedSliders();

	document.querySelectorAll('.dsgo-slider').forEach((slider) => {
		const { images, allLoaded } = getImageLoadState(slider);

		if (allLoaded) {
			initializeSlider(slider);
			return;
		}

		let loadedCount = 0;
		const checkAndInitialize = () => {
			if (loadedCount === images.length) {
				initializeSlider(slider);
			}
		};
		const onImageLoad = () => {
			loadedCount++;
			checkAndInitialize();
		};

		images.forEach((img) => {
			if (img.complete) {
				loadedCount++;
				return;
			}
			// A broken image must not block initialisation, so error counts too.
			img.addEventListener('load', onImageLoad, { once: true });
			img.addEventListener('error', onImageLoad, { once: true });

			// The image may have finished between the check above and the
			// listeners landing; drop them so it is not counted twice.
			if (img.complete) {
				img.removeEventListener('load', onImageLoad);
				img.removeEventListener('error', onImageLoad);
				loadedCount++;
			}
		});

		checkAndInitialize();

		sliderTimeouts.set(
			slider,
			setTimeout(() => {
				if (!sliderInstances.has(slider)) {
					initializeSlider(slider);
				}
			}, 3000)
		);
	});
}

document.addEventListener('DOMContentLoaded', initializeSliders);

// Soft navigation: bfcache restore, and Dynamic Query filter refreshes that
// replace a carousel host's markup wholesale.
document.addEventListener('dsgo-content-loaded', initializeSliders);

// Backstop for sliders whose images were still settling at DOMContentLoaded.
window.addEventListener('load', initializeSliders);

// `pagehide` rather than `beforeunload`: registering a beforeunload listener
// makes the page ineligible for the back/forward cache, which is exactly the
// navigation our own `pageshow` re-init path exists to serve. A page entering
// the bfcache keeps its instances so the restore finds them live.
window.addEventListener('pagehide', (event) => {
	if (event.persisted) {
		return;
	}
	liveInstances.forEach((instance) => instance.destroy());
	liveInstances.clear();
});
