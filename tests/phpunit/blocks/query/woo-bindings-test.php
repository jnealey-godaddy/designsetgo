<?php
/**
 * Tests for the designsetgo/woo-* Block Bindings sources.
 *
 * These assert against real WooCommerce products created by the Unit 0 fixture
 * factory, so the delegation to Woo's product API is genuinely exercised rather
 * than mocked.
 *
 * @group woocommerce
 * @group dynamic-tags
 */

/**
 * @group woocommerce
 */
class DesignSetGo_Woo_Bindings_Test extends WP_UnitTestCase {

	/**
	 * Skips the whole class when WooCommerce is not installed.
	 */
	public function set_up() {
		parent::set_up();
		DesignSetGo_Woo_Product_Factory::skip_if_unavailable( $this );
	}

	/**
	 * Invokes a registered binding source's value callback for a given post.
	 *
	 * Goes through the real registered source so the shared security gates in
	 * designsetgo_register_bindings_source() are part of what is under test.
	 *
	 * @param string $slug    Binding source slug.
	 * @param int    $post_id Post ID to resolve against.
	 * @return mixed
	 */
	private function get_value( $slug, $post_id ) {
		$source = get_block_bindings_source( $slug );

		$this->assertNotNull( $source, "Binding source {$slug} is not registered." );

		// WP_Block_Bindings_Source::$get_value_callback is private (WP 6.5+).
		// Use the public get_value() method instead of the property.
		return $source->get_value( array( '__dsgo_post_id' => $post_id ), null, 'content' );
	}

	/**
	 * All six sources register when WooCommerce is active.
	 */
	public function test_all_woo_sources_are_registered() {
		$expected = array(
			'designsetgo/woo-price-html',
			'designsetgo/woo-price',
			'designsetgo/woo-regular-price',
			'designsetgo/woo-discount-percent',
			'designsetgo/woo-stock-quantity',
			'designsetgo/woo-average-rating',
		);

		foreach ( $expected as $slug ) {
			$this->assertNotNull( get_block_bindings_source( $slug ), "Missing source: {$slug}" );
		}
	}

