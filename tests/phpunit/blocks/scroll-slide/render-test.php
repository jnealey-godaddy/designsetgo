<?php
/**
 * Tests for the scroll-slide block server render.
 *
 * Regression guard for the PrefixAllGlobals refactor that wrapped render.php in
 * designsetgo_render_scroll_slide(). Standalone (authored) slides pass through
 * unchanged; inside a query iteration (an item context on
 * $GLOBALS['designsetgo_parent_stack']) the slide is enriched with the iterated
 * post's title via WP_HTML_Tag_Processor.
 *
 * @group scroll-slide
 */

/**
 * @group scroll-slide
 */
class DesignSetGo_Scroll_Slide_Render_Test extends WP_UnitTestCase {

	/**
	 * Include the built render template and capture its echoed output.
	 *
	 * @param array       $attributes Block attributes.
	 * @param string      $content    Pre-rendered save.js output.
	 * @param object|null $block      Block instance.
	 * @return string Rendered HTML.
	 */
	private function render( array $attributes, $content = '', $block = null ) {
		$path = DESIGNSETGO_PATH . 'build/blocks/scroll-slide/render.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render templates are served from build/.' );

		$previous_block                     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'designsetgo/scroll-slide',
			'attrs'     => array(),
		);

		ob_start();
		$returned = include $path;
		$html     = ob_get_clean();

		WP_Block_Supports::$block_to_render = $previous_block;

		return is_string( $returned ) ? $returned : $html;
	}

	public function test_authored_mode_passes_content_through_unchanged() {
		$content = '<div class="dsgo-scroll-slide">authored slide</div>';

		// No parent stack -> authored mode.
		unset( $GLOBALS['designsetgo_parent_stack'] );

		$html = $this->render( array(), $content );

		$this->assertSame( $content, $html );
	}

	public function test_query_iteration_injects_post_title_as_nav_heading() {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$this->markTestSkipped( 'WP_HTML_Tag_Processor not available.' );
		}

		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Spotlight Story',
				'post_status' => 'publish',
			)
		);

		$content = '<div class="dsgo-scroll-slide">slide body</div>';

		$GLOBALS['designsetgo_parent_stack'] = array(
			array( 'postId' => $post_id ),
		);

		$html = $this->render( array(), $content, new stdClass() );

		unset( $GLOBALS['designsetgo_parent_stack'] );

		$this->assertStringContainsString( 'data-dsgo-nav-heading="Spotlight Story"', $html );
	}
}
