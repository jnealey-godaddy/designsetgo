<?php
/**
 * Tests for the product-categories-grid block server render.
 *
 * WooCommerce is not installed in the test environment, so we stub the
 * `wc_get_product()` guard and register the `product_cat` taxonomy ourselves.
 * The render template otherwise relies only on core term APIs, which lets us
 * exercise the get_terms() exclusion path and the manual/all source modes.
 *
 * @group product-categories-grid
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
 * @group product-categories-grid
 */
class DesignSetGo_Product_Categories_Grid_Render_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		register_taxonomy(
			'product_cat',
			array( 'product' ),
			array(
				'hierarchical' => true,
				'public'       => true,
				'rewrite'      => false,
			)
		);
	}

	public function tear_down() {
		unregister_taxonomy( 'product_cat' );
		delete_option( 'default_product_cat' );
		parent::tear_down();
	}

	/**
	 * Create a product_cat term and return its ID.
	 *
	 * @param string $name Term name.
	 * @param string $slug Term slug.
	 * @return int Term ID.
	 */
	private function make_category( $name, $slug ) {
		$term = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
		$this->assertNotWPError( $term );
		return (int) $term['term_id'];
	}

	/**
	 * Include the built render template with the given attributes and capture output.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML ('' when the template bails).
	 */
	private function render( array $attributes ) {
		$path = DESIGNSETGO_PATH . 'build/blocks/product-categories-grid/render.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render templates are served from build/.' );

		$content = '';
		$block   = null;

		// get_block_wrapper_attributes() reads the active block from
		// WP_Block_Supports; prime it so the call works outside a full render.
		$previous_block = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'designsetgo/product-categories-grid',
			'attrs'     => array(),
		);

		ob_start();
		$returned = include $path;
		$html     = ob_get_clean();

		WP_Block_Supports::$block_to_render = $previous_block;

		// The template echoes markup (include returns 1) on success and
		// `return ''` on a bail.
		return is_string( $returned ) ? $returned : $html;
	}

	public function test_all_mode_excludes_uncategorized_default_category() {
		$apparel       = $this->make_category( 'Apparel', 'apparel' );
		$shoes         = $this->make_category( 'Shoes', 'shoes' );
		$uncategorized = $this->make_category( 'Uncategorized', 'uncategorized' );
		update_option( 'default_product_cat', $uncategorized );

		$html = $this->render(
			array(
				'categorySource' => 'all',
				'showEmpty'      => true,
			)
		);

		$this->assertStringContainsString( 'Apparel', $html );
		$this->assertStringContainsString( 'Shoes', $html );
		$this->assertStringNotContainsString( 'Uncategorized', $html );
		$this->assertSame( 2, substr_count( $html, '<a href=' ), 'One card per visible category.' );
	}

	public function test_all_mode_honours_exclude_categories_attribute() {
		$apparel = $this->make_category( 'Apparel', 'apparel' );
		$shoes   = $this->make_category( 'Shoes', 'shoes' );

		$html = $this->render(
			array(
				'categorySource'    => 'all',
				'showEmpty'         => true,
				'excludeCategories' => array( $shoes ),
			)
		);

		$this->assertStringContainsString( 'Apparel', $html );
		$this->assertStringNotContainsString( 'Shoes', $html );
		$this->assertSame( 1, substr_count( $html, '<a href=' ) );
	}

	public function test_manual_mode_renders_selected_categories_in_order() {
		$apparel = $this->make_category( 'Apparel', 'apparel' );
		$shoes   = $this->make_category( 'Shoes', 'shoes' );

		$html = $this->render(
			array(
				'categorySource'     => 'manual',
				'selectedCategories' => array(
					array( 'id' => $shoes ),
					array( 'id' => $apparel ),
				),
			)
		);

		$this->assertStringContainsString( 'Apparel', $html );
		$this->assertStringContainsString( 'Shoes', $html );
		$this->assertLessThan(
			strpos( $html, 'Apparel' ),
			strpos( $html, 'Shoes' ),
			'Manual mode preserves the user-defined order (orderby=include).'
		);
	}

	public function test_manual_mode_with_no_valid_selection_renders_nothing() {
		$this->make_category( 'Apparel', 'apparel' );

		$html = $this->render(
			array(
				'categorySource'     => 'manual',
				'selectedCategories' => array(),
			)
		);

		$this->assertSame( '', $html );
	}

	public function test_renders_nothing_when_no_categories_exist() {
		$html = $this->render(
			array(
				'categorySource' => 'all',
				'showEmpty'      => true,
			)
		);

		$this->assertSame( '', $html );
	}
}
