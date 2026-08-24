/**
 * Slider — configuration module unit tests.
 *
 * Covers the two derivations that used to be wrong in the runtime: reading a
 * CSS time value, and resolving how many slides are on screen at a given
 * viewport width.
 *
 * @package
 */

import {
	parseDurationMs,
	parseSliderConfig,
	slidesPerViewFor,
} from '../../../src/blocks/slider/view/config';

describe('parseDurationMs', () => {
	test('reads seconds', () => {
		expect(parseDurationMs('0.5s')).toBe(500);
		expect(parseDurationMs('1s')).toBe(1000);
	});

	test('reads milliseconds without multiplying them again', () => {
		expect(parseDurationMs('500ms')).toBe(500);
		expect(parseDurationMs('250MS')).toBe(250);
	});

	test('falls back when the value is unparseable', () => {
		expect(parseDurationMs('', 400)).toBe(400);
		expect(parseDurationMs(undefined, 400)).toBe(400);
		expect(parseDurationMs('ease-in-out', 400)).toBe(400);
	});
});

describe('slidesPerViewFor', () => {
	const config = {
		slidesPerView: 4,
		slidesPerViewTablet: 2,
		slidesPerViewMobile: 1,
		mobileBreakpoint: 768,
		tabletBreakpoint: 1024,
	};

	test('resolves per breakpoint', () => {
		expect(slidesPerViewFor(config, 1440)).toBe(4);
		expect(slidesPerViewFor(config, 1025)).toBe(4);
		expect(slidesPerViewFor(config, 1024)).toBe(2);
		expect(slidesPerViewFor(config, 769)).toBe(2);
		expect(slidesPerViewFor(config, 768)).toBe(1);
		expect(slidesPerViewFor(config, 375)).toBe(1);
	});
});

describe('parseSliderConfig', () => {
	/**
	 * @param {Object} data data-* attributes to set on the root.
	 * @return {HTMLElement} A configured slider root (not attached).
	 */
	function root(data = {}) {
		const el = document.createElement('div');
		Object.entries(data).forEach(([key, value]) => {
			el.dataset[key] = String(value);
		});
		return el;
	}

	test('derives transitionDurationMs alongside the raw string', () => {
		const config = parseSliderConfig(root({ transitionDuration: '300ms' }));
		expect(config.transitionDuration).toBe('300ms');
		expect(config.transitionDurationMs).toBe(300);
	});

	test('single-slide effects pin every breakpoint to one slide', () => {
		const config = parseSliderConfig(
			root({ effect: 'fade', slidesPerView: 3, slidesPerViewTablet: 2 })
		);
		expect(config.slidesPerView).toBe(1);
		expect(config.slidesPerViewTablet).toBe(1);
		expect(config.slidesPerViewMobile).toBe(1);
	});

	test('scroll-driven mode disables the paged features it would fight', () => {
		const config = parseSliderConfig(
			root({
				scrollDriven: 'true',
				autoplay: 'true',
				loop: 'true',
				showArrows: 'true',
				showDots: 'true',
			})
		);
		expect(config.autoplay).toBe(false);
		expect(config.loop).toBe(false);
		expect(config.showArrows).toBe(false);
		expect(config.showDots).toBe(false);
	});
});
