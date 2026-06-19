<?php
/**
 * Tests for the dynamic-image block server render.
 *
 * Regression guard for the PrefixAllGlobals refactor that wrapped render.php in
 * designsetgo_render_dynamic_image(). The template resolves an image source via
 * ImageResolver and falls back to a fallbackUrl/fallbackId; we exercise the
 * fallback-URL happy path (no attachment needed) and the no-source bail.
 *
 * @group dynamic-image
 */

/**
 * @group dynamic-image
 */
class DesignSetGo_Dynamic_Image_Render_Test extends WP_UnitTestCase {

	/**
	 * Include the built render template with the given attributes and capture
	 * its echoed output.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML ('' when the template bails).
	 */
	private function render( array $attributes ) {
		$path = DESIGNSETGO_PATH . 'build/blocks/dynamic-image/render.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render templates are served from build/.' );

		$content = '';
		$block   = null;

		$previous_block                     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'designsetgo/dynamic-image',
			'attrs'     => array(),
		);

		ob_start();
		$returned = include $path;
		$html     = ob_get_clean();

		WP_Block_Supports::$block_to_render = $previous_block;

		return is_string( $returned ) ? $returned : $html;
	}

	public function test_renders_figure_from_fallback_url() {
		$html = $this->render(
			array(
				'fallbackUrl' => 'https://example.com/fallback.jpg',
				'altText'     => 'Fallback alt',
				'aspectRatio' => '16/9',
			)
		);

		$this->assertStringContainsString( '<figure', $html );
		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( 'https://example.com/fallback.jpg', $html );
	}

	public function test_renders_nothing_without_source_or_fallback() {
		$html = $this->render( array() );

		$this->assertSame( '', $html );
	}
}
