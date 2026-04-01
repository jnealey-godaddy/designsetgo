/**
 * Slider Block - Frontend JavaScript
 * Handles all slider interactions: navigation, auto-play, swipe, drag, keyboard
 */

/* global requestAnimationFrame */

const SINGLE_SLIDE_EFFECTS = ['fade', 'zoom'];

class DSGSlider {
	constructor(element) {
		this.slider = element;
		this.viewport = element.querySelector('.dsgo-slider__viewport');
		this.track = element.querySelector('.dsgo-slider__track');
		this.originalSlides = Array.from(
			element.querySelectorAll('.dsgo-slide')
		);

		if (this.originalSlides.length === 0) {
			return;
		}

		// Parse configuration from data attributes
		this.config = this.parseConfig();

		// State
		this.currentIndex = parseInt(this.config.activeSlide) || 0;
		this.isAnimating = false;
		this.autoplayTimer = null;
		this.isInViewport = false;
		this.touchStartX = 0;
		this.touchEndX = 0;
		this.dragStartX = 0;
		this.isDragging = false;
		this.cloneCount = 0;
		this.realSlideCount = this.originalSlides.length;

		// Performance: Cache slide dimensions (updated on resize only)
		this.cachedSlideWidth = 0;
		this.cachedGap = 0;

		// Setup infinite loop clones if needed
		if (this.config.loop && this.config.effect === 'slide') {
			this.setupInfiniteLoop();
		}

		// Get all slides (including clones)
		this.slides = Array.from(this.track.querySelectorAll('.dsgo-slide'));

		// Initialize
		this.init();
	}

	parseConfig() {
		const effect = this.slider.dataset.effect || 'slide';
		const requiresSingleSlideEffect = SINGLE_SLIDE_EFFECTS.includes(effect);
		const scrollDriven = this.slider.dataset.scrollDriven === 'true';

		return {
			slidesPerView: requiresSingleSlideEffect
				? 1
				: parseFloat(this.slider.dataset.slidesPerView) || 1,
			slidesPerViewTablet: requiresSingleSlideEffect
				? 1
				: parseFloat(this.slider.dataset.slidesPerViewTablet) || 1,
			slidesPerViewMobile: requiresSingleSlideEffect
				? 1
				: parseFloat(this.slider.dataset.slidesPerViewMobile) || 1,
			effect,
			transitionDuration:
				this.slider.dataset.transitionDuration || '0.5s',
			transitionEasing:
				this.slider.dataset.transitionEasing || 'ease-in-out',
			autoplay: scrollDriven
				? false
				: this.slider.dataset.autoplay === 'true',
			autoplayInterval:
				parseInt(this.slider.dataset.autoplayInterval) || 3000,
			pauseOnHover: this.slider.dataset.pauseOnHover === 'true',
			pauseOnInteraction:
				this.slider.dataset.pauseOnInteraction === 'true',
			loop: scrollDriven ? false : this.slider.dataset.loop === 'true',
			draggable: this.slider.dataset.draggable === 'true',
			swipeable: this.slider.dataset.swipeable === 'true',
			freeMode: this.slider.dataset.freeMode === 'true',
			centeredSlides: this.slider.dataset.centeredSlides === 'true',
			showArrows: scrollDriven
				? false
				: this.slider.dataset.showArrows === 'true',
			showDots: scrollDriven
				? false
				: this.slider.dataset.showDots === 'true',
			mobileBreakpoint:
				parseInt(this.slider.dataset.mobileBreakpoint) || 768,
			tabletBreakpoint:
				parseInt(this.slider.dataset.tabletBreakpoint) || 1024,
			activeSlide: parseInt(this.slider.dataset.activeSlide) || 0,
			scrollDriven,
			scrollDrivenSpeed:
				parseFloat(this.slider.dataset.scrollDrivenSpeed) || 1,
		};
	}

