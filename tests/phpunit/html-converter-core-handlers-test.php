<?php
/**
 * Test HTML-to-Block Converter: Core Handlers
 *
 * Validates that core HTML elements (p, h1-h6, ul, ol, blockquote, pre/code,
 * img, figure, table, hr, video, audio, div) are converted to the correct
 * WordPress core block arrays.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\HTML_Converter\Converter;

/**
 * Tests for HTML-to-Block Converter core element handlers.
 */
class HTML_Converter_Core_Handlers_Test extends WP_UnitTestCase {

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

		$this->core_converter = new Converter( array( 'prefer_dsgo' => false ) );
	}

	// ------------------------------------------------------------------
	// Paragraph
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

	// ------------------------------------------------------------------
	// Headings
	// ------------------------------------------------------------------

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

	// ------------------------------------------------------------------
	// Lists
	// ------------------------------------------------------------------

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

	// ------------------------------------------------------------------
	// Blockquote
	// ------------------------------------------------------------------

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

	// ------------------------------------------------------------------
	// Code
	// ------------------------------------------------------------------

	/**
	 * Test code block conversion.
	 */
	public function test_code_block_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<pre><code class="language-php">echo "hi";</code></pre>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/code', $blocks[0]['blockName'] );
		$this->assertSame( 'php', $blocks[0]['attrs']['language'] );
		$this->assertStringContainsString( 'echo &quot;hi&quot;;', $blocks[0]['innerHTML'] );
	}

	// ------------------------------------------------------------------
	// Image / Figure
	// ------------------------------------------------------------------

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

	// ------------------------------------------------------------------
	// Table
	// ------------------------------------------------------------------

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

	// ------------------------------------------------------------------
	// Separator
	// ------------------------------------------------------------------

	/**
	 * Test separator (hr) conversion.
	 */
	public function test_separator_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<hr />' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/separator', $blocks[0]['blockName'] );
		$this->assertStringContainsString( '<hr', $blocks[0]['innerHTML'] );
	}

	// ------------------------------------------------------------------
	// Video
	// ------------------------------------------------------------------

	/**
	 * Test video conversion.
	 */
	public function test_video_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<video src="https://example.com/video.mp4"></video>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/video', $blocks[0]['blockName'] );
		$this->assertSame( 'https://example.com/video.mp4', $blocks[0]['attrs']['src'] );
	}

	// ------------------------------------------------------------------
	// Audio
	// ------------------------------------------------------------------

	/**
	 * Test audio conversion.
	 */
	public function test_audio_conversion() {
		$blocks = $this->core_converter->convert_to_blocks( '<audio src="https://example.com/audio.mp3"></audio>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/audio', $blocks[0]['blockName'] );
		$this->assertSame( 'https://example.com/audio.mp3', $blocks[0]['attrs']['src'] );
	}

	// ------------------------------------------------------------------
	// Div
	// ------------------------------------------------------------------

	/**
	 * Test generic div becomes core/group.
	 */
	public function test_div_becomes_group() {
		$blocks = $this->core_converter->convert_to_blocks( '<div><p>Inner content</p></div>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertNotEmpty( $blocks[0]['innerBlocks'] );
	}

	// ------------------------------------------------------------------
	// Script / Style stripping
	// ------------------------------------------------------------------

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

	// ------------------------------------------------------------------
	// Inline element wrapping
	// ------------------------------------------------------------------

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
	// Sanitization tests
	// ------------------------------------------------------------------

	/**
	 * Test code block content is escaped (no raw script tags).
	 */
	public function test_code_block_strips_script_tags() {
		$html   = '<pre><code><script>alert(1)</script></code></pre>';
		$blocks = $this->core_converter->convert_to_blocks( $html );

		$this->assertCount( 1, $blocks );
		$this->assertStringNotContainsString( '<script>', $blocks[0]['innerHTML'] );
		$this->assertStringNotContainsString( '</script>', $blocks[0]['innerHTML'] );
	}

	/**
	 * Test table HTML is sanitized (no event handlers).
	 */
	public function test_table_strips_event_handlers() {
		$html   = '<table onclick="evil()"><tr><td>cell</td></tr></table>';
		$blocks = $this->core_converter->convert_to_blocks( $html );

		$this->assertCount( 1, $blocks );
		$this->assertStringNotContainsString( 'onclick', $blocks[0]['innerHTML'] );
	}

	/**
	 * Test text buffer sanitizes inline event handlers.
	 */
	public function test_text_buffer_strips_event_handlers() {
		$html   = '<p>Hello</p><strong onclick="evil()">text</strong><p>World</p>';
		$blocks = $this->core_converter->convert_to_blocks( $html );

		// The strong tag between two block elements should be flushed as a paragraph.
		$inline_block = null;
		foreach ( $blocks as $block ) {
			if ( strpos( $block['innerHTML'], 'text' ) !== false && strpos( $block['innerHTML'], 'Hello' ) === false ) {
				$inline_block = $block;
				break;
			}
		}

		$this->assertNotNull( $inline_block, 'Inline text block should exist' );
		$this->assertStringNotContainsString( 'onclick', $inline_block['innerHTML'] );
	}
}
