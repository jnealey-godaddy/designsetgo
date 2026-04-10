<?php
/**
 * Test HTML-to-Block Converter
 *
 * Main test class covering class existence, basic converter infrastructure
 * (empty input, text nodes, handler registry), the wrap_in_section option,
 * the prefer_dsgo integration flag, complex multi-block conversion, and
 * block serialization round-trips.
 *
 * Detailed handler tests are split into focused sibling files:
 * - html-converter-core-handlers-test.php
 * - html-converter-dsgo-handlers-test.php
 * - html-converter-attribute-mapper-test.php
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\HTML_Converter\Converter;
use DesignSetGo\HTML_Converter\Attribute_Mapper;
use DesignSetGo\HTML_Converter\Element_Handler;

/**
 * Tests for HTML-to-Block Converter infrastructure and integration.
 */
class Test_HTML_Converter extends WP_UnitTestCase {

	/**
	 * Converter instance with DesignSetGo blocks preferred.
	 *
	 * @var Converter
	 */
	private $converter;

	/**
	 * Converter instance with core blocks only.
	 *
	 * @var Converter
	 */
	private $core_converter;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->converter      = new Converter( array( 'prefer_dsgo' => true ) );
		$this->core_converter = new Converter( array( 'prefer_dsgo' => false ) );
	}

	// ------------------------------------------------------------------
	// Class existence tests
	// ------------------------------------------------------------------

	/**
	 * Test that Converter class exists.
	 */
	public function test_converter_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\HTML_Converter\Converter' ) );
	}

	/**
	 * Test that Element_Handler class exists.
	 */
	public function test_element_handler_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\HTML_Converter\Element_Handler' ) );
	}

	/**
	 * Test that Attribute_Mapper class exists.
	 */
	public function test_attribute_mapper_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\HTML_Converter\Attribute_Mapper' ) );
	}

	/**
	 * Test that Core_Handlers class exists.
	 */
	public function test_core_handlers_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\HTML_Converter\Core_Handlers' ) );
	}

	/**
	 * Test that Dsgo_Handlers class exists.
	 */
	public function test_dsgo_handlers_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\HTML_Converter\Dsgo_Handlers' ) );
	}

	/**
	 * Test that Insert_HTML ability class exists.
	 */
	public function test_insert_html_ability_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\Abilities\Inserters\Insert_HTML' ) );
	}

	// ------------------------------------------------------------------
	// Empty / edge-case input
	// ------------------------------------------------------------------

	/**
	 * Test empty input returns empty array.
	 */
	public function test_empty_html_returns_empty() {
		$blocks = $this->converter->convert_to_blocks( '' );
		$this->assertEmpty( $blocks );
	}

	/**
	 * Test whitespace-only input returns empty array.
	 */
	public function test_whitespace_only_returns_empty() {
		$blocks = $this->converter->convert_to_blocks( '   ' );
		$this->assertEmpty( $blocks );
	}

	// ------------------------------------------------------------------
	// Element_Handler registry tests
	// ------------------------------------------------------------------

	/**
	 * Test tag handler registration and retrieval.
	 */
	public function test_tag_handler_registration() {
		$registry = new Element_Handler();

		$handler = function () {
			return array( 'blockName' => 'test/block' );
		};

		$registry->register_tag_handler( 'custom-tag', $handler );

		$this->assertTrue( $registry->has_tag_handler( 'custom-tag' ) );
		$this->assertFalse( $registry->has_tag_handler( 'nonexistent' ) );
	}

	/**
	 * Test class handler registration.
	 */
	public function test_class_handler_registration() {
		$registry = new Element_Handler();

		$handler = function () {
			return array( 'blockName' => 'test/block' );
		};

		$registry->register_class_handler( 'my-class', $handler );

		$this->assertTrue( $registry->has_class_handler( 'my-class' ) );
		$this->assertFalse( $registry->has_class_handler( 'other-class' ) );
	}

	// ------------------------------------------------------------------
	// Integration: prefer_dsgo option
	// ------------------------------------------------------------------

	/**
	 * Test that prefer_dsgo=false uses core blocks for sections.
	 */
	public function test_prefer_dsgo_false_uses_core_group() {
		$blocks = $this->core_converter->convert_to_blocks( '<section><p>Content</p></section>' );

		// Without dsgo preference, section should not map to designsetgo/section.
		// It should fall back to a tag handler or default.
		$block_names = wp_list_pluck( $blocks, 'blockName' );
		$this->assertNotContains( 'designsetgo/section', $block_names );
	}

	// ------------------------------------------------------------------
	// Integration: wrap_in_section option
	// ------------------------------------------------------------------

	/**
	 * Test wrap_in_section option wraps top-level blocks.
	 */
	public function test_wrap_in_section_option() {
		$converter = new Converter( array(
			'prefer_dsgo'     => true,
			'wrap_in_section' => true,
		) );

		$blocks = $converter->convert_to_blocks( '<p>Hello</p><p>World</p>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/section', $blocks[0]['blockName'] );
		$this->assertCount( 2, $blocks[0]['innerBlocks'] );
	}

	// ------------------------------------------------------------------
	// Integration: complex multi-block HTML
	// ------------------------------------------------------------------

	/**
	 * Test conversion of a complex HTML page with multiple elements.
	 */
	public function test_complex_html_conversion() {
		$html = '
			<section>
				<h2>Welcome</h2>
				<p>This is the intro paragraph.</p>
				<div class="columns">
					<div><p>Column 1</p></div>
					<div><p>Column 2</p></div>
				</div>
			</section>
		';

		$blocks = $this->converter->convert_to_blocks( $html );

		// Top-level should be a section.
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/section', $blocks[0]['blockName'] );

		// Inner blocks: h2, p, row.
		$inner = $blocks[0]['innerBlocks'];
		$this->assertGreaterThanOrEqual( 3, count( $inner ) );

		$inner_block_names = wp_list_pluck( $inner, 'blockName' );
		$this->assertContains( 'core/heading', $inner_block_names );
		$this->assertContains( 'core/paragraph', $inner_block_names );
		$this->assertContains( 'designsetgo/row', $inner_block_names );
	}

	// ------------------------------------------------------------------
	// Serialization: convert() produces valid block markup
	// ------------------------------------------------------------------

	/**
	 * Test that convert() produces parseable block markup.
	 */
	public function test_convert_produces_valid_block_markup() {
		$html   = '<h2>Title</h2><p>Content</p>';
		$markup = $this->core_converter->convert( $html );

		// The markup should contain block comments.
		$this->assertStringContainsString( '<!-- wp:heading', $markup );
		$this->assertStringContainsString( '<!-- wp:paragraph', $markup );

		// Should be re-parseable.
		$reparsed = parse_blocks( $markup );
		$named    = array_filter( $reparsed, function ( $b ) {
			return ! empty( $b['blockName'] );
		} );
		$this->assertCount( 2, $named );
	}

	/**
	 * Test serialization round-trip for a section block.
	 */
	public function test_section_serialization_roundtrip() {
		$html   = '<section><p>Hello</p></section>';
		$markup = $this->converter->convert( $html );

		$this->assertStringContainsString( '<!-- wp:designsetgo/section', $markup );
		$this->assertStringContainsString( '<!-- wp:paragraph', $markup );
		$this->assertStringContainsString( 'dsgo-stack', $markup );

		// Re-parse and verify structure.
		$reparsed = parse_blocks( $markup );
		$named    = array_filter( $reparsed, function ( $b ) {
			return ! empty( $b['blockName'] );
		} );
		$this->assertCount( 1, $named );

		$section = array_values( $named )[0];
		$this->assertSame( 'designsetgo/section', $section['blockName'] );
		$this->assertNotEmpty( $section['innerBlocks'] );
	}
}
