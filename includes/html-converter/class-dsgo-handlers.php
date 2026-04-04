<?php
/**
 * DesignSetGo Block Handlers for HTML-to-Block Converter.
 *
 * Maps HTML elements to DesignSetGo blocks when preferred over core blocks.
 * These handlers are registered when the prefer_dsgo option is true (default).
 *
 * IMPORTANT: The innerHTML and innerContent produced by each handler must
 * match the output of the corresponding block's save.js in order to pass
 * the block editor's validation. The markup here mirrors the default save
 * output for each block.
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
 * DesignSetGo Handlers class.
 *
 * Overrides core handler mappings with DesignSetGo equivalents.
 * Registration order matters: these are registered after core handlers,
 * overriding tag handlers for elements like section, div, form, etc.
 */
class Dsgo_Handlers {

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
	 * Register DesignSetGo handlers with the element handler registry.
	 *
	 * Overrides core handlers for tags where DesignSetGo blocks are preferred.
	 * Also registers class-based handlers for structural disambiguation.
	 *
	 * @param Element_Handler $registry Handler registry.
	 */
	public function register( Element_Handler $registry ): void {
		// Tag handlers (override core equivalents).
		$registry->register_tag_handler( 'section', array( $this, 'handle_section' ) );
		$registry->register_tag_handler( 'details', array( $this, 'handle_accordion' ) );
		$registry->register_tag_handler( 'article', array( $this, 'handle_card' ) );
		$registry->register_tag_handler( 'button', array( $this, 'handle_button' ) );
		$registry->register_tag_handler( 'form', array( $this, 'handle_form' ) );
		$registry->register_tag_handler( 'input', array( $this, 'handle_form_field' ) );
		$registry->register_tag_handler( 'textarea', array( $this, 'handle_form_field' ) );
		$registry->register_tag_handler( 'select', array( $this, 'handle_form_field' ) );
		$registry->register_tag_handler( 'nav', array( $this, 'handle_nav' ) );

		// Class-based handlers for divs.
		$registry->register_class_handler( 'columns', array( $this, 'handle_row' ) );
		$registry->register_class_handler( 'row', array( $this, 'handle_row' ) );
		$registry->register_class_handler( 'grid', array( $this, 'handle_grid' ) );
		$registry->register_class_handler( 'card', array( $this, 'handle_card' ) );
		$registry->register_class_handler( 'section', array( $this, 'handle_section' ) );
		$registry->register_class_handler( 'button', array( $this, 'handle_button_wrapper' ) );
		$registry->register_class_handler( 'accordion', array( $this, 'handle_accordion_wrapper' ) );
		$registry->register_class_handler( 'tabs', array( $this, 'handle_tabs' ) );
		$registry->register_class_handler( 'timeline', array( $this, 'handle_timeline' ) );
		$registry->register_class_handler( 'modal', array( $this, 'handle_modal' ) );
		$registry->register_class_handler( 'slider', array( $this, 'handle_slider' ) );
		$registry->register_class_handler( 'flip-card', array( $this, 'handle_flip_card' ) );
		$registry->register_class_handler( 'progress-bar', array( $this, 'handle_progress_bar' ) );
		$registry->register_class_handler( 'counter', array( $this, 'handle_counter' ) );
		$registry->register_class_handler( 'divider', array( $this, 'handle_divider' ) );
		$registry->register_class_handler( 'icon', array( $this, 'handle_icon' ) );
		$registry->register_class_handler( 'pill', array( $this, 'handle_pill' ) );
		$registry->register_class_handler( 'fifty-fifty', array( $this, 'handle_fifty_fifty' ) );
		$registry->register_class_handler( 'reveal', array( $this, 'handle_reveal' ) );
	}