	setupInfiniteLoop() {
		// Determine how many slides to clone (based on slides per view)
		const slidesToClone = Math.ceil(this.config.slidesPerView);
		this.cloneCount = slidesToClone;

		// Clone last N slides and prepend to track
		for (
			let i = this.originalSlides.length - slidesToClone;
			i < this.originalSlides.length;
			i++
		) {
			const clone = this.originalSlides[i].cloneNode(true);
			clone.classList.add('dsgo-slide--clone');
			clone.classList.add('dsgo-slide--clone-before');
			clone.setAttribute('aria-hidden', 'true');
			clone.removeAttribute('id'); // Remove any IDs from clones
			this.track.insertBefore(clone, this.track.firstChild);
		}

		// Clone first N slides and append to track
		for (let i = 0; i < slidesToClone; i++) {
			const clone = this.originalSlides[i].cloneNode(true);
			clone.classList.add('dsgo-slide--clone');
			clone.classList.add('dsgo-slide--clone-after');
			clone.setAttribute('aria-hidden', 'true');
			clone.removeAttribute('id'); // Remove any IDs from clones
			this.track.appendChild(clone);
		}

		// Adjust currentIndex to account for prepended clones
		this.currentIndex = this.currentIndex + this.cloneCount;
	}

	init() {
		// Build navigation
		if (this.config.showArrows) {
			this.buildArrows();
		}
		if (this.config.showDots) {
			this.buildDots();
		}

		// Add screen reader announcement region
		this.buildAnnouncementRegion();

		// Initialize non-scroll-driven features before RAF
		// (scroll-driven init must wait for cached dimensions)
		if (!this.config.scrollDriven) {
			if (this.config.swipeable) {
				this.initSwipe();
			}
			if (this.config.draggable) {
				this.initDrag();
			}
			if (this.config.autoplay) {
				this.observeVisibility();
			}

			this.initKeyboard();
		}

		// Performance: Calculate and cache dimensions once
		// Use requestAnimationFrame to ensure layout is complete
		requestAnimationFrame(() => {
			this.updateDimensions();

			if (this.config.scrollDriven) {
				// Scroll-driven mode: init after dimensions are cached
				// Skip goToSlide() — scroll-driven position is set by
				// updateScrollDrivenPosition(), and updateARIA() would
				// incorrectly hide all non-first slides from screen readers
				this.initScrollDriven();
			} else {
				// Set initial slide
				this.goToSlide(this.currentIndex, false);
			}

			// If dimensions are still 0, recalculate after a short delay
			// This handles cases where CSS hasn't fully applied yet
			if (this.cachedSlideWidth === 0) {
				setTimeout(() => {
					this.updateDimensions();
					if (this.config.scrollDriven) {
						this.updateScrollDrivenDimensions();
						this.updateScrollDrivenStickyTop();
						this.updateScrollDrivenPosition();
					} else {
						this.goToSlide(this.currentIndex, false);
					}
				}, 100);
			}
		});

		this.initResponsive();

		// Check reduced motion preference
		this.respectReducedMotion();
	}

	/**
	 * Update cached slide dimensions
	 * Performance optimization: Only called on init and resize
	 */
	updateDimensions() {
		if (this.slides.length === 0) {
			return;
		}

		// Batch read all layout properties at once (prevents layout thrashing)
		this.cachedSlideWidth = this.slides[0].offsetWidth;
		this.cachedGap =
			parseFloat(window.getComputedStyle(this.track).gap) || 0;
	}

