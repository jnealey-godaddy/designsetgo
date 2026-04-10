<?php
/**
 * DesignSetGo Interactive Block Handlers for HTML-to-Block Converter.
 *
 * Handles interactive HTML elements and maps them to DesignSetGo interactive blocks.
 *
 * @package DesignSetGo
 * @subpackage HTML_Converter
 * @since 2.1.0
 */

namespace DesignSetGo\HTML_Converter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DesignSetGo Interactive Handlers class.
 *
 * Handles interactive blocks: accordion, tabs, timeline, modal, slider, flip-card.
 */
class Dsgo_Interactive_Handlers {

	/**
	 * Converter instance.
	 *
	 * @var Converter
	 */
	private Converter $converter;

	/**
	 * Attribute mapper instance.
	 *
	 * @var Attribute_Mapper
	 */
	private Attribute_Mapper $attribute_mapper;

	/**
	 * Constructor.
	 *
	 * @param Converter        $converter        Converter instance.
	 * @param Attribute_Mapper $attribute_mapper  Attribute mapper instance.
	 */
	public function __construct( Converter $converter, Attribute_Mapper $attribute_mapper ) {
		$this->converter        = $converter;
		$this->attribute_mapper = $attribute_mapper;
	}

	/**
	 * Register interactive handlers with the element handler registry.
	 *
	 * @param Element_Handler $registry Handler registry.
	 */
	public function register( Element_Handler $registry ): void {
		$registry->register_tag_handler( 'details', array( $this, 'handle_accordion' ) );
		$registry->register_class_handler( 'accordion', array( $this, 'handle_accordion_wrapper' ) );
		$registry->register_class_handler( 'tabs', array( $this, 'handle_tabs' ) );
		$registry->register_class_handler( 'timeline', array( $this, 'handle_timeline' ) );
		$registry->register_class_handler( 'modal', array( $this, 'handle_modal' ) );
		$registry->register_class_handler( 'slider', array( $this, 'handle_slider' ) );
		$registry->register_class_handler( 'flip-card', array( $this, 'handle_flip_card' ) );
	}

