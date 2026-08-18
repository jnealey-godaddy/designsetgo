<?php
/**
 * Tests for WooCommerce-aware Query block arguments.
 *
 * Covers catalog visibility, the block's own Woo attributes, and consumption of
 * the URL params WooCommerce's own filter blocks emit — the mechanism that lets
 * `woocommerce/product-filters` drive a `designsetgo/query` loop without DSGo
 * rebuilding a filter UI.
 *
 * @group woocommerce
 * @group query
 */

/**
 * @group woocommerce
 */
class DesignSetGo_Woo_Query_Args_Test extends WP_UnitTestCase {

	/**
	 * Skips the class when WooCommerce is unavailable and loads the helpers.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! DesignSetGo_Woo_Product_Factory::skip_if_unavailable( $this ) ) {
			return;
		}

		// Loaded from build/, not src/, because that is what production loads —
		// a copy rule that misses this file must fail here rather than pass.
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-woo.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — query helpers are served from build/.' );
		require_once $path;
	}

	/**
	 * Loads the query render helpers from build/.
	 */
	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $path;
	}

	/**
	 * Baseline args targeting products.
	 *
	 * @return array
	 */
	private function product_args() {
		return array( 'post_type' => 'product' );
	}

	/**
	 * Finds the first tax_query clause for a taxonomy.
	 *
	 * @param array  $args     WP_Query args.
	 * @param string $taxonomy Taxonomy name.
	 * @return array|null
	 */
	private function find_tax_clause( array $args, $taxonomy ) {
		foreach ( (array) ( $args['tax_query'] ?? array() ) as $clause ) {
			if ( is_array( $clause ) && ( $clause['taxonomy'] ?? '' ) === $taxonomy ) {
				return $clause;
			}
		}

		return null;
	}

	/**
	 * Finds the first meta_query clause for a key, optionally by compare.
	 *
	 * @param array  $args    WP_Query args.
	 * @param string $key     Meta key.
	 * @param string $compare Optional compare operator to match.
	 * @return array|null
	 */
	private function find_meta_clause( array $args, $key, $compare = '' ) {
		foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
			if ( ! is_array( $clause ) || ( $clause['key'] ?? '' ) !== $key ) {
				continue;
			}
			if ( '' !== $compare && ( $clause['compare'] ?? '' ) !== $compare ) {
				continue;
			}
			return $clause;
		}

		return null;
	}

	/**
	 * Non-product queries are left completely untouched.
	 */
	public function test_non_product_queries_are_untouched() {
		$args = array( 'post_type' => 'post' );

		$this->assertSame( $args, designsetgo_query_apply_woo_args( $args, array(), array() ) );
	}

	/**
	 * A post_type=any query is untouched, which is what relationship loops are.
	 *
	 * render-relationship.php delegates to the posts renderer with source=manual,
	 * so it DOES reach designsetgo_query_apply_woo_args() — but it also forces
	 * post_type=any, so targets_products() returns false and nothing applies.
	 *
	 * Pinning this because the boundary is easy to move by accident: broadening
	 * targets_products() to accept 'any' would silently start applying
	 * product_visibility and on-sale filtering to mixed-type relationship loops.
	 * The trade-off is that a relationship loop over products gets no
	 * catalog-visibility filtering today.
	 */
	public function test_post_type_any_is_untouched() {
		$args = array(
			'post_type' => 'any',
			'post__in'  => array( 1, 2 ),
		);

		$this->assertSame(
			$args,
			designsetgo_query_apply_woo_args(
				$args,
				array(
					'wooOnSale'  => true,
					'wooFeatured' => true,
				),
				array( 'min_price' => '10' )
			)
		);
	}

	/**
	 * Catalog visibility excludes hidden products by default.
	 */
	public function test_catalog_visibility_applies_by_default() {
		$args   = designsetgo_query_apply_woo_args( $this->product_args(), array(), array() );
		$clause = $this->find_tax_clause( $args, 'product_visibility' );

		$this->assertNotNull( $clause );
		$this->assertSame( 'NOT IN', $clause['operator'] );
		$this->assertContains( 'exclude-from-catalog', $clause['terms'] );
	}

	/**
	 * The author can explicitly opt out of catalog visibility.
	 */
	public function test_catalog_visibility_can_be_disabled() {
		$args = designsetgo_query_apply_woo_args(
			$this->product_args(),
			array( 'wooCatalogVisibility' => false ),
			array()
		);

		$this->assertNull( $this->find_tax_clause( $args, 'product_visibility' ) );
	}

	/**
	 * Hiding out-of-stock products is honoured when the store option is on.
	 */
	public function test_out_of_stock_hidden_when_store_option_set() {
		update_option( 'woocommerce_hide_out_of_stock_items', 'yes' );

		$args   = designsetgo_query_apply_woo_args( $this->product_args(), array(), array() );
		$clause = $this->find_tax_clause( $args, 'product_visibility' );

		delete_option( 'woocommerce_hide_out_of_stock_items' );

		$this->assertContains( 'outofstock', $clause['terms'] );
	}

	/**
	 * The featured attribute adds an IN clause on product_visibility.
	 */
	public function test_featured_attribute_adds_clause() {
		$args = designsetgo_query_apply_woo_args(
			$this->product_args(),
			array(
				'wooCatalogVisibility' => false,
				'wooFeatured'          => true,
			),
			array()
		);

		$clause = $this->find_tax_clause( $args, 'product_visibility' );

		$this->assertSame( 'IN', $clause['operator'] );
		$this->assertSame( array( 'featured' ), $clause['terms'] );
	}

	/**
	 * Woo's min_price / max_price params become a _price meta query.
	 *
	 * Matches WooCommerce's own ProductCollection QueryBuilder semantics.
	 */
	public function test_price_params_become_price_meta_query() {
		$args = designsetgo_query_apply_woo_args(
			$this->product_args(),
			array(),
			array(
				'min_price' => '10',
				'max_price' => '50',
			)
		);

		$this->assertNotNull( $this->find_meta_clause( $args, '_price', '>=' ) );
		$this->assertNotNull( $this->find_meta_clause( $args, '_price', '<=' ) );
		$this->assertSame( 10.0, $this->find_meta_clause( $args, '_price', '>=' )['value'] );
		$this->assertSame( 50.0, $this->find_meta_clause( $args, '_price', '<=' )['value'] );
	}

	/**
	 * Woo's rating_filter param maps onto rated-N visibility terms.
	 */
	public function test_rating_filter_maps_to_rated_terms() {
		$args = designsetgo_query_apply_woo_args(
			$this->product_args(),
			array( 'wooCatalogVisibility' => false ),
			array( 'rating_filter' => '4,5' )
		);

		$clause = $this->find_tax_clause( $args, 'product_visibility' );

		$this->assertSame( array( 'rated-4', 'rated-5' ), $clause['terms'] );
	}

	/**
	 * Out-of-range ratings are discarded rather than producing bogus terms.
	 */
	public function test_rating_filter_rejects_out_of_range_values() {
		$args = designsetgo_query_apply_woo_args(
			$this->product_args(),
			array( 'wooCatalogVisibility' => false ),
			array( 'rating_filter' => '0,9' )
		);

		$this->assertNull( $this->find_tax_clause( $args, 'product_visibility' ) );
	}

	/**
	 * Woo's stock-status param becomes a _stock_status meta query.
	 */
	public function test_stock_status_param_becomes_meta_query() {
		$args = designsetgo_query_apply_woo_args(
			$this->product_args(),
			array(),
			array( 'filter_stock_status' => 'instock' )
		);

		$clause = $this->find_meta_clause( $args, '_stock_status' );

		$this->assertNotNull( $clause );
		$this->assertSame( array( 'instock' ), $clause['value'] );
	}

	/**
	 * Woo's filter_<attr> maps onto the pa_<attr> taxonomy.
	 *
	 * This is the mapping that makes Woo's filter blocks usable with a DSGo
	 * query: Woo strips the `pa_` prefix in the URL, so the generic
	 * filter_<taxonomy> handler in render-posts.php checks taxonomy_exists(
	 * 'color' ), finds nothing, and skips the param silently.
	 */
	public function test_attribute_param_maps_to_pa_taxonomy() {
		register_taxonomy( 'pa_color', array( 'product' ), array( 'public' => true ) );

		$args = designsetgo_query_apply_woo_args(
			$this->product_args(),
			array( 'wooCatalogVisibility' => false ),
			array( 'filter_color' => 'red,blue' )
		);

		$clause = $this->find_tax_clause( $args, 'pa_color' );

		unregister_taxonomy( 'pa_color' );

		$this->assertNotNull( $clause );
		$this->assertSame( array( 'red', 'blue' ), $clause['terms'] );
		$this->assertSame( 'IN', $clause['operator'] );
	}

	/**
	 * query_type_<attr>=and switches the attribute clause to AND, matching Woo.
	 */
	public function test_attribute_query_type_and_switches_operator() {
		register_taxonomy( 'pa_color', array( 'product' ), array( 'public' => true ) );

		$args = designsetgo_query_apply_woo_args(
			$this->product_args(),
			array( 'wooCatalogVisibility' => false ),
			array(
				'filter_color'     => 'red,blue',
				'query_type_color' => 'and',
			)
		);

		$clause = $this->find_tax_clause( $args, 'pa_color' );

		unregister_taxonomy( 'pa_color' );

		$this->assertSame( 'AND', $clause['operator'] );
	}

	/**
	 * A filter_<taxonomy> for a real taxonomy is left to the generic handler.
	 *
	 * Guards against double-applying: render-posts.php already handles those,
	 * and adding a pa_-prefixed clause on top would silently narrow results.
	 */
	public function test_real_taxonomy_params_are_not_double_handled() {
		register_taxonomy( 'pa_brand', array( 'product' ), array( 'public' => true ) );
		register_taxonomy( 'brand', array( 'product' ), array( 'public' => true ) );

		$args = designsetgo_query_apply_woo_args(
			$this->product_args(),
			array( 'wooCatalogVisibility' => false ),
			array( 'filter_brand' => 'acme' )
		);

		$clause = $this->find_tax_clause( $args, 'pa_brand' );

		unregister_taxonomy( 'pa_brand' );
		unregister_taxonomy( 'brand' );

		$this->assertNull( $clause, 'Existing taxonomy must be left to the generic handler.' );
	}

	/**
	 * End to end: a hidden product does not appear in a rendered loop.
	 *
	 * The unit tests above assert the shape of the args; this asserts the result,
	 * through the same designsetgo_query_render() path the front end uses.
	 */
	public function test_hidden_product_is_excluded_from_a_rendered_loop() {
		$this->load_helpers();

		DesignSetGo_Woo_Product_Factory::create_simple( array( 'name' => 'Visible Widget' ) );
		DesignSetGo_Woo_Product_Factory::create_simple(
			array(
				'name'               => 'Hidden Widget',
				'catalog_visibility' => 'hidden',
			)
		);

		$out = designsetgo_query_render(
			array(
				'source'   => 'posts',
				'postType' => 'product',
				'perPage'  => 20,
			),
			array(
				'query_id'   => 'woo-e2e',
				'page'       => 1,
				'inner_html' => '<!-- wp:post-title /-->',
			)
		);

		$html = is_array( $out ) && isset( $out['html'] ) ? $out['html'] : (string) $out;

		$this->assertStringContainsString( 'Visible Widget', $html );
		$this->assertStringNotContainsString( 'Hidden Widget', $html );
	}

	/**
	 * An author-configured OR tax_query survives as an OR group.
	 */
	public function test_existing_or_group_is_preserved() {
		$args = array(
			'post_type' => 'product',
			'tax_query' => array(
				'relation' => 'OR',
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => array( 'a' ),
				),
			),
		);

		$result = designsetgo_query_apply_woo_args( $args, array(), array() );

		$this->assertSame( 'AND', $result['tax_query']['relation'] );
		$this->assertSame( 'OR', $result['tax_query'][0]['relation'] );
	}
}