	buildArrows() {
		// Remove editor-only arrows if present
		const editorArrows = this.slider.querySelector(
			'.dsgo-slider__arrows--editor-only'
		);
		if (editorArrows) {
			editorArrows.remove();
		}

		const arrowsContainer = document.createElement('div');
		arrowsContainer.className = 'dsgo-slider__arrows';
		arrowsContainer.innerHTML = `
			<button type="button" class="dsgo-slider__arrow dsgo-slider__arrow--prev" aria-label="Previous slide">
				<span>‹</span>
			</button>
			<button type="button" class="dsgo-slider__arrow dsgo-slider__arrow--next" aria-label="Next slide">
				<span>›</span>
			</button>
		`;

		this.slider.appendChild(arrowsContainer);

		this.prevArrow = arrowsContainer.querySelector(
			'.dsgo-slider__arrow--prev'
		);
		this.nextArrow = arrowsContainer.querySelector(
			'.dsgo-slider__arrow--next'
		);

		this.prevArrow.addEventListener('click', () => this.prev());
		this.nextArrow.addEventListener('click', () => this.next());

		this.updateArrows();
	}

	buildDots() {
		// Remove editor-only dots if present
		const editorDots = this.slider.querySelector(
			'.dsgo-slider__dots--editor-only'
		);
		if (editorDots) {
			editorDots.remove();
		}

		const dotsContainer = document.createElement('div');
		dotsContainer.className = 'dsgo-slider__dots';
		dotsContainer.setAttribute('role', 'tablist');
		dotsContainer.setAttribute('aria-label', 'Slide navigation');

		// Only create dots for real slides, not clones
		const dotCount =
			this.cloneCount > 0 ? this.realSlideCount : this.slides.length;

		for (let i = 0; i < dotCount; i++) {
			const dot = document.createElement('button');
			dot.type = 'button';
			dot.className = 'dsgo-slider__dot';
			dot.setAttribute('role', 'tab');
			dot.setAttribute('aria-label', `Go to slide ${i + 1}`);
			const realIndex = this.cloneCount > 0 ? i + this.cloneCount : i;
			dot.setAttribute(
				'aria-selected',
				realIndex === this.currentIndex ? 'true' : 'false'
			);
			dot.addEventListener('click', () => this.goToSlide(realIndex));
			dotsContainer.appendChild(dot);
		}

		this.slider.appendChild(dotsContainer);
		this.dots = Array.from(
			dotsContainer.querySelectorAll('.dsgo-slider__dot')
		);
		this.updateDots();
	}

	buildAnnouncementRegion() {
		// Create visually hidden region for screen reader announcements
		const announcer = document.createElement('div');
		announcer.className = 'dsgo-slider__announcer';
		announcer.setAttribute('role', 'status');
		announcer.setAttribute('aria-live', 'polite');
		announcer.setAttribute('aria-atomic', 'true');
		announcer.style.position = 'absolute';
		announcer.style.left = '-9999px';
		announcer.style.width = '1px';
		announcer.style.height = '1px';
		announcer.style.overflow = 'hidden';

		this.slider.appendChild(announcer);
		this.announcer = announcer;
	}

	goToSlide(index, animate = true) {
		if (this.isAnimating && animate) {
			return;
		}

		const previousIndex = this.currentIndex;

		// Handle loop mode with clones (slide effect)
		if (
			this.config.loop &&
			this.cloneCount > 0 &&
			this.config.effect === 'slide'
		) {
			// Allow moving to clones, they will be handled in applySlideTransition
			this.currentIndex = index;
		}
		// Handle loop mode without clones (other effects)
		else if (this.config.loop) {
			if (index < 0) {
				index = this.slides.length - 1;
			}
			if (index >= this.slides.length) {
				index = 0;
			}
			this.currentIndex = index;
		}
		// No loop - clamp to valid range
		else {
			this.currentIndex = Math.max(
				0,
				Math.min(index, this.slides.length - 1)
			);
		}

		if (!animate) {
			this.isAnimating = false;
		} else {
			this.isAnimating = true;
		}

		// Apply transition based on effect type
		if (this.config.effect === 'slide') {
			this.applySlideTransition(animate);
		} else if (this.config.effect === 'fade') {
			this.applyFadeTransition();
		} else if (this.config.effect === 'zoom') {
			this.applyZoomTransition();
		}

		// Update navigation
		this.updateArrows();
		this.updateDots();
		this.updateARIA();

		// Pause autoplay on interaction
		if (this.config.pauseOnInteraction && this.autoplayTimer) {
			this.stopAutoplay();
		}

		// Dispatch custom event (use real index for event)
		const realIndex = this.getRealIndex(this.currentIndex);
		this.slider.dispatchEvent(
			new CustomEvent('dsgo-slider-change', {
				detail: {
					previousIndex: this.getRealIndex(previousIndex),
					currentIndex: realIndex,
				},
			})
		);

		// Announce slide change to screen readers
		if (this.announcer && animate) {
			const totalSlides =
				this.cloneCount > 0 ? this.realSlideCount : this.slides.length;
			this.announcer.textContent = `Slide ${realIndex + 1} of ${totalSlides}`;
		}

		// Reset animating state
		if (animate) {
			const duration = parseFloat(this.config.transitionDuration) * 1000;
			setTimeout(() => {
				this.isAnimating = false;
			}, duration);
		}
	}

