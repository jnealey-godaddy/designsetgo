<?php
/**
 * Tests for the scroll-slides block server render.
 *
 * Regression guard for the PrefixAllGlobals refactor that wrapped render.php in
 * designsetgo_render_scroll_slides(). Authored mode passes the saved markup
 * through unchanged; query-bound mode rebuilds chrome and splices in items
 * stashed by the parent query container (where the refactored locals live).
 *
 * @group scroll-slides
 */

/**
 * @group scroll-slides
 */
class DesignSetGo_Scroll_Slides_Render_Test extends WP_UnitTestCase {

	/**
	 * Include the built render template and capture its echoed output.
	 *
	 * @param array       $attributes Block attributes.
	 * @param string      $content    Pre-rendered save.js output.
	 * @param object|null $block      Block instance (carries context).
	 * @return string Rendered HTML.
	 */
	private function render( array $attributes, $content = '', $block = null ) {
		$path = DESIGNSETGO_PATH . 'build/blocks/scroll-slides/render.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render templates are served from build/.' );

		$previous_block                     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'designsetgo/scroll-slides',
			'attrs'     => array(),
		);

		ob_start();
		$returned = include $path;
		$html     = ob_get_clean();

		WP_Block_Supports::$block_to_render = $previous_block;

		return is_string( $returned ) ? $returned : $html;
	}

	public function test_authored_mode_passes_content_through_unchanged() {
		$content = '<div class="dsgo-scroll-slides">authored markup</div>';

		$html = $this->render( array(), $content );

		$this->assertSame( $content, $html );
	}

	public function test_query_mode_builds_chrome_and_injects_items() {
		$query_id = 'slides42';

		$block          = new stdClass();
		$block->context = array( 'designsetgo/queryId' => $query_id );

		$GLOBALS['designsetgo_query_items_html'] = array(
			$query_id => '<div class="dsgo-scroll-slide">panel one</div>',
		);

		$html = $this->render( array(), '', $block );

		unset( $GLOBALS['designsetgo_query_items_html'] );

		$this->assertStringContainsString( 'dsgo-scroll-slides', $html );
		$this->assertStringContainsString( 'panel one', $html );
	}
}