	/**
	 * Handle details/summary -> designsetgo/accordion + accordion-item.
	 *
	 * Save.js accordion: <div class="dsgo-accordion"><div class="dsgo-accordion__items">...</div></div>
	 * Save.js accordion-item: <div class="dsgo-accordion-item dsgo-accordion-item--closed">...complex header + panel...</div>
	 *
	 * @param \DOMElement $element   The details element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_accordion( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/accordion' );

		// Extract summary as title, remaining content as body.
		$summary = $element->getElementsByTagName( 'summary' )->item( 0 );
		$title   = $summary ? wp_strip_all_tags( $converter->get_inner_html( $summary ) ) : '';

		// Process non-summary children as inner blocks of the accordion item.
		$item_inner_blocks = array();
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement && 'summary' !== strtolower( $child->tagName ) ) {
				$block = $converter->process_node( $child );
				if ( $block ) {
					$item_inner_blocks[] = $block;
				}
			}
		}

		$unique_id  = 'accordion-item-' . wp_unique_id( '' );
		$item_attrs = array(
			'title'    => $title,
			'uniqueId' => $unique_id,
		);

		// Build accordion-item with save.js markup.
		// IDs must match what save.js derives from the uniqueId attribute.
		$header_id     = $unique_id . '-header';
		$panel_id      = $unique_id . '-panel';
		$title_escaped = esc_html( $title );

		$item_open  = '<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed" data-initially-open="false">';
		$item_open .= '<div class="dsgo-accordion-item__header">';
		$item_open .= '<button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '" id="' . esc_attr( $header_id ) . '">';
		$item_open .= '<span class="dsgo-accordion-item__title">' . $title_escaped . '</span>';
		$item_open .= '<span class="dsgo-accordion-item__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></span>';
		$item_open .= '</button></div>';
		$item_open .= '<div class="dsgo-accordion-item__panel" role="region" aria-labelledby="' . esc_attr( $header_id ) . '" id="' . esc_attr( $panel_id ) . '" hidden>';
		$item_open .= '<div class="dsgo-accordion-item__content">';
		$item_close = '</div></div></div>';

		$accordion_item = Dsgo_Handlers::build_container_block(
			'designsetgo/accordion-item',
			$item_attrs,
			$item_inner_blocks,
			$item_open,
			$item_close
		);

		// Build accordion wrapper.
		$acc_open  = '<div class="wp-block-designsetgo-accordion dsgo-accordion" data-allow-multiple="false" data-icon-style="chevron"><div class="dsgo-accordion__items">';
		$acc_close = '</div></div>';

		return Dsgo_Handlers::build_container_block(
			'designsetgo/accordion',
			$attrs,
			array( $accordion_item ),
			$acc_open,
			$acc_close
		);
	}

	/**
	 * Handle div.accordion wrapper -> designsetgo/accordion with multiple items.
	 *
	 * @param \DOMElement $element   The accordion wrapper element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_accordion_wrapper( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/accordion' );

		// Look for details elements as items.
		$inner_blocks = array();
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				if ( 'details' === strtolower( $child->tagName ) ) {
					$item = $this->handle_accordion( $child, $converter );
					// Extract the accordion-item from the wrapped accordion.
					if ( ! empty( $item['innerBlocks'] ) ) {
						$inner_blocks[] = $item['innerBlocks'][0];
					}
				} else {
					$block = $converter->process_node( $child );
					if ( $block ) {
						$inner_blocks[] = $block;
					}
				}
			}
		}

		$open  = '<div class="wp-block-designsetgo-accordion dsgo-accordion" data-allow-multiple="false" data-icon-style="chevron"><div class="dsgo-accordion__items">';
		$close = '</div></div>';

		return Dsgo_Handlers::build_container_block( 'designsetgo/accordion', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle tabs container -> designsetgo/tabs.
	 *
	 * Save.js renders: <div class="dsgo-tabs dsgo-tabs--vertical dsgo-tabs--default dsgo-tabs--align-left">
	 *   <div class="dsgo-tabs__nav" role="tablist"></div>
	 *   <div class="dsgo-tabs__panels">...inner blocks...</div>
	 * </div>
	 *
	 * @param \DOMElement $element   The tabs container element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_tabs( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/tabs' );
		$inner_blocks = $converter->process_children( $element );

		$unique_id         = 'tabs-' . wp_unique_id( '' );
		$attrs['uniqueId'] = $unique_id;
		$open              = '<div class="wp-block-designsetgo-tabs dsgo-tabs dsgo-tabs-' . esc_attr( $unique_id ) . ' dsgo-tabs--vertical dsgo-tabs--default dsgo-tabs--align-left" data-active-tab="0">';
		$open     .= '<div class="dsgo-tabs__nav" role="tablist"></div>';
		$open     .= '<div class="dsgo-tabs__panels">';
		$close     = '</div></div>';

		return Dsgo_Handlers::build_container_block( 'designsetgo/tabs', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle timeline container -> designsetgo/timeline.
	 *
	 * Save.js renders: <div class="dsgo-timeline dsgo-timeline--vertical dsgo-timeline--layout-stacked dsgo-timeline--marker-circle">
	 *   <div class="dsgo-timeline__line" aria-hidden="true"></div>
	 *   <div class="dsgo-timeline__items">...inner blocks...</div>
	 * </div>
	 *
	 * @param \DOMElement $element   The timeline container element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_timeline( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/timeline' );
		$inner_blocks = $converter->process_children( $element );

		$open  = '<div class="wp-block-designsetgo-timeline dsgo-timeline dsgo-timeline--vertical dsgo-timeline--layout-stacked dsgo-timeline--marker-circle" data-animate="false" data-animation-duration="600" data-stagger-delay="100">';
		$open .= '<div class="dsgo-timeline__line" aria-hidden="true"></div>';
		$open .= '<div class="dsgo-timeline__items">';
		$close = '</div></div>';

		return Dsgo_Handlers::build_container_block( 'designsetgo/timeline', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle modal container -> designsetgo/modal.
	 *
	 * @param \DOMElement $element   The modal container element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_modal( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/modal' );
		$inner_blocks = $converter->process_children( $element );

		$open  = '<div class="wp-block-designsetgo-modal dsgo-modal">';
		$close = '</div>';

		return Dsgo_Handlers::build_container_block( 'designsetgo/modal', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle slider container -> designsetgo/slider.
	 *
	 * Save.js renders: <div class="dsgo-slider dsgo-slider--classic dsgo-slider--effect-slide
	 *   dsgo-slider--has-arrows dsgo-slider--has-dots" style="--dsgo-slider-*" data-*...>
	 *   <div class="dsgo-slider__viewport"><div class="dsgo-slider__track">...slides...</div></div>
	 * </div>
	 *
	 * @param \DOMElement $element   The slider container element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_slider( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/slider' );

		// Each child becomes a slide.
		$slides = array();
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				$slide_inner = $converter->process_children( $child );

				$slide_open  = '<div class="wp-block-designsetgo-slide dsgo-slide" role="group" aria-roledescription="slide"><div class="dsgo-slide__content">';
				$slide_close = '</div></div>';

				$slides[] = Dsgo_Handlers::build_container_block( 'designsetgo/slide', array(), $slide_inner, $slide_open, $slide_close );
			}
		}

		$open = $this->build_slider_open_html( $attrs );

		$close = '</div></div></div>';

		return Dsgo_Handlers::build_container_block( 'designsetgo/slider', $attrs, $slides, $open, $close );
	}

	/**
	 * Build the slider opening HTML with data-* attributes and CSS custom properties
	 * to match save.js output.
	 *
	 * @param array<string, mixed> $attrs Block attributes (read, not modified).
	 * @return string Opening HTML.
	 */
	private function build_slider_open_html( array $attrs ): string {
		// Defaults matching block.json / save.js.
		$effect              = ! empty( $attrs['effect'] ) ? $attrs['effect'] : 'slide';
		$style_variation     = ! empty( $attrs['styleVariation'] ) ? $attrs['styleVariation'] : 'classic';
		$show_arrows         = isset( $attrs['showArrows'] ) ? (bool) $attrs['showArrows'] : true;
		$show_dots           = isset( $attrs['showDots'] ) ? (bool) $attrs['showDots'] : true;
		$centered_slides     = ! empty( $attrs['centeredSlides'] );
		$free_mode           = ! empty( $attrs['freeMode'] );
		$scroll_driven       = ! empty( $attrs['scrollDriven'] );
		$aria_label          = ! empty( $attrs['ariaLabel'] ) ? $attrs['ariaLabel'] : 'Image slider';

		// Force single slide for fade/zoom effects.
		$is_single_effect         = in_array( $effect, array( 'fade', 'zoom' ), true );
		$slides_per_view          = $is_single_effect ? 1 : ( isset( $attrs['slidesPerView'] ) ? (int) $attrs['slidesPerView'] : 1 );
		$slides_per_view_tablet   = $is_single_effect ? 1 : ( isset( $attrs['slidesPerViewTablet'] ) ? (int) $attrs['slidesPerViewTablet'] : 1 );
		$slides_per_view_mobile   = $is_single_effect ? 1 : ( isset( $attrs['slidesPerViewMobile'] ) ? (int) $attrs['slidesPerViewMobile'] : 1 );

		// Build CSS classes.
		$classes   = array( 'wp-block-designsetgo-slider', 'dsgo-slider' );
		$classes[] = 'dsgo-slider--' . sanitize_html_class( $style_variation );
		$classes[] = 'dsgo-slider--effect-' . sanitize_html_class( $effect );
		if ( $show_arrows ) {
			$classes[] = 'dsgo-slider--has-arrows';
		}
		if ( $show_dots ) {
			$classes[] = 'dsgo-slider--has-dots';
		}
		if ( $centered_slides ) {
			$classes[] = 'dsgo-slider--centered';
		}
		if ( $free_mode ) {
			$classes[] = 'dsgo-slider--free-mode';
		}
		if ( $scroll_driven ) {
			$classes[] = 'dsgo-slider--scroll-driven';
		}

		// Build CSS custom properties.
		$aspect_ratio       = ! empty( $attrs['aspectRatio'] ) ? $attrs['aspectRatio'] : '16/9';
		$gap                = ! empty( $attrs['gap'] ) ? $attrs['gap'] : '20px';
		$transition_dur     = ! empty( $attrs['transitionDuration'] ) ? $attrs['transitionDuration'] : '0.5s';

		$style_parts   = array();
		$style_parts[] = '--dsgo-slider-aspect-ratio:' . esc_attr( $aspect_ratio );
		$style_parts[] = '--dsgo-slider-gap:' . esc_attr( $gap );
		$style_parts[] = '--dsgo-slider-transition:' . esc_attr( $transition_dur );
		$style_parts[] = '--dsgo-slider-slides-per-view:' . (int) $slides_per_view;
		$style_parts[] = '--dsgo-slider-slides-per-view-tablet:' . (int) $slides_per_view_tablet;
		$style_parts[] = '--dsgo-slider-slides-per-view-mobile:' . (int) $slides_per_view_mobile;

		if ( ! empty( $attrs['height'] ) ) {
			$style_parts[] = '--dsgo-slider-height:' . esc_attr( $attrs['height'] );
		}
		if ( ! empty( $attrs['arrowSize'] ) ) {
			$style_parts[] = '--dsgo-slider-arrow-size:' . esc_attr( $attrs['arrowSize'] );
		}
		if ( ! empty( $attrs['arrowPadding'] ) ) {
			$style_parts[] = '--dsgo-slider-arrow-padding:' . esc_attr( $attrs['arrowPadding'] );
		}

		// Build data-* attributes matching save.js output.
		$data_attrs = array(
			'data-slides-per-view'        => (string) $slides_per_view,
			'data-slides-per-view-tablet'  => (string) $slides_per_view_tablet,
			'data-slides-per-view-mobile'  => (string) $slides_per_view_mobile,
			'data-use-aspect-ratio'        => ! empty( $attrs['useAspectRatio'] ) ? 'true' : 'false',
			'data-show-arrows'             => $show_arrows ? 'true' : 'false',
			'data-show-dots'               => $show_dots ? 'true' : 'false',
			'data-arrow-style'             => ! empty( $attrs['arrowStyle'] ) ? $attrs['arrowStyle'] : 'default',
			'data-arrow-position'          => ! empty( $attrs['arrowPosition'] ) ? $attrs['arrowPosition'] : 'sides',
			'data-arrow-vertical-position' => ! empty( $attrs['arrowVerticalPosition'] ) ? $attrs['arrowVerticalPosition'] : 'center',
			'data-dot-style'               => ! empty( $attrs['dotStyle'] ) ? $attrs['dotStyle'] : 'default',
			'data-dot-position'            => ! empty( $attrs['dotPosition'] ) ? $attrs['dotPosition'] : 'inside',
			'data-effect'                  => $effect,
			'data-transition-duration'     => $transition_dur,
			'data-transition-easing'       => ! empty( $attrs['transitionEasing'] ) ? $attrs['transitionEasing'] : 'ease-in-out',
			'data-autoplay'                => ! empty( $attrs['autoplay'] ) ? 'true' : 'false',
			'data-autoplay-interval'       => isset( $attrs['autoplayInterval'] ) ? (string) (int) $attrs['autoplayInterval'] : '3000',
			'data-pause-on-hover'          => isset( $attrs['pauseOnHover'] ) && ! $attrs['pauseOnHover'] ? 'false' : 'true',
			'data-pause-on-interaction'    => isset( $attrs['pauseOnInteraction'] ) && ! $attrs['pauseOnInteraction'] ? 'false' : 'true',
			'data-loop'                    => isset( $attrs['loop'] ) && ! $attrs['loop'] ? 'false' : 'true',
			'data-draggable'               => isset( $attrs['draggable'] ) && ! $attrs['draggable'] ? 'false' : 'true',
			'data-swipeable'               => isset( $attrs['swipeable'] ) && ! $attrs['swipeable'] ? 'false' : 'true',
			'data-free-mode'               => $free_mode ? 'true' : 'false',
			'data-centered-slides'         => $centered_slides ? 'true' : 'false',
			'data-mobile-breakpoint'       => isset( $attrs['mobileBreakpoint'] ) ? (string) (int) $attrs['mobileBreakpoint'] : '768',
			'data-tablet-breakpoint'       => isset( $attrs['tabletBreakpoint'] ) ? (string) (int) $attrs['tabletBreakpoint'] : '1024',
			'data-active-slide'            => isset( $attrs['activeSlide'] ) ? (string) (int) $attrs['activeSlide'] : '0',
		);

		// Conditional data attrs (only present when scrollDriven is true).
		if ( $scroll_driven ) {
			$data_attrs['data-scroll-driven']       = 'true';
			$data_attrs['data-scroll-driven-speed']  = isset( $attrs['scrollDrivenSpeed'] ) ? (string) (int) $attrs['scrollDrivenSpeed'] : '1';
		}

		// Assemble opening tag.
		$data_str = '';
		foreach ( $data_attrs as $key => $val ) {
			$data_str .= ' ' . $key . '="' . esc_attr( $val ) . '"';
		}

		$open  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( implode( '; ', $style_parts ) ) . '"' . $data_str;
		$open .= ' role="region" aria-label="' . esc_attr( $aria_label ) . '" aria-roledescription="slider">';
		$open .= '<div class="dsgo-slider__viewport"><div class="dsgo-slider__track">';

		return $open;
	}