	applySlideTransition(animate = true) {
		// Performance: Use cached dimensions instead of reading layout
		const offset = -(
			this.currentIndex *
			(this.cachedSlideWidth + this.cachedGap)
		);

		// Apply transition
		if (!animate) {
			this.track.style.transition = 'none';
			this.track.style.transform = `translateX(${offset}px)`;
			// Force browser to apply styles immediately
			void this.track.offsetHeight;
			this.track.style.transition = '';
		} else {
			this.track.style.transform = `translateX(${offset}px)`;

			// For infinite loop: check if we're on a clone and need to jump to real slide
			if (this.cloneCount > 0) {
				const duration =
					parseFloat(this.config.transitionDuration) * 1000;
				setTimeout(() => {
					let needsJump = false;
					let newIndex = this.currentIndex;

					// If we're on a clone after the real slides, jump to the corresponding real slide at the start
					if (
						this.currentIndex >=
						this.cloneCount + this.realSlideCount
					) {
						newIndex =
							this.cloneCount +
							(this.currentIndex -
								(this.cloneCount + this.realSlideCount));
						needsJump = true;
					}
					// If we're on a clone before the real slides, jump to the corresponding real slide at the end
					else if (this.currentIndex < this.cloneCount) {
						newIndex =
							this.cloneCount +
							this.realSlideCount -
							(this.cloneCount - this.currentIndex);
						needsJump = true;
					}

					if (needsJump) {
						this.currentIndex = newIndex;
						const jumpOffset = -(
							newIndex *
							(this.cachedSlideWidth + this.cachedGap)
						);
						this.track.style.transition = 'none';
						this.track.style.transform = `translateX(${jumpOffset}px)`;
						// Force browser to apply styles immediately
						void this.track.offsetHeight;
						this.track.style.transition = '';

						// Update navigation after jump
						this.updateDots();
						this.updateARIA();
					}
				}, duration);
			}
		}
	}

	applyFadeTransition() {
		this.slides.forEach((slide, index) => {
			slide.classList.toggle(
				'dsgo-slide--active',
				index === this.currentIndex
			);
		});
	}

