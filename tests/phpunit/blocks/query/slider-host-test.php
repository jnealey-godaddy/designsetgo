<?php
/**
 * Slider-as-query-item-host contract.
 *
 * Verifies:
 *  - itemTagName='none' passed to designsetgo_query_render_item skips the
 *    per-item <li>/<div> wrapper.
 *  - The registry default includes designsetgo/slider.
 *  - designsetgo_query_render_container, when it finds a slider host, feeds
 *    itemTagName='none' through to the source renderer and takes only the
 *    first inner block as the template.
 *
 * @group query-block
 */
class DesignSetGo_Slider_Host_Test extends WP_UnitTestCase {

	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $path;
	}

	public function test_slider_is_registered_as_default_host() {
		$this->load_helpers();
		$this->assertContains( 'designsetgo/slider', designsetgo_query_item_host_block_names() );
	}

	public function test_render_item_skips_wrapper_when_tag_is_none() {
		$this->load_helpers();

		$html = designsetgo_query_render_item(
			'<!-- wp:paragraph --><p>Bare</p><!-- /wp:paragraph -->',
			array( 'postId' => 0, 'postType' => 'post' ),
			'none'
		);

		// WP 6.8+ emits core/paragraph with a `wp-block-paragraph` class. Match
		// the closing tag + content so both pre- and post-6.8 output pass.
		$this->assertStringContainsString( 'Bare</p>', $html );
		$this->assertMatchesRegularExpression( '#<p[^>]*>Bare</p>#', $html );
		$this->assertStringNotContainsString( 'dsgo-query__item', $html );
		// No leading <li, <div, or <article wrapper around the paragraph.
		$this->assertStringStartsNotWith( '<li', $html );
		$this->assertStringStartsNotWith( '<article', $html );
	}

	public function test_render_item_defaults_to_li_when_tag_is_unknown() {
		$this->load_helpers();

		$html = designsetgo_query_render_item(
			'<!-- wp:paragraph --><p>Wrapped</p><!-- /wp:paragraph -->',
			array( 'postId' => 0 ),
			'garbage-value'
		);

		$this->assertStringContainsString( '<li class="dsgo-query__item">', $html );
	}

	public function test_slider_block_type_registered_with_render_callback() {
		$this->load_helpers();
		$registry = WP_Block_Type_Registry::get_instance();
		$type     = $registry->get_registered( 'designsetgo/slider' );
		$this->assertNotNull( $type, 'designsetgo/slider must be registered for query integration.' );
		$this->assertNotEmpty( $type->render_callback, 'Slider must have a render_callback (render.php).' );
		$this->assertContains( 'designsetgo/queryId', (array) $type->uses_context, 'Slider must opt into queryId context.' );
	}

	public function test_container_with_slider_host_uses_first_inner_block_only() {
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		// Emulate a parsed innerBlocks tree: designsetgo/slider containing two
		// slides. Only the first slide should be used as the item template.
		$parsed_children = array(
			array(
				'blockName'   => 'designsetgo/slider',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Slide one</p>',
						'innerContent' => array( '<p>Slide one</p>' ),
					),
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Slide two</p>',
						'innerContent' => array( '<p>Slide two</p>' ),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( '' ),
			),
		);

		$html = designsetgo_query_render_container(
			array(
				'source'   => 'posts',
				'postType' => 'post',
				'perPage'  => 3,
				'queryId'  => 'slider-host',
			),
			$parsed_children,
			1,
			'slider-host',
			'class="test-wrap"'
		);

		// Slider chrome present (slider's render.php should have emitted it).
		$this->assertStringContainsString( 'dsgo-slider__track', $html );
		// Template is innerBlocks[0] only — 3 posts → 3 renderings of Slide one.
		// Match `Slide one</p>` so an added `wp-block-paragraph` class (WP 6.8+)
		// doesn't invalidate the count.
		$this->assertSame( 3, substr_count( $html, 'Slide one</p>' ) );
		// The unused 2nd sibling never leaks into output.
		$this->assertStringNotContainsString( 'Slide two', $html );
		// Non-grid host should NOT double-wrap with <li class="dsgo-query__item">.
		$this->assertStringNotContainsString( 'dsgo-query__item', $html );
	}

	public function test_container_with_grid_host_keeps_all_inner_blocks_as_template() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		// designsetgo/query-results with TWO inner blocks — both should render
		// inside the per-item <li> wrapper.
		$parsed_children = array(
			array(
				'blockName'   => 'designsetgo/query-results',
				'attrs'       => array( 'itemTagName' => 'li' ),
				'innerBlocks' => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>First</p>',
						'innerContent' => array( '<p>First</p>' ),
					),
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Second</p>',
						'innerContent' => array( '<p>Second</p>' ),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( '' ),
			),
		);

		$html = designsetgo_query_render_container(
			array(
				'source'   => 'posts',
				'postType' => 'post',
				'perPage'  => 2,
				'queryId'  => 'grid-host',
			),
			$parsed_children,
			1,
			'grid-host',
			'class="test-wrap"'
		);

		// Grid host keeps all innerBlocks as the template, wrapped per item.
		$this->assertSame( 2, substr_count( $html, '<li class="dsgo-query__item">' ) );
		// Both paragraphs render inside each <li>. Match closing tag + content
		// so the WP 6.8+ `wp-block-paragraph` class doesn't break the assertion.
		$this->assertStringContainsString( 'First</p>', $html );
		$this->assertStringContainsString( 'Second</p>', $html );
	}
}
