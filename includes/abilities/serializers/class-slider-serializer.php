<?php
/**
 * Serializer for designsetgo/slider.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/slider
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slider_Serializer.
 */
class Slider_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// The inline isset() fallbacks below are normally unreachable
		// because apply_block_json_defaults() fills these from the
		// registry before we get here. They only fire when the block
		// isn't registered (e.g., build folder missing), so keep them
		// synced with src/blocks/slider/block.json — not with any
		// past convention like height=500px / arrowSize=48px.
		$slides_per_view        = isset( $attributes['slidesPerView'] ) ? Serializer_Support::numeric_attribute( $attributes['slidesPerView'] ) : 1;
		$slides_per_view_tablet = isset( $attributes['slidesPerViewTablet'] ) ? Serializer_Support::numeric_attribute( $attributes['slidesPerViewTablet'] ) : 1;
		$slides_per_view_mobile = isset( $attributes['slidesPerViewMobile'] ) ? Serializer_Support::numeric_attribute( $attributes['slidesPerViewMobile'] ) : 1;
		$height                 = isset( $attributes['height'] ) ? $attributes['height'] : '';
		$aspect_ratio           = isset( $attributes['aspectRatio'] ) ? $attributes['aspectRatio'] : '16/9';
		$use_aspect_ratio       = isset( $attributes['useAspectRatio'] ) ? $attributes['useAspectRatio'] : false;
		$gap                    = isset( $attributes['gap'] ) ? $attributes['gap'] : '20px';
		$show_arrows            = isset( $attributes['showArrows'] ) ? $attributes['showArrows'] : true;
		$show_dots              = isset( $attributes['showDots'] ) ? $attributes['showDots'] : true;
		$arrow_style            = isset( $attributes['arrowStyle'] ) ? $attributes['arrowStyle'] : 'default';
		$arrow_position         = isset( $attributes['arrowPosition'] ) ? $attributes['arrowPosition'] : 'sides';
		$arrow_vertical_pos     = isset( $attributes['arrowVerticalPosition'] ) ? $attributes['arrowVerticalPosition'] : 'center';
		$arrow_color            = isset( $attributes['arrowColor'] ) ? $attributes['arrowColor'] : '';
		$arrow_bg_color         = isset( $attributes['arrowBackgroundColor'] ) ? $attributes['arrowBackgroundColor'] : '';
		$arrow_size             = isset( $attributes['arrowSize'] ) ? $attributes['arrowSize'] : '24px';
		$arrow_padding          = isset( $attributes['arrowPadding'] ) ? $attributes['arrowPadding'] : '';
		$dot_style              = isset( $attributes['dotStyle'] ) ? $attributes['dotStyle'] : 'default';
		$dot_position           = isset( $attributes['dotPosition'] ) ? $attributes['dotPosition'] : 'inside';
		$dot_color              = isset( $attributes['dotColor'] ) ? $attributes['dotColor'] : '';
		$effect                 = isset( $attributes['effect'] ) ? $attributes['effect'] : 'slide';
		$transition_duration    = isset( $attributes['transitionDuration'] ) ? $attributes['transitionDuration'] : '0.5s';
		$transition_easing      = isset( $attributes['transitionEasing'] ) ? $attributes['transitionEasing'] : 'ease-in-out';
		$autoplay               = isset( $attributes['autoplay'] ) ? $attributes['autoplay'] : false;
		$autoplay_interval      = isset( $attributes['autoplayInterval'] ) ? Serializer_Support::numeric_attribute( $attributes['autoplayInterval'] ) : 3000;
		$pause_on_hover         = isset( $attributes['pauseOnHover'] ) ? $attributes['pauseOnHover'] : true;
		$pause_on_interaction   = isset( $attributes['pauseOnInteraction'] ) ? $attributes['pauseOnInteraction'] : true;
		$loop                   = isset( $attributes['loop'] ) ? $attributes['loop'] : true;
		$draggable              = isset( $attributes['draggable'] ) ? $attributes['draggable'] : true;
		$swipeable              = isset( $attributes['swipeable'] ) ? $attributes['swipeable'] : true;
		$free_mode              = isset( $attributes['freeMode'] ) ? $attributes['freeMode'] : false;
		$centered_slides        = isset( $attributes['centeredSlides'] ) ? $attributes['centeredSlides'] : false;
		$mobile_breakpoint      = isset( $attributes['mobileBreakpoint'] ) ? Serializer_Support::numeric_attribute( $attributes['mobileBreakpoint'] ) : 768;
		$tablet_breakpoint      = isset( $attributes['tabletBreakpoint'] ) ? Serializer_Support::numeric_attribute( $attributes['tabletBreakpoint'] ) : 1024;
		$active_slide           = isset( $attributes['activeSlide'] ) ? Serializer_Support::numeric_attribute( $attributes['activeSlide'] ) : 0;
		$style_variation        = isset( $attributes['styleVariation'] ) ? $attributes['styleVariation'] : 'classic';
		$aria_label             = isset( $attributes['ariaLabel'] ) ? $attributes['ariaLabel'] : '';
		$scroll_driven          = isset( $attributes['scrollDriven'] ) ? $attributes['scrollDriven'] : false;
		$scroll_driven_speed    = isset( $attributes['scrollDrivenSpeed'] ) ? floatval( $attributes['scrollDrivenSpeed'] ) : 1;

		// Single slide effects.
		$single_slide_effects    = array( 'fade', 'zoom' );
		$requires_single         = in_array( $effect, $single_slide_effects, true );
		$effective_slides        = $requires_single ? 1 : $slides_per_view;
		$effective_slides_tablet = $requires_single ? 1 : $slides_per_view_tablet;
		$effective_slides_mobile = $requires_single ? 1 : $slides_per_view_mobile;

		// Build classes.
		$class_parts = array( 'wp-block-designsetgo-slider', 'dsgo-slider' );
		if ( $style_variation ) {
			$class_parts[] = 'dsgo-slider--' . esc_attr( $style_variation );
		}
		if ( $effect ) {
			$class_parts[] = 'dsgo-slider--effect-' . esc_attr( $effect );
		}
		if ( $show_arrows ) {
			$class_parts[] = 'dsgo-slider--has-arrows';
		}
		if ( $show_dots ) {
			$class_parts[] = 'dsgo-slider--has-dots';
		}
		if ( $centered_slides ) {
			$class_parts[] = 'dsgo-slider--centered';
		}
		if ( $free_mode ) {
			$class_parts[] = 'dsgo-slider--free-mode';
		}
		if ( $scroll_driven ) {
			$class_parts[] = 'dsgo-slider--scroll-driven';
		}

		// Build style. Mirror save.js: height is only included when
		// truthy (block.json default is ""), and arrow size follows the
		// same rule. Emitting them unconditionally here breaks
		// round-tripping against save().
		$style_parts = array();
		if ( $height ) {
			$style_parts[] = '--dsgo-slider-height:' . esc_attr( $height );
		}
		$style_parts[] = '--dsgo-slider-aspect-ratio:' . esc_attr( $aspect_ratio );
		$style_parts[] = '--dsgo-slider-gap:' . esc_attr( $gap );
		$style_parts[] = '--dsgo-slider-transition:' . esc_attr( $transition_duration );
		$style_parts[] = '--dsgo-slider-slides-per-view:' . esc_attr( (string) $effective_slides );
		$style_parts[] = '--dsgo-slider-slides-per-view-tablet:' . esc_attr( (string) $effective_slides_tablet );
		$style_parts[] = '--dsgo-slider-slides-per-view-mobile:' . esc_attr( (string) $effective_slides_mobile );
		if ( $arrow_color ) {
			$style_parts[] = '--dsgo-slider-arrow-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( $arrow_color ) );
		}
		if ( $arrow_bg_color ) {
			$style_parts[] = '--dsgo-slider-arrow-bg-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( $arrow_bg_color ) );
		}
		if ( $arrow_size ) {
			$style_parts[] = '--dsgo-slider-arrow-size:' . esc_attr( $arrow_size );
		}
		if ( $arrow_padding ) {
			$style_parts[] = '--dsgo-slider-arrow-padding:' . esc_attr( $arrow_padding );
		}
		if ( $dot_color ) {
			$style_parts[] = '--dsgo-slider-dot-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( $dot_color ) );
		}
		$style = implode( ';', $style_parts );

		// Build data attributes.
		$data_attrs  = ' data-slides-per-view="' . esc_attr( (string) $effective_slides ) . '"';
		$data_attrs .= ' data-slides-per-view-tablet="' . esc_attr( (string) $effective_slides_tablet ) . '"';
		$data_attrs .= ' data-slides-per-view-mobile="' . esc_attr( (string) $effective_slides_mobile ) . '"';
		$data_attrs .= ' data-use-aspect-ratio="' . ( $use_aspect_ratio ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-show-arrows="' . ( $show_arrows ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-show-dots="' . ( $show_dots ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-arrow-style="' . esc_attr( $arrow_style ) . '"';
		$data_attrs .= ' data-arrow-position="' . esc_attr( $arrow_position ) . '"';
		$data_attrs .= ' data-arrow-vertical-position="' . esc_attr( $arrow_vertical_pos ) . '"';
		$data_attrs .= ' data-dot-style="' . esc_attr( $dot_style ) . '"';
		$data_attrs .= ' data-dot-position="' . esc_attr( $dot_position ) . '"';
		$data_attrs .= ' data-effect="' . esc_attr( $effect ) . '"';
		$data_attrs .= ' data-transition-duration="' . esc_attr( $transition_duration ) . '"';
		$data_attrs .= ' data-transition-easing="' . esc_attr( $transition_easing ) . '"';
		$data_attrs .= ' data-autoplay="' . ( $autoplay ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-autoplay-interval="' . esc_attr( (string) $autoplay_interval ) . '"';
		$data_attrs .= ' data-pause-on-hover="' . ( $pause_on_hover ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-pause-on-interaction="' . ( $pause_on_interaction ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-loop="' . ( $loop ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-draggable="' . ( $draggable ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-swipeable="' . ( $swipeable ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-free-mode="' . ( $free_mode ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-centered-slides="' . ( $centered_slides ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-mobile-breakpoint="' . esc_attr( (string) $mobile_breakpoint ) . '"';
		$data_attrs .= ' data-tablet-breakpoint="' . esc_attr( (string) $tablet_breakpoint ) . '"';
		$data_attrs .= ' data-active-slide="' . esc_attr( (string) $active_slide ) . '"';
		if ( $scroll_driven ) {
			$data_attrs .= ' data-scroll-driven="true"';
			$data_attrs .= ' data-scroll-driven-speed="' . esc_attr( (string) $scroll_driven_speed ) . '"';
		}

		$aria = $aria_label ? $aria_label : 'Image slider';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '"' . $data_attrs . ' role="region" aria-label="' . esc_attr( $aria ) . '" aria-roledescription="slider"><div class="dsgo-slider__viewport"><div class="dsgo-slider__track">',
			'closing' => '</div></div></div>',
		);
	}
}