	applyZoomTransition() {
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

	updateArrows() {
		if (!this.prevArrow || !this.nextArrow) {
			return;
		}

		if (!this.config.loop) {
			// For non-loop, account for clones in the index
			const minIndex = this.cloneCount;
			const maxIndex =
				this.cloneCount > 0
					? this.cloneCount + this.realSlideCount - 1
					: this.slides.length - 1;
			this.prevArrow.disabled = this.currentIndex === minIndex;
			this.nextArrow.disabled = this.currentIndex === maxIndex;
		} else {
			this.prevArrow.disabled = false;
			this.nextArrow.disabled = false;
		}
	}

	getRealIndex(index) {
		if (this.cloneCount === 0) {
			return index;
		}

		// Convert any index to its real slide equivalent
		const adjustedIndex = index - this.cloneCount;
		if (adjustedIndex < 0) {
			return this.realSlideCount + adjustedIndex;
		} else if (adjustedIndex >= this.realSlideCount) {
			return adjustedIndex - this.realSlideCount;
		}
		return adjustedIndex;
	}

	updateDots() {
		if (!this.dots) {
			return;
		}

		const realIndex = this.getRealIndex(this.currentIndex);

		this.dots.forEach((dot, index) => {
			const isActive = index === realIndex;
			dot.classList.toggle('dsgo-slider__dot--active', isActive);
			dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
		});
	}

	updateARIA() {
		// In scroll-driven mode all slides are visible in the horizontal
		// strip — hiding non-active slides from AT would be incorrect
		if (this.config.scrollDriven) {
			return;
		}

		this.slides.forEach((slide, index) => {
			const isActive = index === this.currentIndex;
			slide.setAttribute('aria-hidden', !isActive ? 'true' : 'false');
			slide.setAttribute('tabindex', isActive ? '0' : '-1');
		});
	}

	// Swipe support (touch devices)
	initSwipe() {
		this.track.addEventListener(
			'touchstart',
			(e) => {
				this.touchStartX = e.touches[0].clientX;
			},
			{ passive: true }
		);

		this.track.addEventListener(
			'touchend',
			(e) => {
				this.touchEndX = e.changedTouches[0].clientX;
				this.handleSwipe();
			},
			{ passive: true }
		);
	}

	handleSwipe() {
		const diff = this.touchStartX - this.touchEndX;
		const threshold = 50;

		if (Math.abs(diff) > threshold) {
			if (diff > 0) {
				this.next();
			} else {
				this.prev();
			}
		}
	}

	// Drag support (mouse)
	initDrag() {
		let startX = 0;
		let currentTranslate = 0;
		let previousTranslate = 0;

		// ✅ Store bound functions for cleanup
		this.handleMouseMove = (e) => {
			if (!this.isDragging) {
				return;
			}

			const currentX = e.clientX;
			const diff = currentX - startX;
			currentTranslate = previousTranslate + diff;

			if (this.config.effect === 'slide') {
				this.track.style.transform = `translateX(${currentTranslate}px)`;
			}
		};

		this.handleMouseUp = () => {
			if (!this.isDragging) {
				return;
			}

			this.isDragging = false;
			this.track.style.cursor = 'grab';

			const diff = currentTranslate - previousTranslate;
			const threshold = 50;

			if (Math.abs(diff) > threshold) {
				if (diff < 0) {
					this.next();
				} else {
					this.prev();
				}
			} else {
				this.goToSlide(this.currentIndex); // Snap back
			}
		};

		this.handleMouseDown = (e) => {
			this.isDragging = true;
			startX = e.clientX;
			this.track.style.cursor = 'grabbing';
			previousTranslate = currentTranslate;
		};

		// Add event listeners
		this.track.addEventListener('mousedown', this.handleMouseDown);
		document.addEventListener('mousemove', this.handleMouseMove);
		document.addEventListener('mouseup', this.handleMouseUp);

		this.track.style.cursor = 'grab';
	}

	// Keyboard navigation
	initKeyboard() {
		this.slider.addEventListener('keydown', (e) => {
			if (
				e.target !== this.slider &&
				!this.slider.contains(e.target.ownerDocument.activeElement)
			) {
				return;
			}

			switch (e.key) {
				case 'ArrowLeft':
					e.preventDefault();
					this.prev();
					break;
				case 'ArrowRight':
					e.preventDefault();
					this.next();
					break;
				case 'Home':
					e.preventDefault();
					this.goToSlide(0);
					break;
				case 'End':
					e.preventDefault();
					this.goToSlide(this.slides.length - 1);
					break;
			}
		});

		// Make slider focusable
		if (!this.slider.hasAttribute('tabindex')) {
			this.slider.setAttribute('tabindex', '0');
		}
	}

	// Responsive handling
	initResponsive() {
		let resizeTimer;
		// ✅ Store handler for cleanup
		this.handleResize = () => {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(() => {
				if (!this.isDestroyed) {
					// Performance: Recalculate dimensions on resize
					this.updateDimensions();
					if (this.config.scrollDriven) {
						this.updateScrollDrivenDimensions();
						this.updateScrollDrivenStickyTop();
						this.updateScrollDrivenPosition();
					} else {
						this.goToSlide(this.currentIndex, false);
					}
				}
			}, 250);
		};
		window.addEventListener('resize', this.handleResize);
	}

	// Auto-play with IntersectionObserver
	observeVisibility() {
		const observer = new window.IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					this.isInViewport = entry.isIntersecting;
					if (this.isInViewport) {
						this.startAutoplay();
					} else {
						this.stopAutoplay();
					}
				});
			},
			{ threshold: 0.5 }
		);

		observer.observe(this.slider);

		// Pause on hover
		if (this.config.pauseOnHover) {
			this.slider.addEventListener('mouseenter', () =>
				this.stopAutoplay()
			);
			this.slider.addEventListener('mouseleave', () => {
				if (this.isInViewport) {
					this.startAutoplay();
				}
			});
		}
	}

	startAutoplay() {
		if (!this.config.autoplay || this.autoplayTimer) {
			return;
		}

		this.autoplayTimer = setInterval(() => {
			this.next();
		}, this.config.autoplayInterval);
	}

	stopAutoplay() {
		if (this.autoplayTimer) {
			clearInterval(this.autoplayTimer);
			this.autoplayTimer = null;
		}
	}

	// Respect reduced motion preference
	respectReducedMotion() {
		const prefersReducedMotion = window.matchMedia(
			'(prefers-reduced-motion: reduce)'
		).matches;

		if (prefersReducedMotion) {
			this.config.transitionDuration = '0s';
			this.track.style.transition = 'none';
			this.slides.forEach((slide) => {
				slide.style.transition = 'none';
			});

			// Disable autoplay for reduced motion
			if (this.config.autoplay) {
				this.stopAutoplay();
			}
		}
	}

	/**
	 * Initialize scroll-driven horizontal mode
	 * Wraps the slider in a pin spacer, makes slider sticky,
	 * and maps vertical scroll progress to horizontal translateX
	 */
	initScrollDriven() {
		// Create pin spacer wrapper
		this.pinSpacer = document.createElement('div');
		this.pinSpacer.className = 'dsgo-slider-pin-spacer';
		this.slider.parentNode.insertBefore(this.pinSpacer, this.slider);
		this.pinSpacer.appendChild(this.slider);

		// Disable track transition for smooth scroll-driven animation
		this.track.style.transition = 'none';

		// Calculate and set dimensions
		this.updateScrollDrivenDimensions();

		// Center the sticky slider vertically in the viewport
		this.updateScrollDrivenStickyTop();

		// Build progress dots for scroll-driven mode
		this.buildScrollDrivenProgress();

		// Bind scroll handler with requestAnimationFrame throttle
		this.scrollDrivenTicking = false;
		this.handleScrollDriven = () => {
			if (!this.scrollDrivenTicking) {
				this.scrollDrivenTicking = true;
				requestAnimationFrame(() => {
					this.updateScrollDrivenPosition();
					this.scrollDrivenTicking = false;
				});
			}
		};

		window.addEventListener('scroll', this.handleScrollDriven, {
			passive: true,
		});

		// Initial position
		this.updateScrollDrivenPosition();
	}

	/**
	 * Calculate dimensions for scroll-driven mode
	 */
	updateScrollDrivenDimensions() {
		const sliderHeight = this.slider.offsetHeight;
		const viewportWidth = this.viewport.offsetWidth;

		// Total track width (all slides + gaps)
		const slideCount = this.originalSlides.length;
		const slideWidth =
			this.cachedSlideWidth || this.originalSlides[0].offsetWidth;
		const gap =
			this.cachedGap ||
			parseFloat(window.getComputedStyle(this.track).gap) ||
			0;
		const totalTrackWidth =
			slideCount * slideWidth + (slideCount - 1) * gap;

		// How much the track needs to scroll horizontally
		this.scrollDrivenMaxOffset = Math.max(
			0,
			totalTrackWidth - viewportWidth
		);

		// Total scroll distance for the pin spacer
		// scrollDrivenSpeed multiplier controls how much vertical scroll maps to horizontal travel
		const scrollDistance =
			this.scrollDrivenMaxOffset * this.config.scrollDrivenSpeed;

		// Set pin spacer height: slider height + extra scroll room
		this.pinSpacer.style.height = `${sliderHeight + scrollDistance}px`;
	}

	/**
	 * Center the sticky slider vertically in the viewport
	 * Calculates offset so the slider pins at the middle of the screen
	 */
	updateScrollDrivenStickyTop() {
		const sliderHeight = this.slider.offsetHeight;
		this.scrollDrivenStickyTop = Math.max(
			0,
			(window.innerHeight - sliderHeight) / 2
		);
		this.slider.style.top = `${this.scrollDrivenStickyTop}px`;
	}

	/**
	 * Build progress bar indicator for scroll-driven mode
	 */
	buildScrollDrivenProgress() {
		const dotsContainer = document.createElement('div');
		dotsContainer.className = 'dsgo-slider__scroll-progress';
		dotsContainer.setAttribute('aria-hidden', 'true');

		const progressBar = document.createElement('div');
		progressBar.className = 'dsgo-slider__scroll-progress-bar';
		dotsContainer.appendChild(progressBar);

		this.slider.appendChild(dotsContainer);
		this.scrollProgressBar = progressBar;
	}

	/**
	 * Update the horizontal position based on vertical scroll progress
	 */
	updateScrollDrivenPosition() {
		if (!this.pinSpacer) {
			return;
		}

		// Respect reduced motion — don't animate track via scroll
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}

		const sliderHeight = this.slider.offsetHeight;
		const scrollableDistance = this.pinSpacer.offsetHeight - sliderHeight;

		if (scrollableDistance <= 0) {
			return;
		}

		// Calculate progress: 0 when slider first pins at center, 1 when it unpins
		// Account for stickyTop offset so pinning starts when spacer reaches the centered position
		const spacerRect = this.pinSpacer.getBoundingClientRect();
		const scrolledIntoSpacer =
			(this.scrollDrivenStickyTop || 0) - spacerRect.top;
		const progress = Math.max(
			0,
			Math.min(1, scrolledIntoSpacer / scrollableDistance)
		);

		// Map progress to horizontal offset
		const offset = -(progress * this.scrollDrivenMaxOffset);
		this.track.style.transform = `translate3d(${offset}px, 0, 0)`;

		// Update progress bar
		if (this.scrollProgressBar) {
			this.scrollProgressBar.style.width = `${progress * 100}%`;
		}
	}

	/**
	 * Cleanup method to prevent memory leaks
	 * Removes all event listeners and clears timers
	 *
	 * PERFORMANCE FIX: Prevents memory leaks from accumulating
	 * document-level event listeners on pages with multiple sliders
	 */
	destroy() {
		// Stop autoplay timer
		this.stopAutoplay();

		// Remove drag event listeners
		if (this.handleMouseMove) {
			document.removeEventListener('mousemove', this.handleMouseMove);
		}
		if (this.handleMouseUp) {
			document.removeEventListener('mouseup', this.handleMouseUp);
		}
		if (this.handleMouseDown && this.track) {
			this.track.removeEventListener('mousedown', this.handleMouseDown);
		}

		// Remove resize listener
		if (this.handleResize) {
			window.removeEventListener('resize', this.handleResize);
		}

		// Remove scroll-driven listener
		if (this.handleScrollDriven) {
			window.removeEventListener('scroll', this.handleScrollDriven);
		}

		// Unwrap from pin spacer
		if (this.pinSpacer && this.pinSpacer.parentNode) {
			this.pinSpacer.parentNode.insertBefore(this.slider, this.pinSpacer);
			this.pinSpacer.remove();
		}

		// Mark as destroyed
		this.isDestroyed = true;
	}
}

