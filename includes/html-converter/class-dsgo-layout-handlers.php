<?php
/**
 * DesignSetGo Layout Block Handlers for HTML-to-Block Converter.
 *
 * Handles layout-related HTML elements and maps them to DesignSetGo layout blocks.
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
 * DesignSetGo Layout Handlers class.
 *
 * Handles layout blocks: section, row, grid, fifty-fifty, reveal.
 */
class Dsgo_Layout_Handlers {

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
	 * Register layout handlers with the element handler registry.
	 *
	 * @param Element_Handler $registry Handler registry.
	 */
	public function register( Element_Handler $registry ): void {
		$registry->register_tag_handler( 'section', array( $this, 'handle_section' ) );
		$registry->register_class_handler( 'columns', array( $this, 'handle_row' ) );
		$registry->register_class_handler( 'row', array( $this, 'handle_row' ) );
		$registry->register_class_handler( 'grid', array( $this, 'handle_grid' ) );
		$registry->register_class_handler( 'section', array( $this, 'handle_section' ) );
		$registry->register_class_handler( 'fifty-fifty', array( $this, 'handle_fifty_fifty' ) );
		$registry->register_class_handler( 'reveal', array( $this, 'handle_reveal' ) );
	}

	/**
	 * Handle section element -> designsetgo/section.
	 *
	 * Save.js renders: <div class="dsgo-stack"><div class="dsgo-stack__inner">...inner blocks...</div></div>
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

		return Dsgo_Handlers::build_container_block( 'designsetgo/section', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle row/columns layout -> designsetgo/row.
	 *
	 * Save.js renders: <div class="dsgo-flex"><div class="dsgo-flex__inner">...inner blocks...</div></div>
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

		return Dsgo_Handlers::build_container_block( 'designsetgo/row', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle grid layout -> designsetgo/grid.
	 *
	 * Save.js renders: <div class="dsgo-grid dsgo-grid-cols-3 ..."><div class="dsgo-grid__inner">...</div></div>
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

		return Dsgo_Handlers::build_container_block( 'designsetgo/grid', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle fifty-fifty layout -> designsetgo/fifty-fifty.
	 *
	 * Save.js renders: <div class="dsgo-fifty-fifty dsgo-fifty-fifty--media-left">
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

		return Dsgo_Handlers::build_container_block( 'designsetgo/fifty-fifty', $attrs, $inner_blocks, $open, $close );
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

		return Dsgo_Handlers::build_container_block( 'designsetgo/reveal', $attrs, $inner_blocks, $open, $close );
	}
}
