<?php
/**
 * Test HTML-to-Block Converter: Attribute Mapper
 *
 * Validates that the Attribute_Mapper correctly translates inline CSS styles,
 * CSS class names, and HTML data attributes into WordPress block attribute
 * structures (style, className, anchor, etc.).
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\HTML_Converter\Attribute_Mapper;
use DesignSetGo\HTML_Converter\Converter;

/**
 * Tests for HTML-to-Block Converter attribute mapping.
 */
class HTML_Converter_Attribute_Mapper_Test extends WP_UnitTestCase {

	/**
	 * Attribute_Mapper instance.
	 *
	 * @var Attribute_Mapper
	 */
	private $mapper;

	/**
	 * Converter instance with core blocks only (used for integration assertions).
	 *
	 * @var Converter
	 */
	private $core_converter;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->mapper         = new Attribute_Mapper();
		$this->core_converter = new Converter( array( 'prefer_dsgo' => false ) );
	}

	// ------------------------------------------------------------------
	// Style parsing
	// ------------------------------------------------------------------

	/**
	 * Test inline style mapping to block style.
	 */
	public function test_style_mapping() {
		$style = $this->mapper->parse_style_to_block_style( 'color: red; font-size: 18px; padding: 10px 20px' );

		$this->assertSame( 'red', $style['color']['text'] );
		$this->assertSame( '18px', $style['typography']['fontSize'] );
		$this->assertSame( '10px', $style['spacing']['padding']['top'] );
		$this->assertSame( '20px', $style['spacing']['padding']['right'] );
	}

	/**
	 * Test background-color style mapping.
	 */
	public function test_background_color_style_mapping() {
		$style = $this->mapper->parse_style_to_block_style( 'background-color: #ff0000' );

		$this->assertSame( '#ff0000', $style['color']['background'] );
	}

	/**
	 * Test padding shorthand mapping (4 values).
	 */
	public function test_padding_shorthand_four_values() {
		$style = $this->mapper->parse_style_to_block_style( 'padding: 1px 2px 3px 4px' );

		$this->assertSame( '1px', $style['spacing']['padding']['top'] );
		$this->assertSame( '2px', $style['spacing']['padding']['right'] );
		$this->assertSame( '3px', $style['spacing']['padding']['bottom'] );
		$this->assertSame( '4px', $style['spacing']['padding']['left'] );
	}

	/**
	 * Test margin shorthand mapping (1 value).
	 */
	public function test_margin_shorthand_one_value() {
		$style = $this->mapper->parse_style_to_block_style( 'margin: 20px' );

		$this->assertSame( '20px', $style['spacing']['margin']['top'] );
		$this->assertSame( '20px', $style['spacing']['margin']['right'] );
		$this->assertSame( '20px', $style['spacing']['margin']['bottom'] );
		$this->assertSame( '20px', $style['spacing']['margin']['left'] );
	}

	/**
	 * Test border-radius style mapping.
	 */
	public function test_border_radius_mapping() {
		$style = $this->mapper->parse_style_to_block_style( 'border-radius: 8px' );

		$this->assertSame( '8px', $style['border']['radius'] );
	}

	// ------------------------------------------------------------------
	// Class mapping
	// ------------------------------------------------------------------

	/**
	 * Test CSS class to attribute mapping.
	 */
	public function test_class_attribute_mapping() {
		$attrs = $this->mapper->parse_classes_to_attributes( 'has-text-align-center has-large-font-size alignfull', 'core/paragraph' );

		$this->assertSame( 'center', $attrs['textAlign'] );
		$this->assertSame( 'large', $attrs['fontSize'] );
		$this->assertSame( 'full', $attrs['align'] );
	}

	// ------------------------------------------------------------------
	// Data / id attribute mapping (integration via Converter)
	// ------------------------------------------------------------------

	/**
	 * Test id attribute maps to anchor.
	 */
	public function test_id_maps_to_anchor() {
		$blocks = $this->core_converter->convert_to_blocks( '<p id="my-section">Content</p>' );

		$this->assertSame( 'my-section', $blocks[0]['attrs']['anchor'] );
	}

	/**
	 * Test WordPress marker class has-text-color does not produce invalid textColor.
	 */
	public function test_marker_class_has_text_color_ignored() {
		$mapper = new \DesignSetGo\HTML_Converter\Attribute_Mapper();
		$attrs  = $mapper->parse_classes_to_attributes( 'has-text-color has-contrast-color', 'core/paragraph' );

		// has-text-color is a marker class — should NOT produce textColor = 'text'.
		$this->assertNotSame( 'text', $attrs['textColor'] ?? null );
		// has-contrast-color IS a real preset — should produce textColor = 'contrast'.
		$this->assertSame( 'contrast', $attrs['textColor'] );
	}

	/**
	 * Test real color preset class maps correctly.
	 */
	public function test_real_color_preset_maps_correctly() {
		$mapper = new \DesignSetGo\HTML_Converter\Attribute_Mapper();
		$attrs  = $mapper->parse_classes_to_attributes( 'has-primary-background-color has-white-color', 'core/paragraph' );

		$this->assertSame( 'primary', $attrs['backgroundColor'] );
		$this->assertSame( 'white', $attrs['textColor'] );
	}
}
