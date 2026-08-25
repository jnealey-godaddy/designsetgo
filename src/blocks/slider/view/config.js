/**
 * Slider — frontend configuration parsing.
 *
 * The block's save.js and render.php both serialise every setting onto the
 * root element as `data-*` attributes; this module turns that back into a
 * plain config object and derives the few values that depend on the current
 * viewport rather than on the author's settings alone.
 */

/** Effects that only ever show one slide, whatever slidesPerView says. */
export const SINGLE_SLIDE_EFFECTS = ['fade', 'zoom'];

/**
 * Parse a CSS time value into milliseconds.
 *
 * `transitionDuration` is authored as a CSS string, so it can arrive as
 * either `0.5s` or `500ms`. Reading it with a bare parseFloat() and
 * multiplying by 1000 turns `500ms` into an eight-minute transition, which
 * leaves `isAnimating` latched and the slider frozen.
 *
 * @param {string} value    CSS time value.
 * @param {number} fallback Milliseconds to use when the value is unparseable.
 * @return {number} Duration in milliseconds.
 */
export function parseDurationMs(value, fallback = 500) {
	const raw = String(value ?? '').trim();
	const amount = parseFloat(raw);
	if (!Number.isFinite(amount)) {
		return fallback;
	}
	return /ms\s*$/i.test(raw) ? amount : amount * 1000;
}

/**
 * Read the slider's configuration off its data attributes.
 *
 * @param {HTMLElement} slider The `.dsgo-slider` root element.
 * @return {Object} Parsed configuration.
 */
export function parseSliderConfig(slider) {
	const data = slider.dataset;
	const effect = data.effect || 'slide';
	const forcesSingleSlide = SINGLE_SLIDE_EFFECTS.includes(effect);
	const scrollDriven = data.scrollDriven === 'true';
	const perView = (key) =>
		forcesSingleSlide ? 1 : parseFloat(data[key]) || 1;

	return {
		slidesPerView: perView('slidesPerView'),
		slidesPerViewTablet: perView('slidesPerViewTablet'),
		slidesPerViewMobile: perView('slidesPerViewMobile'),
		effect,
		transitionDuration: data.transitionDuration || '0.5s',
		transitionDurationMs: parseDurationMs(data.transitionDuration, 500),
		transitionEasing: data.transitionEasing || 'ease-in-out',
		// Scroll-driven mode owns the track transform outright: autoplay,
		// looping and the nav chrome would all fight it for control.
		autoplay: scrollDriven ? false : data.autoplay === 'true',
		autoplayInterval: parseInt(data.autoplayInterval, 10) || 3000,
		pauseOnHover: data.pauseOnHover === 'true',
		pauseOnInteraction: data.pauseOnInteraction === 'true',
		loop: scrollDriven ? false : data.loop === 'true',
		draggable: data.draggable === 'true',
		swipeable: data.swipeable === 'true',
		freeMode: data.freeMode === 'true',
		centeredSlides: data.centeredSlides === 'true',
		showArrows: scrollDriven ? false : data.showArrows === 'true',
		showDots: scrollDriven ? false : data.showDots === 'true',
		mobileBreakpoint: parseInt(data.mobileBreakpoint, 10) || 768,
		tabletBreakpoint: parseInt(data.tabletBreakpoint, 10) || 1024,
		activeSlide: parseInt(data.activeSlide, 10) || 0,
		scrollDriven,
		scrollDrivenSpeed: parseFloat(data.scrollDrivenSpeed) || 1,
	};
}

/**
 * Predict how many slides are on screen at the current viewport width.
 *
 * style.scss sizes the slides from three custom properties behind
 * `max-width` media queries; the JS needs the same number to decide how many
 * clones the loop needs, how far the track may travel before it runs out of
 * slides, and which slides are visible to assistive tech. Reading only the
 * desktop value — as this used to — over-scrolls past the last slide and
 * mislabels visible slides as hidden on every device but the widest.
 *
 * This is a *prediction*, used only before there is any layout to measure.
 * It assumes the author left `mobileBreakpoint` / `tabletBreakpoint` at the
 * values style.scss hardcodes, which is not something the JS can enforce — a
 * media query cannot read a custom property. Once the slider has painted,
 * DSGSlider.measuredSlidesPerView() supersedes this by measuring the slide
 * CSS actually rendered, so an author-moved breakpoint self-corrects.
 *
 * @param {Object} config Parsed slider config.
 * @param {number} width  Viewport width in pixels.
 * @return {number} Slides visible at that width.
 */
export function slidesPerViewFor(config, width) {
	if (width <= config.mobileBreakpoint) {
		return config.slidesPerViewMobile;
	}
	if (width <= config.tabletBreakpoint) {
		return config.slidesPerViewTablet;
	}
	return config.slidesPerView;
}
