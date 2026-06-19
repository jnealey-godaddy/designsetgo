<?php
/**
 * Tests for the slider block server render.
 *
 * Regression guard for the PrefixAllGlobals refactor that wrapped render.php in
 * designsetgo_render_slider(). Two modes:
 *  - authored (no query context): the saved markup passes through unchanged;
 *  - query-bound: the template rebuilds slider chrome and splices in items
 *    stashed by the parent query container. The query path is where the
 *    refactored local variables live, so we assert the wrapper chrome here.
 *    (Full query integration is also covered by query/slider-host-test.php.)
 *
 * @group slider
 */

/**
 * @group slider
 */
class DesignSetGo_Slider_Render_Test extends WP_UnitTestCase {

	/**
	 * Include the built render template and capture its echoed output.
	 *
	 * @param array       $attributes Block attributes.
	 * @param string      $content    Pre-rendered save.js output.
	 * @param object|null $block      Block instance (carries context).
	 * @return string Rendered HTML.
	 */
	private function render( array $attributes, $content = '', $block = null ) {
		$path = DESIGNSETGO_PATH . 'build/blocks/slider/render.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render templates are served from build/.' );

		$previous_block                     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'designsetgo/slider',
			'attrs'     => array(),
		);

		ob_start();
		$returned = include $path;
		$html     = ob_get_clean();

		WP_Block_Supports::$block_to_render = $previous_block;

		return is_string( $returned ) ? $returned : $html;
	}

	public function test_authored_mode_passes_content_through_unchanged() {
		$content = '<div class="dsgo-slider">authored slider markup</div>';

		$html = $this->render( array(), $content );

		$this->assertSame( $content, $html );
	}

	public function test_query_mode_builds_chrome_and_injects_items() {
		$query_id = 'abc123';

		$block          = new stdClass();
		$block->context = array( 'designsetgo/queryId' => $query_id );

		$GLOBALS['designsetgo_query_items_html'] = array(
			$query_id => '<div class="dsgo-slide">item one</div>',
		);

		$html = $this->render(
			array(
				'effect'     => 'slide',
				'showArrows' => true,
			),
			'',
			$block
		);

		unset( $GLOBALS['designsetgo_query_items_html'] );

		$this->assertStringContainsString( 'dsgo-slider', $html );
		$this->assertStringContainsString( 'dsgo-slider__viewport', $html );
		$this->assertStringContainsString( 'dsgo-slider__track', $html );
		$this->assertStringContainsString( 'item one', $html );
		$this->assertStringContainsString( 'data-show-arrows="true"', $html );
	}
}
