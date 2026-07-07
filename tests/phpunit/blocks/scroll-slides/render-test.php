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

	/**
	 * The PHP overlay-opacity formatter must produce the same fraction the JS
	 * overlayOpacityFraction() util does (src/utils/overlay-opacity.js): the 80
	 * default maps to 0.8, percent maps to a fraction, and out-of-range values
	 * clamp to [0, 1]. Guards against the two hand-synced paths silently drifting.
	 *
	 * Regex boundaries (negative lookahead) assert the exact numeric value without
	 * depending on how core serializes the trailing style delimiter.
	 *
	 * @dataProvider data_overlay_opacity
	 *
	 * @param int    $opacity  The overlayOpacity attribute (percent).
	 * @param string $fraction Regex-escaped expected fraction string.
	 */
	public function test_query_mode_overlay_opacity_matches_js( $opacity, $fraction ) {
		$query_id = 'slidesOpacity';

		$block          = new stdClass();
		$block->context = array( 'designsetgo/queryId' => $query_id );

		$GLOBALS['designsetgo_query_items_html'] = array(
			$query_id => '<div class="dsgo-scroll-slide">panel</div>',
		);

		$html = $this->render(
			array(
				'overlayColor'   => '#000000',
				'overlayOpacity' => $opacity,
			),
			'',
			$block
		);

		unset( $GLOBALS['designsetgo_query_items_html'] );

		$this->assertMatchesRegularExpression(
			'/--dsgo-overlay-opacity:' . $fraction . '(?![.\d])/',
			$html,
			"overlayOpacity {$opacity} should render fraction {$fraction}"
		);
	}

	/**
	 * @return array<string, array{0:int,1:string}> opacity => regex-escaped fraction.
	 */
	public function data_overlay_opacity() {
		return array(
			'default 80 => 0.8'      => array( 80, '0\.8' ),
			'mid 50 => 0.5'          => array( 50, '0\.5' ),
			'zero preserved'         => array( 0, '0' ),
			'above range clamps to 1' => array( 150, '1' ),
			'below range clamps to 0' => array( -20, '0' ),
		);
	}
}
