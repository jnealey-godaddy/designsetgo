<?php
/**
 * Tests for the product-showcase-hero block server render.
 *
 * WooCommerce is not installed in the test environment, so the full happy-path
 * render (which needs a real WC_Product) cannot run here — that is covered by
 * e2e/manual testing on a WooCommerce site. These tests stub the
 * `wc_get_product()` guard and assert the resolution/bail logic in the
 * refactored designsetgo_render_product_showcase_hero() returns '' cleanly
 * (no fatal) when no valid product is available.
 *
 * @group product-showcase-hero
 */

if ( ! function_exists( 'wc_get_product' ) ) {
	/**
	 * Minimal stub so the render template passes its WooCommerce guard.
	 *
	 * @param mixed $the_product Ignored.
	 * @return false
	 */
	function wc_get_product( $the_product = false ) {
		return false;
	}
}

/**
 * @group product-showcase-hero
 */
class DesignSetGo_Product_Showcase_Hero_Render_Test extends WP_UnitTestCase {

	/**
	 * Include the built render template with the given attributes and capture
	 * its echoed output.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML ('' when the template bails).
	 */
	private function render( array $attributes ) {
		$path = DESIGNSETGO_PATH . 'build/blocks/product-showcase-hero/render.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render templates are served from build/.' );

		$content = '';
		$block   = null;

		$previous_block                     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'designsetgo/product-showcase-hero',
			'attrs'     => array(),
		);

		ob_start();
		$returned = include $path;
		$html     = ob_get_clean();

		WP_Block_Supports::$block_to_render = $previous_block;

		return is_string( $returned ) ? $returned : $html;
	}

	public function test_renders_nothing_when_no_product_id() {
		$html = $this->render(
			array(
				'productSource' => 'manual',
				'productId'     => 0,
			)
		);

		$this->assertSame( '', $html );
	}

	public function test_renders_nothing_when_product_not_found() {
		// wc_get_product() is stubbed to return false, so any id resolves to no
		// product and the template bails without fatal.
		$html = $this->render(
			array(
				'productSource' => 'manual',
				'productId'     => 99999,
			)
		);

		$this->assertSame( '', $html );
	}
}
