<?php
/**
 * DesignSetGo Block Handlers for HTML-to-Block Converter.
 *
 * Maps HTML elements to DesignSetGo blocks when preferred over core blocks.
 * These handlers are registered when the prefer_dsgo option is true (default).
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
	 * @param \DOMElement $element   The section element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_section( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/section' );
		$inner_blocks = $converter->process_children( $element );

		return $this->build_container_block( 'designsetgo/section', $attrs, $inner_blocks );
	}

	/**
	 * Handle row/columns layout -> designsetgo/row.
	 *
	 * @param \DOMElement $element   The element with columns/row class.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_row( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/row' );
		$inner_blocks = $converter->process_children( $element );

		return $this->build_container_block( 'designsetgo/row', $attrs, $inner_blocks );
	}

	/**
	 * Handle grid layout -> designsetgo/grid.
	 *
	 * @param \DOMElement $element   The element with grid class.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_grid( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/grid' );
		$inner_blocks = $converter->process_children( $element );

		return $this->build_container_block( 'designsetgo/grid', $attrs, $inner_blocks );
	}

	/**
	 * Handle card element -> designsetgo/card.
	 *
	 * @param \DOMElement $element   The article or .card element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_card( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/card' );
		$inner_blocks = $converter->process_children( $element );

		return $this->build_container_block( 'designsetgo/card', $attrs, $inner_blocks );
	}

	/**
	 * Handle details/summary -> designsetgo/accordion + accordion-item.
	 *
	 * @param \DOMElement $element   The details element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_accordion( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/accordion' );

		// Extract summary as title, remaining content as body.
		$summary = $element->getElementsByTagName( 'summary' )->item( 0 );
		$title   = $summary ? $converter->get_inner_html( $summary ) : '';

		// Process non-summary children as inner blocks of the accordion item.
		$inner_blocks = array();
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement && 'summary' !== strtolower( $child->tagName ) ) {
				$block = $converter->process_node( $child );
				if ( $block ) {
					$inner_blocks[] = $block;
				}
			}
		}

		$item_attrs = array( 'title' => wp_strip_all_tags( $title ) );

		$accordion_item = $this->build_container_block(
			'designsetgo/accordion-item',
			$item_attrs,
			$inner_blocks
		);

		return $this->build_container_block(
			'designsetgo/accordion',
			$attrs,
			array( $accordion_item )
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

		return $this->build_container_block( 'designsetgo/accordion', $attrs, $inner_blocks );
	}

	/**
	 * Handle button element -> designsetgo/icon-button.
	 *
	 * @param \DOMElement $element   The button element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_button( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/icon-button' );
		$attrs['text'] = wp_strip_all_tags( $converter->get_inner_html( $element ) );

		$href = $element->getAttribute( 'href' );
		if ( ! empty( $href ) ) {
			$attrs['url'] = $href;
		}

		return array(
			'blockName'    => 'designsetgo/icon-button',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
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
	 * @param \DOMElement $element   The form element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_form( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/form-builder' );
		$inner_blocks = $converter->process_children( $element );

		return $this->build_container_block( 'designsetgo/form-builder', $attrs, $inner_blocks );
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

		// Extract label from associated label element or aria-label.
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
	 * Handle nav element -> designsetgo/breadcrumbs or core/navigation.
	 *
	 * @param \DOMElement $element   The nav element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_nav( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/breadcrumbs' );

		// Check if it looks like breadcrumbs (aria-label or class hint).
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
		return $this->build_container_block( 'core/group', $attrs, $inner_blocks );
	}

	/**
	 * Handle tabs container -> designsetgo/tabs.
	 *
	 * @param \DOMElement $element   The tabs container element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_tabs( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/tabs' );
		$inner_blocks = $converter->process_children( $element );

		return $this->build_container_block( 'designsetgo/tabs', $attrs, $inner_blocks );
	}

	/**
	 * Handle timeline container -> designsetgo/timeline.
	 *
	 * @param \DOMElement $element   The timeline container element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_timeline( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/timeline' );
		$inner_blocks = $converter->process_children( $element );

		return $this->build_container_block( 'designsetgo/timeline', $attrs, $inner_blocks );
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

		return $this->build_container_block( 'designsetgo/modal', $attrs, $inner_blocks );
	}

	/**
	 * Handle slider container -> designsetgo/slider.
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
				$slides[]    = $this->build_container_block( 'designsetgo/slide', array(), $slide_inner );
			}
		}

		return $this->build_container_block( 'designsetgo/slider', $attrs, $slides );
	}

	/**
	 * Handle flip card -> designsetgo/flip-card.
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
			$front_inner  = $converter->process_children( $children[0] );
			$inner_blocks[] = $this->build_container_block( 'designsetgo/flip-card-front', array(), $front_inner );
		}
		if ( count( $children ) >= 2 ) {
			$back_inner   = $converter->process_children( $children[1] );
			$inner_blocks[] = $this->build_container_block( 'designsetgo/flip-card-back', array(), $back_inner );
		}

		return $this->build_container_block( 'designsetgo/flip-card', $attrs, $inner_blocks );
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
	 * @param \DOMElement $element   The divider element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_divider( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/divider' );

		return array(
			'blockName'    => 'designsetgo/divider',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
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
	 * @param \DOMElement $element   The pill element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_pill( \DOMElement $element, Converter $converter ): array {
		$attrs         = $this->attribute_mapper->map_attributes( $element, 'designsetgo/pill' );
		$attrs['text'] = wp_strip_all_tags( $converter->get_inner_html( $element ) );

		return array(
			'blockName'    => 'designsetgo/pill',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Handle fifty-fifty layout -> designsetgo/fifty-fifty.
	 *
	 * @param \DOMElement $element   The fifty-fifty element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_fifty_fifty( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/fifty-fifty' );
		$inner_blocks = $converter->process_children( $element );

		return $this->build_container_block( 'designsetgo/fifty-fifty', $attrs, $inner_blocks );
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

		return $this->build_container_block( 'designsetgo/reveal', $attrs, $inner_blocks );
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
	 * Build a container block array with inner blocks.
	 *
	 * Generic helper for blocks that wrap child content.
	 *
	 * @param string               $block_name   Block name.
	 * @param array<string, mixed> $attrs        Block attributes.
	 * @param array<array>         $inner_blocks Inner block arrays.
	 * @return array<string, mixed> Block array.
	 */
	private function build_container_block( string $block_name, array $attrs, array $inner_blocks ): array {
		$inner_content = array( '' );
		for ( $i = 0; $i < count( $inner_blocks ); $i++ ) {
			$inner_content[] = null;
		}
		$inner_content[] = '';

		return array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '',
			'innerContent' => $inner_content,
		);
	}
}
