<?php
/**
 * WooCommerce product fixture factory for PHPUnit.
 *
 * The WooCommerce surface (docs/plans/2026-08-17-woocommerce-surface.md) is built
 * on *delegation* to WooCommerce's own product API — get_price_html() for prices,
 * add_to_cart_url() / add_to_cart_text() / supports('ajax_add_to_cart') for the cart
 * button. None of that is verifiable against a stubbed wc_get_product() that returns
 * false, which is all the pre-existing Woo tests could do.
 *
 * This factory creates real products of all four core types so those delegations can
 * be asserted for real. It is deliberately tolerant of WooCommerce being absent:
 * `available()` reports the truth and `skip_if_unavailable()` skips cleanly, so the
 * suite still runs on an environment without Woo installed.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates WooCommerce product fixtures for tests.
 */
class DesignSetGo_Woo_Product_Factory {

	/**
	 * Whether WooCommerce is loaded and its product classes are usable.
	 *
	 * Checks a product class rather than the `WooCommerce` class itself, because
	 * the CRUD classes are what every fixture below actually needs.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( 'WC_Product_Simple' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Marks the calling test skipped when WooCommerce is unavailable.
	 *
	 * @param \WP_UnitTestCase $test Test case to skip.
	 * @return bool True when WooCommerce is available and the test may proceed.
	 */
	public static function skip_if_unavailable( $test ) {
		if ( self::available() ) {
			return true;
		}

		$test->markTestSkipped( 'WooCommerce is not installed in this environment.' );

		return false;
	}

	/**
	 * Creates a simple product.
	 *
	 * @param array $args {
	 *     Optional. Product properties.
	 *
	 *     @type string $name               Product name.
	 *     @type string $regular_price      Regular price.
	 *     @type string $sale_price         Sale price. Empty for none.
	 *     @type string $sku                SKU.
	 *     @type string $stock_status       'instock' | 'outofstock' | 'onbackorder'.
	 *     @type int    $stock_quantity     Managed stock quantity. Null to not manage stock.
	 *     @type string $catalog_visibility 'visible' | 'catalog' | 'search' | 'hidden'.
	 *     @type bool   $featured           Whether the product is featured.
	 * }
	 * @return int Product ID.
	 */
	public static function create_simple( array $args = array() ) {
		$args = array_merge(
			array(
				'name'               => 'Simple Product',
				'regular_price'      => '20.00',
				'sale_price'         => '',
				'sku'                => '',
				'stock_status'       => 'instock',
				'stock_quantity'     => null,
				'catalog_visibility' => 'visible',
				'featured'           => false,
			),
			$args
		);

		$product = new WC_Product_Simple();
		self::apply_common( $product, $args );

		return $product->save();
	}

	/**
	 * Creates a variable product with two variations at different prices.
	 *
	 * The differing prices matter: get_price_html() renders a *range* for these,
	 * which is precisely the case a scalar get_price() would report wrongly.
	 *
	 * @param array $args {
	 *     Optional. Product properties.
	 *
	 *     @type string   $name               Product name.
	 *     @type string   $attribute_name     Custom (non-taxonomy) attribute name.
	 *     @type string[] $options            Attribute option values, one per variation.
	 *     @type string[] $prices             Regular price per variation, parallel to $options.
	 *     @type string   $catalog_visibility Catalog visibility.
	 * }
	 * @return array {
	 *     @type int   $product_id Parent product ID.
	 *     @type int[] $variations Variation IDs, in $options order.
	 * }
	 */
	public static function create_variable( array $args = array() ) {
		$args = array_merge(
			array(
				'name'               => 'Variable Product',
				'attribute_name'     => 'Size',
				'options'            => array( 'Small', 'Large' ),
				'prices'             => array( '10.00', '20.00' ),
				'catalog_visibility' => 'visible',
			),
			$args
		);

		$attribute = new WC_Product_Attribute();
		$attribute->set_name( $args['attribute_name'] );
		$attribute->set_options( $args['options'] );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$product = new WC_Product_Variable();
		$product->set_name( $args['name'] );
		$product->set_catalog_visibility( $args['catalog_visibility'] );
		$product->set_attributes( array( $attribute ) );
		$product_id = $product->save();

		$variations = array();

		foreach ( $args['options'] as $index => $option ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( array( sanitize_title( $args['attribute_name'] ) => $option ) );
			$variation->set_regular_price( isset( $args['prices'][ $index ] ) ? $args['prices'][ $index ] : '10.00' );
			$variation->set_stock_status( 'instock' );
			$variations[] = $variation->save();
		}

		// Resync so the parent's price lookup reflects the variations just created.
		WC_Product_Variable::sync( $product_id );

		return array(
			'product_id' => $product_id,
			'variations' => $variations,
		);
	}