	/**
	 * Handle flip card -> designsetgo/flip-card.
	 *
	 * Save.js renders: <div class="dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-3d dsgo-flip-card--vertical">
	 *   <div class="dsgo-flip-card__container">...front + back...</div>
	 * </div>
	 *
	 * @param \DOMElement $element   The flip-card container element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_flip_card( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/flip-card' );
		$inner_blocks = array();

		// Expect two children: front and back.
		$children = array();
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				$children[] = $child;
			}
		}

		if ( count( $children ) >= 1 ) {
			$front_inner    = $converter->process_children( $children[0] );
			$front_html     = '<div class="wp-block-designsetgo-flip-card-front dsgo-flip-card__face dsgo-flip-card__front">';
			$inner_blocks[] = Dsgo_Handlers::build_container_block( 'designsetgo/flip-card-front', array(), $front_inner, $front_html, '</div>' );
		}
		if ( count( $children ) >= 2 ) {
			$back_inner     = $converter->process_children( $children[1] );
			$back_html      = '<div class="wp-block-designsetgo-flip-card-back dsgo-flip-card__face dsgo-flip-card__back">';
			$inner_blocks[] = Dsgo_Handlers::build_container_block( 'designsetgo/flip-card-back', array(), $back_inner, $back_html, '</div>' );
		}

		$open  = '<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-3d dsgo-flip-card--vertical" data-flip-trigger="hover" data-flip-effect="3d" data-flip-direction="vertical" style="width: 100%;"><div class="dsgo-flip-card__container">';
		$close = '</div></div>';

		return Dsgo_Handlers::build_container_block( 'designsetgo/flip-card', $attrs, $inner_blocks, $open, $close );
	}
}