	/**
	 * Raw price sources return unformatted numbers, not currency markup.
	 *
	 * This is the whole point of the scalars — dsgoStyleBinding cannot put
	 * "$25.00" into a CSS custom property.
	 */
	public function test_raw_price_sources_return_bare_numbers() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array(
				'regular_price' => '40.00',
				'sale_price'    => '25.00',
			)
		);

		$price   = $this->get_value( 'designsetgo/woo-price', $product_id );
		$regular = $this->get_value( 'designsetgo/woo-regular-price', $product_id );

		$this->assertSame( '25.00', $price );
		$this->assertSame( '40.00', $regular );
		$this->assertStringNotContainsString( '<', (string) $price );
	}

	/**
	 * The formatted source returns Woo's own price HTML.
	 */
	public function test_price_html_source_returns_woo_markup() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array(
				'regular_price' => '40.00',
				'sale_price'    => '25.00',
			)
		);

		$html = $this->get_value( 'designsetgo/woo-price-html', $product_id );

		$this->assertStringContainsString( '<del', $html );
		$this->assertStringContainsString( '<ins', $html );
	}

	/**
	 * Discount percent is computed and rounded to a whole number.
	 */
	public function test_discount_percent_is_computed() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array(
				'regular_price' => '40.00',
				'sale_price'    => '25.00',
			)
		);

		// 1 - (25/40) = 37.5% -> 38.
		$this->assertSame( '38', $this->get_value( 'designsetgo/woo-discount-percent', $product_id ) );
	}

	/**
	 * A product that is not on sale reports no discount, rather than zero.
	 */
	public function test_discount_percent_is_null_when_not_on_sale() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array( 'regular_price' => '40.00' )
		);

		$this->assertNull( $this->get_value( 'designsetgo/woo-discount-percent', $product_id ) );
	}

	/**
	 * Stock quantity comes back as a bare integer string for style binding.
	 */
	public function test_stock_quantity_returns_integer_string() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array( 'stock_quantity' => 7 )
		);

		$this->assertSame( '7', $this->get_value( 'designsetgo/woo-stock-quantity', $product_id ) );
	}

	/**
	 * A product not managing stock reports nothing, not zero.
	 *
	 * Zero would render a stock bar as empty-but-present, which is a different
	 * and wrong statement from "this product has no stock concept".
	 */
	public function test_stock_quantity_is_null_when_unmanaged() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple();

		$this->assertNull( $this->get_value( 'designsetgo/woo-stock-quantity', $product_id ) );
	}

	/**
	 * An unreviewed product reports no rating rather than 0.00.
	 */
	public function test_average_rating_is_null_without_reviews() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple();

		$this->assertNull( $this->get_value( 'designsetgo/woo-average-rating', $product_id ) );
	}

	/**
	 * A non-product post yields null from every source, not an error.
	 */
	public function test_sources_return_null_for_non_product_posts() {
		$post_id = self::factory()->post->create();

		foreach ( array( 'designsetgo/woo-price', 'designsetgo/woo-price-html', 'designsetgo/woo-stock-quantity' ) as $slug ) {
			$this->assertNull( $this->get_value( $slug, $post_id ), "Expected null from {$slug}" );
		}
	}

	/**
	 * Stock quantity drives a CSS custom property through dsgoStyleBinding.
	 *
	 * This is the reason Item 1 exists at all. WooCommerce's own blocks cover
	 * every *display* need inside a DSGo query loop, but none exposes a numeric
	 * stock value, so nothing else can drive a stock bar's --dsgo-progress.
	 */
	public function test_stock_quantity_drives_a_style_binding() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array( 'stock_quantity' => 7 )
		);

		$GLOBALS['designsetgo_parent_stack'] = array( array( 'postId' => $product_id ) );

		$style_binding = new \DesignSetGo\StyleBinding();

		$html = $style_binding->apply_style_bindings(
			'<div class="wp-block-designsetgo-progress-bar">X</div>',
			array(
				'blockName'   => 'designsetgo/progress-bar',
				'attrs'       => array(
					'dsgoStyleBinding' => array(
						'--dsgo-progress' => array(
							'source' => 'designsetgo/woo-stock-quantity',
							'args'   => array(),
						),
					),
				),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			)
		);

		unset( $GLOBALS['designsetgo_parent_stack'] );

		$this->assertStringContainsString( '--dsgo-progress:7', $html );
	}

	/**
	 * An html-returning source is refused by the style-binding path.
	 *
	 * A style binding produces a CSS value, so markup has no business there.
	 * Not an XSS route — the tag processor escapes into the style attribute — but
	 * it would silently emit a garbled declaration.
	 */
	public function test_html_returning_source_is_refused_by_style_bindings() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array(
				'regular_price' => '40.00',
				'sale_price'    => '25.00',
			)
		);

		$GLOBALS['designsetgo_parent_stack'] = array( array( 'postId' => $product_id ) );

		$style_binding = new \DesignSetGo\StyleBinding();

		$html = $style_binding->apply_style_bindings(
			'<div class="wp-block">X</div>',
			array(
				'blockName'   => 'core/group',
				'attrs'       => array(
					'dsgoStyleBinding' => array(
						'--dsgo-price' => array(
							'source' => 'designsetgo/woo-price-html',
							'args'   => array(),
						),
					),
				),
				'innerBlocks' => array(),
				'innerContent' => array(),
				'innerHTML'   => '',
			)
		);

		unset( $GLOBALS['designsetgo_parent_stack'] );

		$this->assertStringNotContainsString( '--dsgo-price', $html );
		$this->assertStringNotContainsString( 'woocommerce-Price-amount', $html );
	}

	/**
	 * A scalar source is still accepted, so the guard is not over-broad.
	 */
	public function test_scalar_source_still_resolves_after_the_markup_guard() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array(
				'regular_price' => '40.00',
				'sale_price'    => '25.00',
			)
		);

		$GLOBALS['designsetgo_parent_stack'] = array( array( 'postId' => $product_id ) );

		$style_binding = new \DesignSetGo\StyleBinding();

		$html = $style_binding->apply_style_bindings(
			'<div class="wp-block">X</div>',
			array(
				'blockName'   => 'core/group',
				'attrs'       => array(
					'dsgoStyleBinding' => array(
						'--dsgo-discount' => array(
							'source' => 'designsetgo/woo-discount-percent',
							'args'   => array(),
						),
					),
				),
				'innerBlocks' => array(),
				'innerContent' => array(),
				'innerHTML'   => '',
			)
		);

		unset( $GLOBALS['designsetgo_parent_stack'] );

		$this->assertStringContainsString( '--dsgo-discount:38', $html );
	}

	/**
	 * A keyed source with no key still resolves to nothing.
	 *
	 * Guards the refactor that made the `key` requirement conditional: custom
	 * field sources must not start resolving without one.
	 */
	public function test_keyed_source_without_key_still_returns_nothing() {
		$post_id = self::factory()->post->create();

		$GLOBALS['designsetgo_parent_stack'] = array( array( 'postId' => $post_id ) );

		$style_binding = new \DesignSetGo\StyleBinding();

		$html = $style_binding->apply_style_bindings(
			'<div>X</div>',
			array(
				'blockName'   => 'core/group',
				'attrs'       => array(
					'dsgoStyleBinding' => array(
						'--x' => array(
							'source' => 'designsetgo/post-meta',
							'args'   => array(),
						),
					),
				),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			)
		);

		unset( $GLOBALS['designsetgo_parent_stack'] );

		$this->assertStringNotContainsString( '--x', $html );
	}

	/**
	 * Variable products expose the minimum price raw, and the range via HTML.
	 *
	 * Pins the documented limitation of the raw scalar so it cannot regress into
	 * silently claiming to be the whole price.
	 */
	public function test_variable_product_raw_price_is_the_minimum() {
		$created = DesignSetGo_Woo_Product_Factory::create_variable(
			array(
				'options' => array( 'Small', 'Large' ),
				'prices'  => array( '10.00', '20.00' ),
			)
		);

		$raw  = $this->get_value( 'designsetgo/woo-price', $created['product_id'] );
		$html = $this->get_value( 'designsetgo/woo-price-html', $created['product_id'] );

		$this->assertSame( '10.00', $raw );
		$this->assertStringContainsString( '20.00', $html );
	}
}
