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
 * Registration hub that delegates to sub-handler classes.
 * Also handles remaining small blocks: card, button, pill, icon, counter,
 * progress-bar, and divider.
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
	 * Delegates to sub-handler classes for layout, interactive, and form blocks.
	 *
	 * @param Element_Handler $registry Handler registry.
	 */
	public function register( Element_Handler $registry ): void {
		// Delegate to sub-handler classes.
		$layout_handlers      = new Dsgo_Layout_Handlers( $this->converter, $this->attribute_mapper );
		$interactive_handlers = new Dsgo_Interactive_Handlers( $this->converter, $this->attribute_mapper );
		$form_handlers        = new Dsgo_Form_Handlers( $this->converter, $this->attribute_mapper );

		$layout_handlers->register( $registry );
		$interactive_handlers->register( $registry );
		$form_handlers->register( $registry );

		// Tag handlers for remaining blocks.
		$registry->register_tag_handler( 'article', array( $this, 'handle_card' ) );
		$registry->register_tag_handler( 'button', array( $this, 'handle_button' ) );

		// Class-based handlers for remaining blocks.
		$registry->register_class_handler( 'card', array( $this, 'handle_card' ) );
		$registry->register_class_handler( 'button', array( $this, 'handle_button_wrapper' ) );
		$registry->register_class_handler( 'progress-bar', array( $this, 'handle_progress_bar' ) );
		$registry->register_class_handler( 'counter', array( $this, 'handle_counter' ) );
		$registry->register_class_handler( 'divider', array( $this, 'handle_divider' ) );
		$registry->register_class_handler( 'icon', array( $this, 'handle_icon' ) );
		$registry->register_class_handler( 'pill', array( $this, 'handle_pill' ) );
	}

	/**
	 * Handle card element -> designsetgo/card.
	 *
	 * Save.js renders: <div class="dsgo-card dsgo-card--default dsgo-card--style-minimal"><div class="dsgo-card__inner"><div class="dsgo-card__content">...</div></div></div>
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

		return self::build_container_block( 'designsetgo/card', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle button element -> designsetgo/icon-button.
	 *
	 * Save.js renders: <button class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button" ...>
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
	 * Handle pill -> designsetgo/pill.
	 *
	 * Save.js renders: <div class="dsgo-pill"><span class="dsgo-pill__content">text</span></div>
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
	 * Handle divider -> designsetgo/divider.
	 *
	 * Save.js renders: <div class="dsgo-divider dsgo-divider--line">
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
	 * Build a container block array with inner blocks and save.js-compatible markup.
	 *
	 * The $opening and $closing parameters define the wrapper HTML that matches
	 * what the block's save.js produces. Inner blocks are represented as null
	 * entries in innerContent.
	 *
	 * Made public static so sub-handler classes can call Dsgo_Handlers::build_container_block().
	 *
	 * @param string               $block_name   Block name.
	 * @param array<string, mixed> $attrs        Block attributes.
	 * @param array<array>         $inner_blocks Inner block arrays.
	 * @param string               $opening      Opening wrapper HTML from save.js.
	 * @param string               $closing      Closing wrapper HTML from save.js.
	 * @return array<string, mixed> Block array.
	 */
	public static function build_container_block( string $block_name, array $attrs, array $inner_blocks, string $opening = '', string $closing = '' ): array {
		return array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => $opening . $closing,
			'innerContent' => Converter::build_inner_content_for_blocks( $inner_blocks, $opening, $closing ),
		);
	}
}
