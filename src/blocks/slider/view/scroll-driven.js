/**
 * Slider — scroll-driven horizontal mode.
 *
 * Wraps the slider in a pin spacer, makes the slider sticky, and maps vertical
 * scroll progress through that spacer onto the track's horizontal translation.
 * Nothing here shares state with the paged navigation: when this controller is
 * active it owns the track transform outright.
 */

/* global requestAnimationFrame */

export default class ScrollDrivenController {
	/**
	 * @param {Object}      host          The slider instance.
	 * @param {HTMLElement} host.slider   Slider root.
	 * @param {HTMLElement} host.viewport Slider viewport.
	 * @param {HTMLElement} host.track    Slider track.
	 */
	constructor(host) {
		this.host = host;
		this.slider = host.slider;
		this.viewport = host.viewport;
		this.track = host.track;
		this.ticking = false;
		this.maxOffset = 0;
		this.stickyTop = 0;

		this.pinSpacer = document.createElement('div');
		this.pinSpacer.className = 'dsgo-slider-pin-spacer';
		this.slider.parentNode.insertBefore(this.pinSpacer, this.slider);
		this.pinSpacer.appendChild(this.slider);

		// The track position is driven frame by frame from scroll offset, so a
		// CSS transition would lag every update behind the pointer.
		this.track.style.transition = 'none';

		this.updateDimensions();
		this.updateStickyTop();
		this.buildProgressBar();

		this.onScroll = () => {
			if (this.ticking) {
				return;
			}
			this.ticking = true;
			requestAnimationFrame(() => {
				this.updatePosition();
				this.ticking = false;
			});
		};
		window.addEventListener('scroll', this.onScroll, { passive: true });

		this.updatePosition();
	}

	/**
	 * Size the pin spacer so scrolling through it covers the whole track.
	 */
	updateDimensions() {
		const slides = this.host.originalSlides;
		if (!slides.length) {
			return;
		}

		const sliderHeight = this.slider.offsetHeight;
		const viewportWidth = this.viewport.offsetWidth;
		const slideWidth = this.host.cachedSlideWidth || slides[0].offsetWidth;
		const gap =
			this.host.cachedGap ||
			parseFloat(window.getComputedStyle(this.track).gap) ||
			0;
		const trackWidth =
			slides.length * slideWidth + (slides.length - 1) * gap;

		this.maxOffset = Math.max(0, trackWidth - viewportWidth);

		// scrollDrivenSpeed decides how much vertical travel buys a pixel of
		// horizontal travel.
		const scrollDistance =
			this.maxOffset * this.host.config.scrollDrivenSpeed;
		this.pinSpacer.style.height = `${sliderHeight + scrollDistance}px`;
	}

	/** Pin the slider at the vertical centre of the viewport. */
	updateStickyTop() {
		const sliderHeight = this.slider.offsetHeight;
		this.stickyTop = Math.max(0, (window.innerHeight - sliderHeight) / 2);
		this.slider.style.top = `${this.stickyTop}px`;
	}

	/** Build the progress bar shown in place of dots. */
	buildProgressBar() {
		const container = document.createElement('div');
		container.className = 'dsgo-slider__scroll-progress';
		container.setAttribute('aria-hidden', 'true');

		const bar = document.createElement('div');
		bar.className = 'dsgo-slider__scroll-progress-bar';
		container.appendChild(bar);

		this.slider.appendChild(container);
		this.progressBar = bar;
	}

	/** Map current scroll progress onto the track transform. */
	updatePosition() {
		if (!this.pinSpacer) {
			return;
		}
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}

		const sliderHeight = this.slider.offsetHeight;
		const scrollable = this.pinSpacer.offsetHeight - sliderHeight;
		if (scrollable <= 0) {
			return;
		}

		// Progress runs 0 → 1 between the slider pinning at centre and unpinning.
		const spacerTop = this.pinSpacer.getBoundingClientRect().top;
		const scrolled = this.stickyTop - spacerTop;
		const progress = Math.max(0, Math.min(1, scrolled / scrollable));

		this.track.style.transform = `translate3d(${-(progress * this.maxOffset)}px, 0, 0)`;

		if (this.progressBar) {
			this.progressBar.style.width = `${progress * 100}%`;
		}
	}

	/** Recompute everything after a resize. */
	resize() {
		this.updateDimensions();
		this.updateStickyTop();
		this.updatePosition();
	}

	/** Remove the scroll listener and unwrap the pin spacer. */
	destroy() {
		window.removeEventListener('scroll', this.onScroll);
		if (this.pinSpacer?.parentNode) {
			this.pinSpacer.parentNode.insertBefore(this.slider, this.pinSpacer);
			this.pinSpacer.remove();
		}
		this.pinSpacer = null;
	}
}