	/**
	 * Handle section element -> designsetgo/section.
	 *
	 * save.js renders: <div class="dsgo-stack"><div class="dsgo-stack__inner">...inner blocks...</div></div>
	 *
	 * @param \DOMElement $element   The section element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_section( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/section' );
		$inner_blocks = $converter->process_children( $element );

		$open  = '<div class="wp-block-designsetgo-section dsgo-stack"><div class="dsgo-stack__inner">';
		$close = '</div></div>';

		return $this->build_container_block( 'designsetgo/section', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle row/columns layout -> designsetgo/row.
	 *
	 * save.js renders: <div class="dsgo-flex"><div class="dsgo-flex__inner">...inner blocks...</div></div>
	 *
	 * @param \DOMElement $element   The element with columns/row class.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_row( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/row' );
		$inner_blocks = $converter->process_children( $element );

		$open  = '<div class="wp-block-designsetgo-row dsgo-flex"><div class="dsgo-flex__inner">';
		$close = '</div></div>';

		return $this->build_container_block( 'designsetgo/row', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle grid layout -> designsetgo/grid.
	 *
	 * save.js renders: <div class="dsgo-grid dsgo-grid-cols-3 ..."><div class="dsgo-grid__inner">...</div></div>
	 *
	 * @param \DOMElement $element   The element with grid class.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_grid( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/grid' );
		$inner_blocks = $converter->process_children( $element );

		$cols  = $attrs['desktopColumns'] ?? 3;
		$tcols = $attrs['tabletColumns'] ?? $cols;
		$mcols = $attrs['mobileColumns'] ?? $tcols;
		$open  = '<div class="wp-block-designsetgo-grid dsgo-grid dsgo-grid-cols-' . (int) $cols . ' dsgo-grid-cols-tablet-' . (int) $tcols . ' dsgo-grid-cols-mobile-' . (int) $mcols . '"><div class="dsgo-grid__inner">';
		$close = '</div></div>';

		return $this->build_container_block( 'designsetgo/grid', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle card element -> designsetgo/card.
	 *
	 * save.js renders: <div class="dsgo-card dsgo-card--default dsgo-card--style-minimal"><div class="dsgo-card__inner"><div class="dsgo-card__content">...</div></div></div>
	 *
	 * @param \DOMElement $element   The article or .card element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_card( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/card' );
		$inner_blocks = $converter->process_children( $element );

		$layout = $attrs['layoutPreset'] ?? 'default';
		$style  = $attrs['visualStyle'] ?? 'minimal';
		$open   = '<div class="wp-block-designsetgo-card dsgo-card dsgo-card--' . esc_attr( $layout ) . ' dsgo-card--style-' . esc_attr( $style ) . '"><div class="dsgo-card__inner"><div class="dsgo-card__content">';
		$close  = '</div></div></div>';

		return $this->build_container_block( 'designsetgo/card', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle details/summary -> designsetgo/accordion + accordion-item.
	 *
	 * save.js accordion: <div class="dsgo-accordion"><div class="dsgo-accordion__items">...</div></div>
	 * save.js accordion-item: <div class="dsgo-accordion-item dsgo-accordion-item--closed">...complex header + panel...</div>
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

		$item_attrs = array( 'title' => $title );

		// Build accordion-item with save.js markup.
		$unique_id  = wp_unique_id( 'accordion-item-' );
		$header_id  = 'header-' . $unique_id;
		$panel_id   = 'panel-' . $unique_id;
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

		$accordion_item = $this->build_container_block(
			'designsetgo/accordion-item',
			$item_attrs,
			$item_inner_blocks,
			$item_open,
			$item_close
		);

		// Build accordion wrapper.
		$acc_open  = '<div class="wp-block-designsetgo-accordion dsgo-accordion" data-allow-multiple="false" data-icon-style="chevron"><div class="dsgo-accordion__items">';
		$acc_close = '</div></div>';

		return $this->build_container_block(
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

		return $this->build_container_block( 'designsetgo/accordion', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle button element -> designsetgo/icon-button.
	 *
	 * save.js renders: <button class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button" ...>
	 *   <span class="dsgo-icon-button__text">text</span>
	 * </button>
	 *
	 * @param \DOMElement $element   The button element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_button( \DOMElement $element, Converter $converter ): array {
		$attrs         = $this->attribute_mapper->map_attributes( $element, 'designsetgo/icon-button' );
		$text          = wp_strip_all_tags( $converter->get_inner_html( $element ) );
		$attrs['text'] = $text;

		$href = $element->getAttribute( 'href' );
		if ( ! empty( $href ) ) {
			$attrs['url'] = $href;
		}

		// Determine wrapper tag based on url presence.
		$has_url = ! empty( $attrs['url'] );
		$tag     = $has_url ? 'a' : 'button';
		$classes = 'wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button';

		$text_escaped = esc_html( $text );
		$inner_html   = '<' . $tag . ' class="' . $classes . '"';

		if ( $has_url ) {
			$inner_html .= ' href="' . esc_url( $attrs['url'] ) . '"';
		} else {
			$inner_html .= ' type="button"';
		}

		$inner_html .= ' style="display: inline-flex; align-items: center; justify-content: center; gap: 0; width: auto; flex-direction: row;">';
		$inner_html .= '<span class="dsgo-icon-button__text">' . $text_escaped . '</span>';
		$inner_html .= '</' . $tag . '>';

		return array(
			'blockName'    => 'designsetgo/icon-button',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $inner_html,
			'innerContent' => array( $inner_html ),
		);
	}

	/**
	 * Handle a.button or div.button -> designsetgo/icon-button.
	 *
	 * @param \DOMElement $element   The button wrapper element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_button_wrapper( \DOMElement $element, Converter $converter ): array {
		return $this->handle_button( $element, $converter );
	}

	/**
	 * Handle form element -> designsetgo/form-builder.
	 *
	 * save.js renders: <div class="dsgo-form-builder"><form class="dsgo-form" method="post" novalidate>
	 *   <div class="dsgo-form__fields">...inner blocks...</div>
	 *   <input type="hidden" name="dsg_form_id" value="..." />
	 * </form></div>
	 *
	 * @param \DOMElement $element   The form element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_form( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/form-builder' );
		$inner_blocks = $converter->process_children( $element );

		$form_id = wp_unique_id( 'form-' );
		$open    = '<div class="wp-block-designsetgo-form-builder dsgo-form-builder" data-form-id="' . esc_attr( $form_id ) . '">';
		$open   .= '<form class="dsgo-form" method="post" novalidate><div class="dsgo-form__fields">';
		$close   = '</div><input type="hidden" name="dsg_form_id" value="' . esc_attr( $form_id ) . '" />';
		$close  .= '<div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display: none;"></div>';
		$close  .= '</form></div>';

		return $this->build_container_block( 'designsetgo/form-builder', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle form field elements -> designsetgo/form-*-field.
	 *
	 * Maps input types to specific DesignSetGo form field blocks.
	 *
	 * @param \DOMElement $element   The input/textarea/select element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_form_field( \DOMElement $element, Converter $converter ): array {
		$tag_name   = strtolower( $element->tagName );
		$block_name = $this->get_form_field_block_name( $element, $tag_name );

		$attrs = array();

		// Extract field attributes.
		$name = $element->getAttribute( 'name' );
		if ( ! empty( $name ) ) {
			$attrs['fieldName'] = $name;
		}

		$placeholder = $element->getAttribute( 'placeholder' );
		if ( ! empty( $placeholder ) ) {
			$attrs['placeholder'] = $placeholder;
		}

		if ( $element->hasAttribute( 'required' ) ) {
			$attrs['required'] = true;
		}

		$label = $element->getAttribute( 'aria-label' );
		if ( ! empty( $label ) ) {
			$attrs['label'] = $label;
		}

		return array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Handle nav element -> designsetgo/breadcrumbs or core/group.
	 *
	 * @param \DOMElement $element   The nav element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_nav( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/breadcrumbs' );

		$aria_label = strtolower( $element->getAttribute( 'aria-label' ) );
		$class      = strtolower( $element->getAttribute( 'class' ) );

		if ( strpos( $aria_label, 'breadcrumb' ) !== false || strpos( $class, 'breadcrumb' ) !== false ) {
			return array(
				'blockName'    => 'designsetgo/breadcrumbs',
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
		}

		// Fallback to core/group with children.
		$inner_blocks = $converter->process_children( $element );
		$open         = "\n<div class=\"wp-block-group\">\n";
		$close        = "\n</div>\n";

		return $this->build_container_block( 'core/group', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle tabs container -> designsetgo/tabs.
	 *
	 * save.js renders: <div class="dsgo-tabs dsgo-tabs--vertical dsgo-tabs--default dsgo-tabs--align-left">
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

		$unique_id = wp_unique_id( 'tabs-' );
		$open      = '<div class="wp-block-designsetgo-tabs dsgo-tabs dsgo-tabs-' . esc_attr( $unique_id ) . ' dsgo-tabs--vertical dsgo-tabs--default dsgo-tabs--align-left" data-active-tab="0">';
		$open     .= '<div class="dsgo-tabs__nav" role="tablist"></div>';
		$open     .= '<div class="dsgo-tabs__panels">';
		$close     = '</div></div>';

		return $this->build_container_block( 'designsetgo/tabs', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle timeline container -> designsetgo/timeline.
	 *
	 * save.js renders: <div class="dsgo-timeline dsgo-timeline--vertical dsgo-timeline--layout-stacked dsgo-timeline--marker-circle">
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

		return $this->build_container_block( 'designsetgo/timeline', $attrs, $inner_blocks, $open, $close );
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

		return $this->build_container_block( 'designsetgo/modal', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle slider container -> designsetgo/slider.
	 *
	 * save.js renders: <div class="dsgo-slider" ...data-attrs...>
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

				$slides[] = $this->build_container_block( 'designsetgo/slide', array(), $slide_inner, $slide_open, $slide_close );
			}
		}

		$open  = '<div class="wp-block-designsetgo-slider dsgo-slider" role="region" aria-label="Image slider" aria-roledescription="slider">';
		$open .= '<div class="dsgo-slider__viewport"><div class="dsgo-slider__track">';
		$close = '</div></div></div>';

		return $this->build_container_block( 'designsetgo/slider', $attrs, $slides, $open, $close );
	}

	/**
	 * Handle flip card -> designsetgo/flip-card.
	 *
	 * save.js renders: <div class="dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-3d dsgo-flip-card--vertical">
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
			$inner_blocks[] = $this->build_container_block( 'designsetgo/flip-card-front', array(), $front_inner, $front_html, '</div>' );
		}
		if ( count( $children ) >= 2 ) {
			$back_inner     = $converter->process_children( $children[1] );
			$back_html      = '<div class="wp-block-designsetgo-flip-card-back dsgo-flip-card__face dsgo-flip-card__back">';
			$inner_blocks[] = $this->build_container_block( 'designsetgo/flip-card-back', array(), $back_inner, $back_html, '</div>' );
		}

		$open  = '<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-3d dsgo-flip-card--vertical" data-flip-trigger="hover" data-flip-effect="3d" data-flip-direction="vertical" style="width: 100%;"><div class="dsgo-flip-card__container">';
		$close = '</div></div>';

		return $this->build_container_block( 'designsetgo/flip-card', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle progress bar -> designsetgo/progress-bar.
	 *
	 * @param \DOMElement $element   The progress-bar element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_progress_bar( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/progress-bar' );

		return array(
			'blockName'    => 'designsetgo/progress-bar',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Handle counter -> designsetgo/counter.
	 *
	 * @param \DOMElement $element   The counter element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_counter( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/counter' );

		return array(
			'blockName'    => 'designsetgo/counter',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Handle divider -> designsetgo/divider.
	 *
	 * save.js renders: <div class="dsgo-divider dsgo-divider--line">
	 *   <div class="dsgo-divider__container" style="width: 100%;">
	 *     <div class="dsgo-divider__line" style="height: 1px;"></div>
	 *   </div>
	 * </div>
	 *
	 * @param \DOMElement $element   The divider element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_divider( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/divider' );

		$inner_html = '<div class="wp-block-designsetgo-divider dsgo-divider dsgo-divider--line">'
			. '<div class="dsgo-divider__container" style="width: 100%;">'
			. '<div class="dsgo-divider__line" style="height: 1px;"></div>'
			. '</div></div>';

		return array(
			'blockName'    => 'designsetgo/divider',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $inner_html,
			'innerContent' => array( $inner_html ),
		);
	}

	/**
	 * Handle icon -> designsetgo/icon.
	 *
	 * @param \DOMElement $element   The icon element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_icon( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/icon' );

		return array(
			'blockName'    => 'designsetgo/icon',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Handle pill -> designsetgo/pill.
	 *
	 * save.js renders: <div class="dsgo-pill"><span class="dsgo-pill__content">text</span></div>
	 *
	 * @param \DOMElement $element   The pill element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_pill( \DOMElement $element, Converter $converter ): array {
		$attrs         = $this->attribute_mapper->map_attributes( $element, 'designsetgo/pill' );
		$text          = wp_strip_all_tags( $converter->get_inner_html( $element ) );
		$attrs['text'] = $text;

		$text_escaped = esc_html( $text );
		$inner_html   = '<div class="wp-block-designsetgo-pill dsgo-pill"><span class="dsgo-pill__content">' . $text_escaped . '</span></div>';

		return array(
			'blockName'    => 'designsetgo/pill',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $inner_html,
			'innerContent' => array( $inner_html ),
		);
	}

	/**
	 * Handle fifty-fifty layout -> designsetgo/fifty-fifty.
	 *
	 * save.js renders: <div class="dsgo-fifty-fifty dsgo-fifty-fifty--media-left">
	 *   <div class="dsgo-fifty-fifty__media">...</div>
	 *   <div class="dsgo-fifty-fifty__content"><div class="dsgo-fifty-fifty__content-inner">...inner blocks...</div></div>
	 * </div>
	 *
	 * @param \DOMElement $element   The fifty-fifty element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_fifty_fifty( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/fifty-fifty' );
		$inner_blocks = $converter->process_children( $element );

		$open  = '<div class="wp-block-designsetgo-fifty-fifty dsgo-fifty-fifty dsgo-fifty-fifty--media-left">';
		$open .= '<div class="dsgo-fifty-fifty__media"></div>';
		$open .= '<div class="dsgo-fifty-fifty__content"><div class="dsgo-fifty-fifty__content-inner">';
		$close = '</div></div></div>';

		return $this->build_container_block( 'designsetgo/fifty-fifty', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle reveal element -> designsetgo/reveal.
	 *
	 * @param \DOMElement $element   The reveal element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_reveal( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/reveal' );
		$inner_blocks = $converter->process_children( $element );

		$open  = '<div class="wp-block-designsetgo-reveal dsgo-reveal">';
		$close = '</div>';

		return $this->build_container_block( 'designsetgo/reveal', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Determine the form field block name from an input element.
	 *
	 * @param \DOMElement $element  The form field element.
	 * @param string      $tag_name The tag name.
	 * @return string Block name.
	 */
	private function get_form_field_block_name( \DOMElement $element, string $tag_name ): string {
		if ( 'textarea' === $tag_name ) {
			return 'designsetgo/form-textarea-field';
		}

		if ( 'select' === $tag_name ) {
			return 'designsetgo/form-select-field';
		}

		// Map input types to specific field blocks.
		$type     = strtolower( $element->getAttribute( 'type' ) ?: 'text' );
		$type_map = array(
			'text'     => 'designsetgo/form-text-field',
			'email'    => 'designsetgo/form-email-field',
			'tel'      => 'designsetgo/form-phone-field',
			'number'   => 'designsetgo/form-number-field',
			'url'      => 'designsetgo/form-url-field',
			'date'     => 'designsetgo/form-date-field',
			'time'     => 'designsetgo/form-time-field',
			'checkbox' => 'designsetgo/form-checkbox-field',
			'hidden'   => 'designsetgo/form-hidden-field',
			'password' => 'designsetgo/form-text-field',
			'search'   => 'designsetgo/form-text-field',
		);

		return $type_map[ $type ] ?? 'designsetgo/form-text-field';
	}

	/**
	 * Build a container block array with inner blocks and save.js-compatible markup.
	 *
	 * The $opening and $closing parameters define the wrapper HTML that matches
	 * what the block's save.js produces. Inner blocks are represented as null
	 * entries in innerContent.
	 *
	 * @param string               $block_name   Block name.
	 * @param array<string, mixed> $attrs        Block attributes.
	 * @param array<array>         $inner_blocks Inner block arrays.
	 * @param string               $opening      Opening wrapper HTML from save.js.
	 * @param string               $closing      Closing wrapper HTML from save.js.
	 * @return array<string, mixed> Block array.
	 */
	private function build_container_block( string $block_name, array $attrs, array $inner_blocks, string $opening = '', string $closing = '' ): array {
		// Build innerContent: opening HTML, null for each inner block, closing HTML.
		$inner_content = array( $opening );
		for ( $i = 0; $i < count( $inner_blocks ); $i++ ) {
			$inner_content[] = null;
		}
		$inner_content[] = $closing;

		// innerHTML is the opening + closing without inner block content.
		$inner_html = $opening . $closing;

		return array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => $inner_html,
			'innerContent' => $inner_content,
		);
	}
}
