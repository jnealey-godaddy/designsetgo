import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertPresetToCSSVar } from '../../utils/convert-preset-to-css-var';

const SINGLE_SLIDE_EFFECTS = ['fade', 'zoom'];

/**
 * v2: Original save — height/arrowSize had non-empty defaults ('500px'/'48px'),
 * height was emitted unconditionally, colors were raw strings, dotPosition
 * default was 'bottom', and scrollDriven/scrollDrivenSpeed did not yet exist.
 * Migrates to current schema: height/arrowSize reset to empty defaults so
 * the conditional guard in the current save.js takes effect; dotPosition
 * passthrough (v1 will then migrate 'bottom' → 'inside' if needed).
 */
const v2 = {
	attributes: {
		slidesPerView: { type: 'number', default: 1 },
		slidesPerViewTablet: { type: 'number', default: 1 },
		slidesPerViewMobile: { type: 'number', default: 1 },
		height: { type: 'string', default: '500px' },
		aspectRatio: { type: 'string', default: '16/9' },
		useAspectRatio: { type: 'boolean', default: false },
		gap: { type: 'string', default: '20px' },
		showArrows: { type: 'boolean', default: true },
		showDots: { type: 'boolean', default: true },
		arrowStyle: { type: 'string', default: 'default' },
		arrowPosition: { type: 'string', default: 'sides' },
		arrowVerticalPosition: { type: 'string', default: 'center' },
		arrowColor: { type: 'string', default: '' },
		arrowBackgroundColor: { type: 'string', default: '' },
		arrowSize: { type: 'string', default: '48px' },
		arrowPadding: { type: 'string', default: '' },
		dotStyle: { type: 'string', default: 'default' },
		dotPosition: { type: 'string', default: 'bottom' },
		dotColor: { type: 'string', default: '' },
		effect: { type: 'string', default: 'slide' },
		transitionDuration: { type: 'string', default: '0.5s' },
		transitionEasing: { type: 'string', default: 'ease-in-out' },
		autoplay: { type: 'boolean', default: false },
		autoplayInterval: { type: 'number', default: 3000 },
		pauseOnHover: { type: 'boolean', default: true },
		pauseOnInteraction: { type: 'boolean', default: true },
		loop: { type: 'boolean', default: true },
		draggable: { type: 'boolean', default: true },
		swipeable: { type: 'boolean', default: true },
		freeMode: { type: 'boolean', default: false },
		centeredSlides: { type: 'boolean', default: false },
		mobileBreakpoint: { type: 'number', default: 768 },
		tabletBreakpoint: { type: 'number', default: 1024 },
		activeSlide: { type: 'number', default: 0 },
		styleVariation: { type: 'string', default: 'classic' },
		ariaLabel: { type: 'string', default: '' },
	},
	supports: {
		anchor: true,
		align: ['wide', 'full'],
		html: false,
		spacing: { margin: true, padding: true },
		color: { background: true, gradient: true, text: true },
		typography: {
			fontSize: true,
			lineHeight: true,
			fontFamily: true,
			fontWeight: true,
			textTransform: true,
			letterSpacing: true,
		},
		__experimentalBorder: {
			color: true,
			radius: true,
			style: true,
			width: true,
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Match blocks saved with the original height/arrowSize defaults
		// (height was always emitted; arrowSize default was 48px not 24px).
		return (
			!!innerHTML &&
			innerHTML.includes('--dsgo-slider-height:') &&
			innerHTML.includes('--dsgo-slider-arrow-size:48px')
		);
	},
	migrate(attributes) {
		// Reset height and arrowSize to the new empty defaults so the
		// current save.js conditional guard takes effect. Passthrough
		// everything else; v1 handles dotPosition 'bottom' → 'inside'.
		const { height, arrowSize, ...rest } = attributes;
		return {
			...rest,
			height: height === '500px' ? '' : height,
			arrowSize: arrowSize === '48px' ? '24px' : arrowSize,
		};
	},
	save({ attributes }) {
		const {
			slidesPerView,
			slidesPerViewTablet,
			slidesPerViewMobile,
			height,
			aspectRatio,
			useAspectRatio,
			gap,
			showArrows,
			showDots,
			arrowStyle,
			arrowPosition,
			arrowVerticalPosition,
			arrowColor,
			arrowBackgroundColor,
			arrowSize,
			arrowPadding,
			dotStyle,
			dotPosition,
			dotColor,
			effect,
			transitionDuration,
			transitionEasing,
			autoplay,
			autoplayInterval,
			pauseOnHover,
			pauseOnInteraction,
			loop,
			draggable,
			swipeable,
			freeMode,
			centeredSlides,
			mobileBreakpoint,
			tabletBreakpoint,
			activeSlide,
			styleVariation,
			ariaLabel,
		} = attributes;

		const requiresSingleSlideEffect = SINGLE_SLIDE_EFFECTS.includes(effect);
		const effectiveSlidesPerView = requiresSingleSlideEffect
			? 1
			: slidesPerView;
		const effectiveSlidesPerViewTablet = requiresSingleSlideEffect
			? 1
			: slidesPerViewTablet;
		const effectiveSlidesPerViewMobile = requiresSingleSlideEffect
			? 1
			: slidesPerViewMobile;

		const sliderClasses = classnames('dsgo-slider', {
			[`dsgo-slider--${styleVariation}`]: styleVariation,
			[`dsgo-slider--effect-${effect}`]: effect,
			'dsgo-slider--has-arrows': showArrows,
			'dsgo-slider--has-dots': showDots,
			'dsgo-slider--centered': centeredSlides,
			'dsgo-slider--free-mode': freeMode,
		});

		// Original: height was unconditional; colors were raw strings (no convertPresetToCSSVar).
		const customStyles = {
			'--dsgo-slider-height': height,
			'--dsgo-slider-aspect-ratio': aspectRatio,
			'--dsgo-slider-gap': gap,
			'--dsgo-slider-transition': transitionDuration,
			'--dsgo-slider-slides-per-view': String(effectiveSlidesPerView),
			'--dsgo-slider-slides-per-view-tablet': String(
				effectiveSlidesPerViewTablet
			),
			'--dsgo-slider-slides-per-view-mobile': String(
				effectiveSlidesPerViewMobile
			),
			...(arrowColor && { '--dsgo-slider-arrow-color': arrowColor }),
			...(arrowBackgroundColor && {
				'--dsgo-slider-arrow-bg-color': arrowBackgroundColor,
			}),
			...(arrowSize && { '--dsgo-slider-arrow-size': arrowSize }),
			...(arrowPadding && { '--dsgo-slider-arrow-padding': arrowPadding }),
			...(dotColor && { '--dsgo-slider-dot-color': dotColor }),
		};

		const blockProps = useBlockProps.save({
			className: sliderClasses,
			style: customStyles,
			'data-slides-per-view': effectiveSlidesPerView,
			'data-slides-per-view-tablet': effectiveSlidesPerViewTablet,
			'data-slides-per-view-mobile': effectiveSlidesPerViewMobile,
			'data-use-aspect-ratio': useAspectRatio,
			'data-show-arrows': showArrows,
			'data-show-dots': showDots,
			'data-arrow-style': arrowStyle,
			'data-arrow-position': arrowPosition,
			'data-arrow-vertical-position': arrowVerticalPosition,
			'data-dot-style': dotStyle,
			'data-dot-position': dotPosition,
			'data-effect': effect,
			'data-transition-duration': transitionDuration,
			'data-transition-easing': transitionEasing,
			'data-autoplay': autoplay,
			'data-autoplay-interval': autoplayInterval,
			'data-pause-on-hover': pauseOnHover,
			'data-pause-on-interaction': pauseOnInteraction,
			'data-loop': loop,
			'data-draggable': draggable,
			'data-swipeable': swipeable,
			'data-free-mode': freeMode,
			'data-centered-slides': centeredSlides,
			'data-mobile-breakpoint': mobileBreakpoint,
			'data-tablet-breakpoint': tabletBreakpoint,
			'data-active-slide': activeSlide,
			role: 'region',
			'aria-label': ariaLabel || 'Image slider',
			'aria-roledescription': 'slider',
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-slider__track',
		});

		return (
			<div {...blockProps}>
				<div className="dsgo-slider__viewport">
					<div {...innerBlocksProps} />
				</div>
			</div>
		);
	},
};

/**
 * v1: dotPosition changed from "bottom"/"top" to "inside"/"outside".
 * Migrates old "bottom" and "top" values to "inside".
 */
const v1 = {
	attributes: {
		slidesPerView: { type: 'number', default: 1 },
		slidesPerViewTablet: { type: 'number', default: 1 },
		slidesPerViewMobile: { type: 'number', default: 1 },
		height: { type: 'string', default: '' },
		aspectRatio: { type: 'string', default: '16/9' },
		useAspectRatio: { type: 'boolean', default: false },
		gap: { type: 'string', default: '20px' },
		showArrows: { type: 'boolean', default: true },
		showDots: { type: 'boolean', default: true },
		arrowStyle: { type: 'string', default: 'default' },
		arrowPosition: { type: 'string', default: 'sides' },
		arrowVerticalPosition: { type: 'string', default: 'center' },
		arrowColor: { type: 'string', default: '' },
		arrowBackgroundColor: { type: 'string', default: '' },
		arrowSize: { type: 'string', default: '24px' },
		arrowPadding: { type: 'string', default: '' },
		dotStyle: { type: 'string', default: 'default' },
		dotPosition: { type: 'string', default: 'bottom' },
		dotColor: { type: 'string', default: '' },
		effect: { type: 'string', default: 'slide' },
		transitionDuration: { type: 'string', default: '0.5s' },
		transitionEasing: { type: 'string', default: 'ease-in-out' },
		autoplay: { type: 'boolean', default: false },
		autoplayInterval: { type: 'number', default: 3000 },
		pauseOnHover: { type: 'boolean', default: true },
		pauseOnInteraction: { type: 'boolean', default: true },
		loop: { type: 'boolean', default: true },
		draggable: { type: 'boolean', default: true },
		swipeable: { type: 'boolean', default: true },
		freeMode: { type: 'boolean', default: false },
		centeredSlides: { type: 'boolean', default: false },
		mobileBreakpoint: { type: 'number', default: 768 },
		tabletBreakpoint: { type: 'number', default: 1024 },
		activeSlide: { type: 'number', default: 0 },
		styleVariation: { type: 'string', default: 'classic' },
		ariaLabel: { type: 'string', default: '' },
	},
	supports: {
		anchor: true,
		align: ['wide', 'full'],
		html: false,
		spacing: { margin: true, padding: true },
		color: { background: true, gradient: true, text: true },
		typography: {
			fontSize: true,
			lineHeight: true,
			fontFamily: true,
			fontWeight: true,
			textTransform: true,
			letterSpacing: true,
		},
		__experimentalBorder: {
			color: true,
			radius: true,
			style: true,
			width: true,
		},
	},
	isEligible(attributes) {
		return (
			attributes.dotPosition === 'bottom' ||
			attributes.dotPosition === 'top'
		);
	},
	migrate(attributes) {
		return {
			...attributes,
			dotPosition: 'inside',
		};
	},
	save({ attributes }) {
		const {
			slidesPerView,
			slidesPerViewTablet,
			slidesPerViewMobile,
			height,
			aspectRatio,
			useAspectRatio,
			gap,
			showArrows,
			showDots,
			arrowStyle,
			arrowPosition,
			arrowVerticalPosition,
			arrowColor,
			arrowBackgroundColor,
			arrowSize,
			arrowPadding,
			dotStyle,
			dotPosition,
			dotColor,
			effect,
			transitionDuration,
			transitionEasing,
			autoplay,
			autoplayInterval,
			pauseOnHover,
			pauseOnInteraction,
			loop,
			draggable,
			swipeable,
			freeMode,
			centeredSlides,
			mobileBreakpoint,
			tabletBreakpoint,
			activeSlide,
			styleVariation,
			ariaLabel,
		} = attributes;

		const requiresSingleSlideEffect = SINGLE_SLIDE_EFFECTS.includes(effect);
		const effectiveSlidesPerView = requiresSingleSlideEffect
			? 1
			: slidesPerView;
		const effectiveSlidesPerViewTablet = requiresSingleSlideEffect
			? 1
			: slidesPerViewTablet;
		const effectiveSlidesPerViewMobile = requiresSingleSlideEffect
			? 1
			: slidesPerViewMobile;

		const sliderClasses = classnames('dsgo-slider', {
			[`dsgo-slider--${styleVariation}`]: styleVariation,
			[`dsgo-slider--effect-${effect}`]: effect,
			'dsgo-slider--has-arrows': showArrows,
			'dsgo-slider--has-dots': showDots,
			'dsgo-slider--centered': centeredSlides,
			'dsgo-slider--free-mode': freeMode,
		});

		const customStyles = {
			...(height && { '--dsgo-slider-height': height }),
			'--dsgo-slider-aspect-ratio': aspectRatio,
			'--dsgo-slider-gap': gap,
			'--dsgo-slider-transition': transitionDuration,
			'--dsgo-slider-slides-per-view': String(effectiveSlidesPerView),
			'--dsgo-slider-slides-per-view-tablet': String(
				effectiveSlidesPerViewTablet
			),
			'--dsgo-slider-slides-per-view-mobile': String(
				effectiveSlidesPerViewMobile
			),
			...(arrowColor && {
				'--dsgo-slider-arrow-color': convertPresetToCSSVar(arrowColor),
			}),
			...(arrowBackgroundColor && {
				'--dsgo-slider-arrow-bg-color':
					convertPresetToCSSVar(arrowBackgroundColor),
			}),
			...(arrowSize && { '--dsgo-slider-arrow-size': arrowSize }),
			...(arrowPadding && {
				'--dsgo-slider-arrow-padding': arrowPadding,
			}),
			...(dotColor && {
				'--dsgo-slider-dot-color': convertPresetToCSSVar(dotColor),
			}),
		};

		const blockProps = useBlockProps.save({
			className: sliderClasses,
			style: customStyles,
			'data-slides-per-view': effectiveSlidesPerView,
			'data-slides-per-view-tablet': effectiveSlidesPerViewTablet,
			'data-slides-per-view-mobile': effectiveSlidesPerViewMobile,
			'data-use-aspect-ratio': useAspectRatio,
			'data-show-arrows': showArrows,
			'data-show-dots': showDots,
			'data-arrow-style': arrowStyle,
			'data-arrow-position': arrowPosition,
			'data-arrow-vertical-position': arrowVerticalPosition,
			'data-dot-style': dotStyle,
			'data-dot-position': dotPosition,
			'data-effect': effect,
			'data-transition-duration': transitionDuration,
			'data-transition-easing': transitionEasing,
			'data-autoplay': autoplay,
			'data-autoplay-interval': autoplayInterval,
			'data-pause-on-hover': pauseOnHover,
			'data-pause-on-interaction': pauseOnInteraction,
			'data-loop': loop,
			'data-draggable': draggable,
			'data-swipeable': swipeable,
			'data-free-mode': freeMode,
			'data-centered-slides': centeredSlides,
			'data-mobile-breakpoint': mobileBreakpoint,
			'data-tablet-breakpoint': tabletBreakpoint,
			'data-active-slide': activeSlide,
			role: 'region',
			'aria-label': ariaLabel || 'Image slider',
			'aria-roledescription': 'slider',
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-slider__track',
		});

		return (
			<div {...blockProps}>
				<div className="dsgo-slider__viewport">
					<div {...innerBlocksProps} />
				</div>
			</div>
		);
	},
};

export default [v2, v1];