	/**
	 * Creates a grouped product wrapping the given children.
	 *
	 * When no children are supplied, two simple products are created for it.
	 *
	 * @param array $args {
	 *     Optional. Product properties.
	 *
	 *     @type string $name     Product name.
	 *     @type int[]  $children Child product IDs.
	 * }
	 * @return array {
	 *     @type int   $product_id Grouped product ID.
	 *     @type int[] $children   Child product IDs.
	 * }
	 */
	public static function create_grouped( array $args = array() ) {
		$args = array_merge(
			array(
				'name'     => 'Grouped Product',
				'children' => array(),
			),
			$args
		);

		if ( empty( $args['children'] ) ) {
			$args['children'] = array(
				self::create_simple(
					array(
						'name'          => 'Grouped Child One',
						'regular_price' => '5.00',
					)
				),
				self::create_simple(
					array(
						'name'          => 'Grouped Child Two',
						'regular_price' => '15.00',
					)
				),
			);
		}

		$product = new WC_Product_Grouped();
		$product->set_name( $args['name'] );
		$product->set_children( $args['children'] );

		return array(
			'product_id' => $product->save(),
			'children'   => $args['children'],
		);
	}

	/**
	 * Creates an external/affiliate product.
	 *
	 * External products are never purchasable, so they exercise the branch where
	 * the cart button must degrade to a plain outbound link.
	 *
	 * @param array $args {
	 *     Optional. Product properties.
	 *
	 *     @type string $name          Product name.
	 *     @type string $regular_price Regular price.
	 *     @type string $product_url   Outbound URL.
	 *     @type string $button_text   Button label.
	 * }
	 * @return int Product ID.
	 */
	public static function create_external( array $args = array() ) {
		$args = array_merge(
			array(
				'name'          => 'External Product',
				'regular_price' => '30.00',
				'product_url'   => 'https://example.org/buy',
				'button_text'   => 'Buy on example.org',
			),
			$args
		);

		$product = new WC_Product_External();
		$product->set_name( $args['name'] );
		$product->set_regular_price( $args['regular_price'] );
		$product->set_product_url( $args['product_url'] );
		$product->set_button_text( $args['button_text'] );

		return $product->save();
	}

	/**
	 * Applies the shared simple-product properties.
	 *
	 * @param WC_Product $product Product object.
	 * @param array      $args    Normalised args from create_simple().
	 */
	private static function apply_common( $product, array $args ) {
		$product->set_name( $args['name'] );
		$product->set_regular_price( $args['regular_price'] );
		$product->set_catalog_visibility( $args['catalog_visibility'] );
		$product->set_featured( (bool) $args['featured'] );
		$product->set_stock_status( $args['stock_status'] );

		if ( '' !== $args['sale_price'] ) {
			$product->set_sale_price( $args['sale_price'] );
		}

		if ( '' !== $args['sku'] ) {
			$product->set_sku( $args['sku'] );
		}

		if ( null !== $args['stock_quantity'] ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $args['stock_quantity'] );
		}
	}
}
