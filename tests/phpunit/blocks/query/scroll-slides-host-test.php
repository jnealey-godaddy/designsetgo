<?php
/**
 * Scroll-slides-as-query-item-host contract.
 *
 * @group query-block
 */
class DesignSetGo_Scroll_Slides_Host_Test extends WP_UnitTestCase {

	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $path;
	}

	public function test_scroll_slides_registered_as_default_host() {
		$this->load_helpers();
		$this->assertContains(
			'designsetgo/scroll-slides',
			designsetgo_query_item_host_block_names()
		);
	}

	public function test_block_type_registered_with_render_callback_and_query_context() {
		$this->load_helpers();
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'designsetgo/scroll-slides' );
		$this->assertNotNull( $type );
		$this->assertNotEmpty( $type->render_callback );
		$this->assertContains( 'designsetgo/queryId', (array) $type->uses_context );
	}

	public function test_container_with_scroll_slides_host_iterates_and_chrome_renders() {
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$parsed_children = array(
			array(
				'blockName'   => 'designsetgo/scroll-slides',
				'attrs'       => array( 'minHeight' => '80vh' ),
				'innerBlocks' => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Panel template</p>',
						'innerContent' => array( '<p>Panel template</p>' ),
					),
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Stray second child</p>',
						'innerContent' => array( '<p>Stray second child</p>' ),
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
				'queryId'  => 'ss-host',
			),
			$parsed_children,
			1,
			'ss-host',
			'class="test-wrap"'
		);

		$this->assertStringContainsString( 'dsgo-scroll-slides__panels', $html );
		$this->assertStringContainsString( 'dsgo-scroll-slides__inner', $html );
		// Chrome receives minHeight via data attr.
		$this->assertStringContainsString( 'data-dsgo-min-height="80vh"', $html );
		// Exactly one template block is used — 3 iterations of Panel template.
		$this->assertSame( 3, substr_count( $html, '<p>Panel template</p>' ) );
		// Non-template sibling never leaks.
		$this->assertStringNotContainsString( 'Stray second child', $html );
		// Non-grid host: no <li class="dsgo-query__item"> wrappers around items.
		$this->assertStringNotContainsString( 'dsgo-query__item', $html );
	}
}
