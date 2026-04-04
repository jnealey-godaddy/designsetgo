<?php
/**
 * Test HTML-to-Block Converter
 *
 * Validates that the converter produces correct block arrays with
 * innerHTML/innerContent matching each block's save.js output.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\HTML_Converter\Converter;
use DesignSetGo\HTML_Converter\Attribute_Mapper;
use DesignSetGo\HTML_Converter\Element_Handler;

/**
 * Tests for HTML-to-Block Converter.
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
	// Core block conversions
	// ------------------------------------------------------------------

	/**
	 * Test paragraph conversion.
	 */
	public function test_paragraph_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<p>Hello world</p>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertStringContainsString( '<p>Hello world</p>', $blocks[0]['innerHTML'] );
	}

	/**
	 * Test heading conversion preserves level.
	 */
	public function test_heading_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<h2>Title</h2>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/heading', $blocks[0]['blockName'] );
		$this->assertSame( 2, $blocks[0]['attrs']['level'] );
		$this->assertStringContainsString( '<h2', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'Title', $blocks[0]['innerHTML'] );
	}

	/**
	 * Test all heading levels (h1-h6).
	 */
	public function test_heading_levels() {
		for ( $level = 1; $level <= 6; $level++ ) {
			$blocks = $this->core_converter->convert_to_blocks( "<h{$level}>Heading {$level}</h{$level}>" );

			$this->assertCount( 1, $blocks, "Failed for h{$level}" );
			$this->assertSame( 'core/heading', $blocks[0]['blockName'], "Failed for h{$level}" );
			$this->assertSame( $level, $blocks[0]['attrs']['level'], "Wrong level for h{$level}" );
		}
	}

	/**
	 * Test unordered list conversion.
	 */
	public function test_unordered_list_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<ul><li>Item 1</li><li>Item 2</li></ul>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/list', $blocks[0]['blockName'] );
		$this->assertArrayNotHasKey( 'ordered', $blocks[0]['attrs'] );
		$this->assertCount( 2, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/list-item', $blocks[0]['innerBlocks'][0]['blockName'] );
		$this->assertStringContainsString( 'Item 1', $blocks[0]['innerBlocks'][0]['innerHTML'] );
	}

	/**
	 * Test ordered list conversion.
	 */
	public function test_ordered_list_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<ol><li>First</li><li>Second</li></ol>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/list', $blocks[0]['blockName'] );
		$this->assertTrue( $blocks[0]['attrs']['ordered'] );
		$this->assertStringContainsString( '<ol>', $blocks[0]['innerHTML'] );
	}

	/**
	 * Test blockquote conversion.
	 */
	public function test_blockquote_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<blockquote><p>A quote</p></blockquote>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/quote', $blocks[0]['blockName'] );
		$this->assertNotEmpty( $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	/**
	 * Test code block conversion.
	 */
	public function test_code_block_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<pre><code class="language-php">echo "hi";</code></pre>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/code', $blocks[0]['blockName'] );
		$this->assertSame( 'php', $blocks[0]['attrs']['language'] );
		$this->assertStringContainsString( 'echo "hi";', $blocks[0]['innerHTML'] );
	}

	/**
	 * Test image conversion.
	 */
	public function test_image_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<img src="https://example.com/photo.jpg" alt="A photo" width="800" />' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/image', $blocks[0]['blockName'] );
		$this->assertSame( 'https://example.com/photo.jpg', $blocks[0]['attrs']['url'] );
		$this->assertSame( 'A photo', $blocks[0]['attrs']['alt'] );
		$this->assertSame( '800', $blocks[0]['attrs']['width'] );
	}

	/**
	 * Test image with no src is skipped.
	 */
	public function test_image_no_src_returns_null() {
		$blocks = $this->core_converter->convert_to_blocks( '<img alt="no source" />' );
		// Should produce no blocks (null from handler, filtered out).
		$this->assertEmpty( $blocks );
	}

	/**
	 * Test table conversion.
	 */
	public function test_table_conversion() {
		$html   = '<table><tr><td>Cell</td></tr></table>';
		$blocks = $this->core_converter->convert_to_blocks( $html );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/table', $blocks[0]['blockName'] );
		$this->assertStringContainsString( '<table>', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'Cell', $blocks[0]['innerHTML'] );
	}

	/**
	 * Test separator (hr) conversion.
	 */
	public function test_separator_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<hr />' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/separator', $blocks[0]['blockName'] );
		$this->assertStringContainsString( '<hr', $blocks[0]['innerHTML'] );
	}

	/**
	 * Test video conversion.
	 */
	public function test_video_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<video src="https://example.com/video.mp4"></video>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/video', $blocks[0]['blockName'] );
		$this->assertSame( 'https://example.com/video.mp4', $blocks[0]['attrs']['src'] );
	}

	/**
	 * Test audio conversion.
	 */
	public function test_audio_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<audio src="https://example.com/audio.mp3"></audio>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/audio', $blocks[0]['blockName'] );
		$this->assertSame( 'https://example.com/audio.mp3', $blocks[0]['attrs']['src'] );
	}

	/**
	 * Test generic div becomes core/group.
	 */
	public function test_div_becomes_group() {
		$blocks = $this->core_converter->convert_to_blocks( '<div><p>Inner content</p></div>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertNotEmpty( $blocks[0]['innerBlocks'] );
	}

	/**
	 * Test script/style tags are stripped.
	 */
	public function test_script_style_stripped() {
		$html   = '<p>Before</p><script>alert("xss")</script><style>.bad{}</style><p>After</p>';
		$blocks = $this->core_converter->convert_to_blocks( $html );

		$block_names = wp_list_pluck( $blocks, 'blockName' );
		$this->assertNotContains( 'core/html', $block_names, 'script/style should be stripped' );

		// Should have 2 paragraphs.
		$paragraph_count = count( array_filter( $block_names, function ( $name ) {
			return 'core/paragraph' === $name;
		} ) );
		$this->assertSame( 2, $paragraph_count );
	}

	/**
	 * Test inline elements are wrapped in paragraphs at block level.
	 */
	public function test_inline_elements_wrapped_in_paragraph() {
		$blocks = $this->core_converter->convert_to_blocks( '<strong>Bold text</strong>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertStringContainsString( '<strong>Bold text</strong>', $blocks[0]['innerHTML'] );
	}

	// ------------------------------------------------------------------
	// DesignSetGo block conversions - block names
	// ------------------------------------------------------------------

	/**
	 * Test section tag maps to designsetgo/section.
	 */
	public function test_section_maps_to_dsgo_section() {
		$blocks = $this->converter->convert_to_blocks( '<section><p>Content</p></section>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/section', $blocks[0]['blockName'] );
		$this->assertNotEmpty( $blocks[0]['innerBlocks'] );
	}

	/**
	 * Test div.columns maps to designsetgo/row.
	 */
	public function test_div_columns_maps_to_dsgo_row() {
		$blocks = $this->converter->convert_to_blocks( '<div class="columns"><div>Col 1</div><div>Col 2</div></div>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/row', $blocks[0]['blockName'] );
	}

	/**
	 * Test div.grid maps to designsetgo/grid.
	 */
	public function test_div_grid_maps_to_dsgo_grid() {
		$blocks = $this->converter->convert_to_blocks( '<div class="grid"><div>Item 1</div></div>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/grid', $blocks[0]['blockName'] );
	}

	/**
	 * Test article maps to designsetgo/card.
	 */
	public function test_article_maps_to_dsgo_card() {
		$blocks = $this->converter->convert_to_blocks( '<article><h3>Title</h3><p>Body</p></article>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/card', $blocks[0]['blockName'] );
		$this->assertNotEmpty( $blocks[0]['innerBlocks'] );
	}

	/**
	 * Test details maps to designsetgo/accordion.
	 */
	public function test_details_maps_to_dsgo_accordion() {
		$html = '<details><summary>FAQ Question</summary><p>Answer here.</p></details>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/accordion', $blocks[0]['blockName'] );
		$this->assertNotEmpty( $blocks[0]['innerBlocks'] );

		// The inner block should be an accordion-item.
		$item = $blocks[0]['innerBlocks'][0];
		$this->assertSame( 'designsetgo/accordion-item', $item['blockName'] );
		$this->assertSame( 'FAQ Question', $item['attrs']['title'] );
	}

	/**
	 * Test button maps to designsetgo/icon-button.
	 */
	public function test_button_maps_to_dsgo_icon_button() {
		$blocks = $this->converter->convert_to_blocks( '<button>Click Me</button>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/icon-button', $blocks[0]['blockName'] );
		$this->assertSame( 'Click Me', $blocks[0]['attrs']['text'] );
	}

	/**
	 * Test form maps to designsetgo/form-builder.
	 */
	public function test_form_maps_to_dsgo_form_builder() {
		$html = '<form><input type="text" name="name" /><input type="email" name="email" /></form>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/form-builder', $blocks[0]['blockName'] );
		$this->assertNotEmpty( $blocks[0]['innerBlocks'] );
	}

	/**
	 * Test input type mapping to form field blocks.
	 */
	public function test_form_field_type_mapping() {
		$type_to_block = array(
			'text'     => 'designsetgo/form-text-field',
			'email'    => 'designsetgo/form-email-field',
			'tel'      => 'designsetgo/form-phone-field',
			'number'   => 'designsetgo/form-number-field',
			'url'      => 'designsetgo/form-url-field',
			'date'     => 'designsetgo/form-date-field',
			'time'     => 'designsetgo/form-time-field',
			'checkbox' => 'designsetgo/form-checkbox-field',
			'hidden'   => 'designsetgo/form-hidden-field',
		);

		foreach ( $type_to_block as $type => $expected_block ) {
			$html   = '<form><input type="' . $type . '" name="field" /></form>';
			$blocks = $this->converter->convert_to_blocks( $html );

			$form_block  = $blocks[0];
			$field_block = $form_block['innerBlocks'][0];

			$this->assertSame( $expected_block, $field_block['blockName'], "Failed for input type={$type}" );
		}
	}

	/**
	 * Test textarea maps to form-textarea-field.
	 */
	public function test_textarea_maps_to_form_textarea_field() {
		$html   = '<form><textarea name="message"></textarea></form>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$field = $blocks[0]['innerBlocks'][0];
		$this->assertSame( 'designsetgo/form-textarea-field', $field['blockName'] );
	}

	/**
	 * Test select maps to form-select-field.
	 */
	public function test_select_maps_to_form_select_field() {
		$html   = '<form><select name="choice"><option>A</option></select></form>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$field = $blocks[0]['innerBlocks'][0];
		$this->assertSame( 'designsetgo/form-select-field', $field['blockName'] );
	}

	/**
	 * Test nav with breadcrumb class maps to designsetgo/breadcrumbs.
	 */
	public function test_nav_breadcrumb_maps_to_dsgo_breadcrumbs() {
		$blocks = $this->converter->convert_to_blocks( '<nav class="breadcrumb"><a href="/">Home</a></nav>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/breadcrumbs', $blocks[0]['blockName'] );
	}

	/**
	 * Test div.slider maps to designsetgo/slider with slides.
	 */
	public function test_div_slider_maps_to_dsgo_slider() {
		$html = '<div class="slider"><div>Slide 1</div><div>Slide 2</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/slider', $blocks[0]['blockName'] );
		$this->assertCount( 2, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'designsetgo/slide', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	/**
	 * Test div.flip-card maps to designsetgo/flip-card with front and back.
	 */
	public function test_div_flip_card_maps_to_dsgo_flip_card() {
		$html = '<div class="flip-card"><div>Front</div><div>Back</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/flip-card', $blocks[0]['blockName'] );
		$this->assertCount( 2, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'designsetgo/flip-card-front', $blocks[0]['innerBlocks'][0]['blockName'] );
		$this->assertSame( 'designsetgo/flip-card-back', $blocks[0]['innerBlocks'][1]['blockName'] );
	}

	/**
	 * Test div.pill maps to designsetgo/pill.
	 */
	public function test_div_pill_maps_to_dsgo_pill() {
		$blocks = $this->converter->convert_to_blocks( '<div class="pill">Beta</div>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/pill', $blocks[0]['blockName'] );
		$this->assertSame( 'Beta', $blocks[0]['attrs']['text'] );
	}

	/**
	 * Test div.divider maps to designsetgo/divider.
	 */
	public function test_div_divider_maps_to_dsgo_divider() {
		$blocks = $this->converter->convert_to_blocks( '<div class="divider"></div>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/divider', $blocks[0]['blockName'] );
	}

	// ------------------------------------------------------------------
	// DesignSetGo block save.js markup validation
	// ------------------------------------------------------------------

	/**
	 * Test section innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-stack"><div class="dsgo-stack__inner">...</div></div>
	 */
	public function test_section_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<section><p>Content</p></section>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-stack', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-stack__inner', $block['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-designsetgo-section', $block['innerHTML'] );

		// innerContent should have opening + null for each inner block + closing.
		$this->assertSame( 3, count( $block['innerContent'] ), 'Section should have 3 innerContent entries (open + null + close)' );
		$this->assertNull( $block['innerContent'][1] );
		$this->assertStringContainsString( 'dsgo-stack__inner', $block['innerContent'][0] );
	}

	/**
	 * Test row innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-flex"><div class="dsgo-flex__inner">...</div></div>
	 */
	public function test_row_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<div class="row"><div>Col</div></div>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-flex', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-flex__inner', $block['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-designsetgo-row', $block['innerHTML'] );
	}

	/**
	 * Test grid innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-grid dsgo-grid-cols-3 ..."><div class="dsgo-grid__inner">...</div></div>
	 */
	public function test_grid_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<div class="grid"><div>Item</div></div>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-grid', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-grid__inner', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-grid-cols-', $block['innerHTML'] );
	}

	/**
	 * Test card innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-card dsgo-card--default dsgo-card--style-minimal">
	 *   <div class="dsgo-card__inner"><div class="dsgo-card__content">...</div></div>
	 * </div>
	 */
	public function test_card_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<article><p>Card content</p></article>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-card', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-card__inner', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-card__content', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-card--default', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-card--style-minimal', $block['innerHTML'] );
	}

	/**
	 * Test accordion innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-accordion"><div class="dsgo-accordion__items">...</div></div>
	 */
	public function test_accordion_innerhtml_matches_save_js() {
		$html   = '<details><summary>Question</summary><p>Answer</p></details>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-accordion', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-accordion__items', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-allow-multiple', $block['innerHTML'] );

		// Accordion item should have proper markup.
		$item = $block['innerBlocks'][0];
		$this->assertStringContainsString( 'dsgo-accordion-item', $item['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-accordion-item__trigger', $item['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-accordion-item__panel', $item['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-accordion-item__content', $item['innerHTML'] );
		$this->assertStringContainsString( 'Question', $item['innerHTML'] );
	}

	/**
	 * Test icon-button innerHTML matches save.js output.
	 *
	 * save.js: <button class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button" ...>
	 *   <span class="dsgo-icon-button__text">text</span>
	 * </button>
	 */
	public function test_icon_button_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<button>Click Me</button>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-icon-button', $block['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-button', $block['innerHTML'] );
		$this->assertStringContainsString( 'wp-element-button', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-icon-button__text', $block['innerHTML'] );
		$this->assertStringContainsString( 'Click Me', $block['innerHTML'] );
		$this->assertStringContainsString( '<button', $block['innerHTML'] );
	}

	/**
	 * Test pill innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-pill"><span class="dsgo-pill__content">text</span></div>
	 */
	public function test_pill_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<div class="pill">Beta</div>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-pill', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-pill__content', $block['innerHTML'] );
		$this->assertStringContainsString( 'Beta', $block['innerHTML'] );
	}

	/**
	 * Test divider innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-divider dsgo-divider--line">
	 *   <div class="dsgo-divider__container"><div class="dsgo-divider__line"></div></div>
	 * </div>
	 */
	public function test_divider_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<div class="divider"></div>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-divider', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-divider--line', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-divider__container', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-divider__line', $block['innerHTML'] );
	}

	/**
	 * Test slider innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-slider" ...><div class="dsgo-slider__viewport"><div class="dsgo-slider__track">...</div></div></div>
	 */
	public function test_slider_innerhtml_matches_save_js() {
		$html   = '<div class="slider"><div>Slide 1</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-slider', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-slider__viewport', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-slider__track', $block['innerHTML'] );

		// Slide inner block.
		$slide = $block['innerBlocks'][0];
		$this->assertStringContainsString( 'dsgo-slide', $slide['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-slide__content', $slide['innerHTML'] );
	}

	/**
	 * Test flip-card innerHTML matches save.js output.
	 */
	public function test_flip_card_innerhtml_matches_save_js() {
		$html   = '<div class="flip-card"><div>Front</div><div>Back</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-flip-card', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-flip-card__container', $block['innerHTML'] );

		$front = $block['innerBlocks'][0];
		$this->assertStringContainsString( 'dsgo-flip-card__face', $front['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-flip-card__front', $front['innerHTML'] );

		$back = $block['innerBlocks'][1];
		$this->assertStringContainsString( 'dsgo-flip-card__back', $back['innerHTML'] );
	}

	/**
	 * Test fifty-fifty innerHTML matches save.js output.
	 */
	public function test_fifty_fifty_innerhtml_matches_save_js() {
		$html   = '<div class="fifty-fifty"><p>Content</p></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-fifty-fifty', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-fifty-fifty__media', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-fifty-fifty__content', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-fifty-fifty__content-inner', $block['innerHTML'] );
	}

	/**
	 * Test timeline innerHTML matches save.js output.
	 */
	public function test_timeline_innerhtml_matches_save_js() {
		$html   = '<div class="timeline"><div>Event 1</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-timeline', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-timeline__line', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-timeline__items', $block['innerHTML'] );
	}

	/**
	 * Test tabs innerHTML matches save.js output.
	 */
	public function test_tabs_innerhtml_matches_save_js() {
		$html   = '<div class="tabs"><div>Tab 1 content</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-tabs', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-tabs__nav', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-tabs__panels', $block['innerHTML'] );
		$this->assertStringContainsString( 'role="tablist"', $block['innerHTML'] );
	}

	// ------------------------------------------------------------------
	// Attribute Mapper tests
	// ------------------------------------------------------------------

	/**
	 * Test inline style mapping to block style.
	 */
	public function test_style_mapping() {
		$mapper = new Attribute_Mapper();
		$style  = $mapper->parse_style_to_block_style( 'color: red; font-size: 18px; padding: 10px 20px' );

		$this->assertSame( 'red', $style['color']['text'] );
		$this->assertSame( '18px', $style['typography']['fontSize'] );
		$this->assertSame( '10px', $style['spacing']['padding']['top'] );
		$this->assertSame( '20px', $style['spacing']['padding']['right'] );
	}

	/**
	 * Test background-color style mapping.
	 */
	public function test_background_color_style_mapping() {
		$mapper = new Attribute_Mapper();
		$style  = $mapper->parse_style_to_block_style( 'background-color: #ff0000' );

		$this->assertSame( '#ff0000', $style['color']['background'] );
	}

	/**
	 * Test CSS class to attribute mapping.
	 */
	public function test_class_attribute_mapping() {
		$mapper = new Attribute_Mapper();
		$attrs  = $mapper->parse_classes_to_attributes( 'has-text-align-center has-large-font-size alignfull', 'core/paragraph' );

		$this->assertSame( 'center', $attrs['textAlign'] );
		$this->assertSame( 'large', $attrs['fontSize'] );
		$this->assertSame( 'full', $attrs['align'] );
	}

	/**
	 * Test padding shorthand mapping (4 values).
	 */
	public function test_padding_shorthand_four_values() {
		$mapper = new Attribute_Mapper();
		$style  = $mapper->parse_style_to_block_style( 'padding: 1px 2px 3px 4px' );

		$this->assertSame( '1px', $style['spacing']['padding']['top'] );
		$this->assertSame( '2px', $style['spacing']['padding']['right'] );
		$this->assertSame( '3px', $style['spacing']['padding']['bottom'] );
		$this->assertSame( '4px', $style['spacing']['padding']['left'] );
	}

	/**
	 * Test margin shorthand mapping (1 value).
	 */
	public function test_margin_shorthand_one_value() {
		$mapper = new Attribute_Mapper();
		$style  = $mapper->parse_style_to_block_style( 'margin: 20px' );

		$this->assertSame( '20px', $style['spacing']['margin']['top'] );
		$this->assertSame( '20px', $style['spacing']['margin']['right'] );
		$this->assertSame( '20px', $style['spacing']['margin']['bottom'] );
		$this->assertSame( '20px', $style['spacing']['margin']['left'] );
	}

	/**
	 * Test border-radius style mapping.
	 */
	public function test_border_radius_mapping() {
		$mapper = new Attribute_Mapper();
		$style  = $mapper->parse_style_to_block_style( 'border-radius: 8px' );

		$this->assertSame( '8px', $style['border']['radius'] );
	}

	/**
	 * Test id attribute maps to anchor.
	 */
	public function test_id_maps_to_anchor() {
		$blocks = $this->core_converter->convert_to_blocks( '<p id="my-section">Content</p>' );

		$this->assertSame( 'my-section', $blocks[0]['attrs']['anchor'] );
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