// Store slider instances for cleanup
const sliderInstances = new WeakMap();

// Store timeout IDs for cleanup
const sliderTimeouts = new WeakMap();

/**
 * Get images and their loading state for a slider
 *
 * @param {HTMLElement} slider - The slider element to check
 * @return {{images: HTMLImageElement[], allLoaded: boolean}} Object containing images array and loading state
 */
function getImageLoadState(slider) {
	const images = Array.from(slider.querySelectorAll('img'));
	const allLoaded =
		images.length === 0 ||
		images.every((img) => img.complete && img.naturalHeight !== 0);
	return { images, allLoaded };
}

/**
 * Initialize a single slider instance
 *
 * @param {HTMLElement} slider - The slider element to initialize
 */
function initializeSlider(slider) {
	// Avoid double initialization
	if (sliderInstances.has(slider)) {
		return;
	}

	// Clear any pending timeout for this slider
	const timeoutId = sliderTimeouts.get(slider);
	if (timeoutId) {
		clearTimeout(timeoutId);
		sliderTimeouts.delete(slider);
	}

	const instance = new DSGSlider(slider);
	sliderInstances.set(slider, instance);
}

/**
 * Initialize sliders after ensuring images are loaded
 */
function initializeSliders() {
	const sliders = document.querySelectorAll('.dsgo-slider');

	sliders.forEach((slider) => {
		const { images, allLoaded } = getImageLoadState(slider);

		if (allLoaded) {
			// Images already loaded, initialize immediately
			initializeSlider(slider);
		} else {
			// Wait for images to load
			let loadedCount = 0;
			const totalImages = images.length;

			const checkAndInitialize = () => {
				if (loadedCount === totalImages) {
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
				} else {
					// Attach both load and error listeners to ensure slider initializes
					// even if images fail to load (broken images shouldn't prevent initialization)
					img.addEventListener('load', onImageLoad, { once: true });
					img.addEventListener('error', onImageLoad, { once: true });

					// Re-check in case image loaded between check and listener attachment
					// This prevents a race condition where the image loads after the
					// complete check but before the listener is attached
					if (img.complete) {
						// Remove listeners to prevent double-counting
						// If image loaded after listener attachment, both the listener
						// and this check would increment loadedCount for the same image
						img.removeEventListener('load', onImageLoad);
						img.removeEventListener('error', onImageLoad);
						loadedCount++;
					}
				}
			});

			// Initialize if all images completed during listener setup
			checkAndInitialize();

			// Fallback: Initialize after timeout if images take too long
			const timeoutId = setTimeout(() => {
				if (!sliderInstances.has(slider)) {
					initializeSlider(slider);
				}
			}, 3000);

			// Store timeout ID for cleanup
			sliderTimeouts.set(slider, timeoutId);
		}
	});
}

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', initializeSliders);

// Re-initialize after soft navigation (bfcache, AJAX)
document.addEventListener('dsgo-content-loaded', initializeSliders);

// Also initialize on load event as backup (ensures all resources loaded)
window.addEventListener('load', () => {
	// Initialize any sliders that weren't initialized yet
	const sliders = document.querySelectorAll('.dsgo-slider');
	sliders.forEach((slider) => {
		if (!sliderInstances.has(slider)) {
			initializeSlider(slider);
		}
	});
});

// Cleanup on page unload (prevents memory leaks on SPA navigation)
window.addEventListener('beforeunload', () => {
	const sliders = document.querySelectorAll('.dsgo-slider');
	sliders.forEach((slider) => {
		const instance = sliderInstances.get(slider);
		if (instance && instance.destroy) {
			instance.destroy();
		}
	});
});
