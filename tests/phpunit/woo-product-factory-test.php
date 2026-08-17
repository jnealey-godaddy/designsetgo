<?php
/**
 * Tests for the WooCommerce product fixture factory.
 *
 * These are infrastructure tests: they prove the factory produces genuinely usable
 * products of all four core types. They also pin the WooCommerce behaviours the
 * WooCommerce Surface plan delegates to, so a Woo upgrade that changes them fails
 * here rather than silently in a block.
 *
 * @group woocommerce
 */

/**
 * @group woocommerce
 */
class DesignSetGo_Woo_Product_Factory_Test extends WP_UnitTestCase {

	/**
	 * Skips the whole class when WooCommerce is not installed.
	 */
	public function set_up() {
		parent::set_up();
		DesignSetGo_Woo_Product_Factory::skip_if_unavailable( $this );
	}

	/**
	 * A simple product is purchasable and supports AJAX add-to-cart.
	 *
	 * The `ajax_add_to_cart` support flag is the switch the cart button branches on
	 * (decision D8): supported means a Store API call, unsupported means a link.
	 */
	public function test_simple_product_is_purchasable_and_ajax_capable() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array(
				'regular_price' => '20.00',
				'sku'           => 'DSGO-SIMPLE-1',
			)
		);

		$product = wc_get_product( $product_id );

		$this->assertInstanceOf( 'WC_Product_Simple', $product );
		$this->assertSame( 'simple', $product->get_type() );
		$this->assertSame( 'DSGO-SIMPLE-1', $product->get_sku() );
		$this->assertTrue( $product->is_purchasable() );
		$this->assertTrue( $product->is_in_stock() );
		$this->assertTrue( $product->supports( 'ajax_add_to_cart' ) );
	}

	/**
	 * A sale price produces the del/ins markup that only get_price_html() renders.
	 *
	 * Decision D3: this is why woo-price-html exists alongside the scalars.
	 */
	public function test_sale_price_renders_strikethrough_markup() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array(
				'regular_price' => '20.00',
				'sale_price'    => '15.00',
			)
		);

		$product = wc_get_product( $product_id );

		$this->assertTrue( $product->is_on_sale() );

		$html = $product->get_price_html();

		$this->assertStringContainsString( '<del', $html );
		$this->assertStringContainsString( '<ins', $html );
	}

	/**
	 * A variable product reports a price *range*, which get_price() cannot.
	 *
	 * This is the concrete failure mode decision D3 guards against: get_price()
	 * returns only the minimum, so a scalar-only price source would under-report.
	 */
	public function test_variable_product_price_html_spans_the_range() {
		$created = DesignSetGo_Woo_Product_Factory::create_variable(
			array(
				'options' => array( 'Small', 'Large' ),
				'prices'  => array( '10.00', '20.00' ),
			)
		);

		$product = wc_get_product( $created['product_id'] );

		$this->assertSame( 'variable', $product->get_type() );
		$this->assertCount( 2, $created['variations'] );

		$html = $product->get_price_html();

		$this->assertStringContainsString( '10.00', $html );
		$this->assertStringContainsString( '20.00', $html );

		// The scalar price is the minimum only — the exact under-reporting D3 avoids.
		$this->assertSame( '10.00', (string) $product->get_price() );
	}

	/**
	 * A variable product is not AJAX-addable, so the button must degrade to a link.
	 */
	public function test_variable_product_degrades_to_a_link() {
		$created = DesignSetGo_Woo_Product_Factory::create_variable();
		$product = wc_get_product( $created['product_id'] );

		$this->assertFalse( $product->supports( 'ajax_add_to_cart' ) );
		$this->assertNotSame( '', $product->add_to_cart_text() );
	}

	/**
	 * A grouped product carries its children.
	 */
	public function test_grouped_product_has_children() {
		$created = DesignSetGo_Woo_Product_Factory::create_grouped();
		$product = wc_get_product( $created['product_id'] );

		$this->assertSame( 'grouped', $product->get_type() );
		$this->assertCount( 2, $product->get_children() );
	}

	/**
	 * An external product is never purchasable and links out.
	 *
	 * Decision D8: delegating to add_to_cart_url() gets this right for free.
	 */
	public function test_external_product_links_out_and_is_not_purchasable() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_external(
			array( 'product_url' => 'https://example.org/buy' )
		);

		$product = wc_get_product( $product_id );

		$this->assertSame( 'external', $product->get_type() );
		$this->assertFalse( $product->is_purchasable() );
		$this->assertSame( 'https://example.org/buy', $product->add_to_cart_url() );
	}

	/**
	 * Catalog visibility is settable, which decision D5's query work depends on.
	 */
	public function test_hidden_product_reports_hidden_catalog_visibility() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array( 'catalog_visibility' => 'hidden' )
		);

		$product = wc_get_product( $product_id );

		$this->assertSame( 'hidden', $product->get_catalog_visibility() );
	}

	/**
	 * Managed stock is settable, which the stock-bar style binding depends on.
	 */
	public function test_managed_stock_quantity_is_readable() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array( 'stock_quantity' => 7 )
		);

		$product = wc_get_product( $product_id );

		$this->assertTrue( $product->managing_stock() );
		$this->assertSame( 7, $product->get_stock_quantity() );
	}
}
