<?php
/**
 * Per-Query URL params as rendered: form fields, no-JS hidden inputs,
 * active-filter chips, Reset, numbered pagination and the REST refresh.
 *
 * Each case renders a real page (the post is set up as the current post, so
 * the page's Queries are found up front, as on the front end) and asserts on
 * the markup a visitor would get.
 *
 * @group query
 */
class DesignSetGo_Query_Param_Scoping_Render_Test extends WP_UnitTestCase {

	const A = 'qa1a1a1a1';
	const B = 'qb2b2b2b2';

	/**
	 * Loads the query helpers from build/ (production's actual source).
	 */
	public function set_up() {
		parent::set_up();
		$helpers = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $helpers, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $helpers;
		$this->assertFileExists( DESIGNSETGO_PATH . 'build/blocks/query/filter-links.php', 'filter-links.php must be copied to build/.' );
		unset( $GLOBALS['designsetgo_query_rendered_ids'] );
		$_GET = array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Restores request globals.
	 */
	public function tear_down() {
		$_GET = array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $GLOBALS['designsetgo_query_rendered_ids'], $GLOBALS['designsetgo_query_force_multi'] );
		$_SERVER['REQUEST_URI'] = '/';
		wp_reset_postdata();
		parent::tear_down();
	}

	/**
	 * Query markup with the given filter siblings, one post per page.
	 *
	 * @param string $query_id Query ID.
	 * @param string $filters  Filter block markup.
	 * @return string
	 */
	private function query( $query_id, $filters = '' ) {
		return '<!-- wp:designsetgo/query {"queryId":"' . $query_id . '","perPage":1,"postType":"post"} -->'
			. $filters
			. '<!-- wp:designsetgo/query-results --><!-- wp:post-title /--><!-- /wp:designsetgo/query-results -->'
			. '<!-- wp:designsetgo/query-pagination /-->'
			. '<!-- /wp:designsetgo/query -->';
	}

	/**
	 * Filter block markup.
	 *
	 * @param string $kind  filterKind.
	 * @param array  $attrs Extra attributes.
	 * @return string
	 */
	private function filter( $kind, array $attrs = array() ) {
		$attrs = array_merge( array( 'filterKind' => $kind ), $attrs );
		return '<!-- wp:designsetgo/query-filter ' . wp_json_encode( $attrs ) . ' /-->';
	}

	/**
	 * Every filter kind, for one Query.
	 *
	 * @return string
	 */
	private function all_filters() {
		return $this->filter(
			'checkbox',
			array(
				'taxonomy'  => 'category',
				'paramName' => 'filter_category',
			)
		)
			. $this->filter( 'search', array( 'paramName' => 'q' ) )
			. $this->filter( 'active' )
			. $this->filter( 'reset' );
	}

	/**
	 * Categories "city" and "coast", two posts each.
	 */
	private function create_posts() {
		foreach ( array( 'city', 'coast' ) as $slug ) {
			$term = self::factory()->category->create(
				array(
					'name' => ucfirst( $slug ),
					'slug' => $slug,
				)
			);
			self::factory()->post->create_many(
				2,
				array(
					'post_status'   => 'publish',
					'post_category' => array( $term ),
				)
			);
		}
	}

	/**
	 * Render a page as its visitor would, with $_GET set to $get.
	 *
	 * @param string $content Page content.
	 * @param array  $get     Query args.
	 * @return string HTML.
	 */
	private function render_page( $content, array $get = array() ) {
		$page = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_content' => $content,
			)
		);
		$_GET                   = array_merge( array( 'page_id' => (string) $page->ID ), $get ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_SERVER['REQUEST_URI'] = '/?' . http_build_query( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$GLOBALS['post']        = $page;
		setup_postdata( $page );
		return do_blocks( $content );
	}

	/**
	 * The HTML of one Query's region.
	 *
	 * @param string $html     Page HTML.
	 * @param string $query_id Query ID.
	 * @return string
	 */
	private function region( $html, $query_id ) {
		$start = strpos( $html, 'data-dsgo-query-region="' . $query_id . '"' );
		$this->assertNotFalse( $start, "Region {$query_id} must render." );
		$next = strpos( $html, 'data-dsgo-query-region="', $start + 1 );
		return false === $next ? substr( $html, $start ) : substr( $html, $start, $next - $start );
	}

	/**
	 * Decoded hrefs of elements with the given class.
	 *
	 * @param string $html  HTML.
	 * @param string $class Class name.
	 * @return string[]
	 */
	private function hrefs( $html, $class ) {
		preg_match_all( '#<a href="([^"]*)"[^>]*class="' . preg_quote( $class, '#' ) . '"#', $html, $m );
		return array_map(
			static function ( $h ) {
				return urldecode( html_entity_decode( $h ) );
			},
			$m[1]
		);
	}

	public function test_one_query_page_writes_plain_names() {
		$this->create_posts();
		$html = $this->render_page( $this->query( self::A, $this->all_filters() ) );

		$this->assertStringContainsString( 'name="filter_category[]"', $html );
		$this->assertStringContainsString( 'name="q"', $html );
		$this->assertStringNotContainsString( '__' . self::A . '[]', $html );
		$this->assertStringContainsString( 'data-dsgo-scoped="1"', $html );
	}

	public function test_multi_query_page_writes_scoped_names() {
		$this->create_posts();
		$html = $this->render_page( $this->query( self::A, $this->all_filters() ) . $this->query( self::B, $this->all_filters() ) );

		$this->assertStringContainsString( 'name="filter_category__' . self::A . '[]"', $html );
		$this->assertStringContainsString( 'name="q__' . self::B . '"', $html );
	}

	public function test_no_js_form_carries_the_rest_of_the_url() {
		$this->create_posts();
		$html = $this->render_page(
			$this->query( self::A, $this->all_filters() ) . $this->query( self::B, $this->all_filters() ),
			array(
				'filter_category'               => array( 'city' ),
				'filter_category__' . self::B => array( 'coast' ),
			)
		);
		$a = $this->region( $html, self::A );

		// Submitting A's search keeps page_id, B's filter and the bare
		// bookmark (which B still reads).
		$this->assertMatchesRegularExpression( '#<input type="hidden" name="page_id" value="\d+" />#', $a );
		$this->assertStringContainsString( '<input type="hidden" name="filter_category__' . self::B . '[]" value="coast" />', $a );
		$this->assertStringContainsString( '<input type="hidden" name="filter_category[]" value="city" />', $a );
		// An unticked list still overrides the bookmark for A.
		$this->assertStringContainsString( '<input type="hidden" name="filter_category__' . self::A . '[]" value="" />', $a );
	}

	public function test_no_js_form_resets_only_its_own_page() {
		$this->create_posts();
		$html = $this->render_page(
			$this->query( self::A, $this->all_filters() ) . $this->query( self::B ),
			array( 'paged' => '2' )
		);

		$this->assertStringContainsString( '<input type="hidden" name="qpage__' . self::A . '" value="1" />', $this->region( $html, self::A ) );
	}

	public function test_bookmark_checkboxes_and_chips_seed_from_the_bare_value() {
		$this->create_posts();
		$html = $this->render_page(
			$this->query( self::A, $this->all_filters() ) . $this->query( self::B, $this->all_filters() ),
			array( 'filter_category' => array( 'city' ) )
		);
		$a = $this->region( $html, self::A );

		$this->assertMatchesRegularExpression( '#value="city" checked#', $a );
		$this->assertStringContainsString( 'data-dsgo-filter-param="filter_category" data-dsgo-filter-value="city"', $a );
	}

	public function test_chips_hide_a_bare_value_this_query_overrides() {
		$this->create_posts();
		$html = $this->render_page(
			$this->query( self::A, $this->all_filters() ) . $this->query( self::B, $this->all_filters() ),
			array(
				'filter_category'               => array( 'city' ),
				'filter_category__' . self::A => array( 'coast' ),
			)
		);
		$a = $this->region( $html, self::A );
		$b = $this->region( $html, self::B );

		$this->assertStringContainsString( 'data-dsgo-filter-value="coast"', $a );
		$this->assertStringNotContainsString( 'data-dsgo-filter-value="city"', $a );
		$this->assertStringContainsString( 'data-dsgo-filter-value="city"', $b );
		$this->assertStringNotContainsString( 'data-dsgo-filter-value="coast"', $b );
	}

	public function test_chip_link_removes_the_value_for_this_query_only() {
		$this->create_posts();
		$html  = $this->render_page(
			$this->query( self::A, $this->all_filters() ) . $this->query( self::B, $this->all_filters() ),
			array( 'filter_category' => array( 'city' ) )
		);
		$chips = $this->hrefs( $this->region( $html, self::A ), 'dsgo-query-filter__chip' );

		$this->assertCount( 1, $chips );
		$this->assertStringContainsString( 'filter_category[]=city', $chips[0], 'B keeps the bookmark.' );
		$this->assertStringContainsString( 'filter_category__' . self::A . '[]=', $chips[0], 'A shadows it.' );
		$this->assertStringContainsString( 'page_id=', $chips[0] );
	}

	public function test_reset_keeps_the_other_querys_filters() {
		$this->create_posts();
		$html  = $this->render_page(
			$this->query( self::A, $this->all_filters() ) . $this->query( self::B, $this->all_filters() ),
			array(
				'q__' . self::A               => 'the',
				'filter_category__' . self::B => array( 'coast' ),
			)
		);
		$reset = $this->hrefs( $this->region( $html, self::A ), 'dsgo-query-filter__reset' );

		$this->assertCount( 1, $reset );
		$this->assertStringNotContainsString( 'q__' . self::A, $reset[0] );
		$this->assertStringContainsString( 'filter_category__' . self::B . '[]=coast', $reset[0] );
		$this->assertStringContainsString( 'page_id=', $reset[0] );
	}

	public function test_reset_on_one_query_page_removes_plain_keys() {
		$this->create_posts();
		$html  = $this->render_page(
			$this->query( self::A, $this->all_filters() ),
			array(
				'q'     => 'the',
				'paged' => '2',
			)
		);
		$reset = $this->hrefs( $html, 'dsgo-query-filter__reset' );

		$this->assertMatchesRegularExpression( '#^/\?page_id=\d+$#', $reset[0] );
	}

	public function test_each_query_pages_independently() {
		$this->create_posts();
		$html = $this->render_page(
			$this->query( self::A ) . $this->query( self::B ),
			array( 'qpage__' . self::A => '3' )
		);

		$this->assertSame( 3, designsetgo_query_get_last_state( self::A )['page'] );
		$this->assertSame( 1, designsetgo_query_get_last_state( self::B )['page'] );
		$this->assertStringContainsString( 'qpage__' . self::A . '=2', urldecode( html_entity_decode( $this->region( $html, self::A ) ) ) );
		$this->assertStringContainsString( 'qpage__' . self::B . '=2', urldecode( html_entity_decode( $this->region( $html, self::B ) ) ) );
	}

	public function test_one_query_page_keeps_wordpress_paging() {
		$this->create_posts();
		$html = $this->render_page( $this->query( self::A ) );

		$this->assertStringNotContainsString( 'qpage__', $html );
		$this->assertStringContainsString( 'paged=2', urldecode( html_entity_decode( $html ) ) );
	}

	public function test_opt_out_filter_restores_shared_params() {
		add_filter( 'designsetgo_query_scope_params', '__return_false' );
		$this->create_posts();
		$html = $this->render_page( $this->query( self::A, $this->all_filters() ) . $this->query( self::B, $this->all_filters() ) );
		remove_filter( 'designsetgo_query_scope_params', '__return_false' );

		$this->assertStringContainsString( 'name="filter_category[]"', $html );
		$this->assertStringNotContainsString( '__' . self::A, preg_replace( '/data-dsgo-query[\w-]*="[^"]*"|data-wp-context=\'[^\']*\'|id="[^"]*"/', '', $html ) );
		$this->assertStringNotContainsString( 'data-dsgo-scoped', $html );
		$this->assertStringNotContainsString( 'qpage__', $html );
	}

	public function test_rest_refresh_uses_scoped_names_when_the_page_has_other_queries() {
		$this->create_posts();
		$html = $this->render_page( $this->query( self::A, $this->all_filters() ) );
		preg_match( '#data-dsgo-blobs-for="' . self::A . '" data-dsgo-refresh-source="([A-Za-z0-9+/=]+)" data-dsgo-signature="([a-f0-9]{64})"#', $html, $m );
		$this->assertNotEmpty( $m, 'The region must carry a signed refresh source.' );
		wp_reset_postdata();
		unset( $GLOBALS['designsetgo_query_rendered_ids'] );
		$_GET = array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
		$request->set_param( 'queryId', self::A );
		$request->set_param( 'source', $m[1] );
		$request->set_param( 'signature', $m[2] );
		$request->set_param(
			'params',
			array(
				'filter_category'               => array( 'city' ),
				'filter_category__' . self::A => array( '' ),
				'q__' . self::A               => 'post',
				'q__' . self::B               => 'ignored',
			)
		);
		$request->set_param( 'multiQuery', true );
		$request->set_param( 'currentUrl', home_url( '/?page_id=7&filter_category__' . self::B . '[]=coast' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$out = $response->get_data()['html'];
		$this->assertStringContainsString( 'name="filter_category__' . self::A . '[]"', $out );
		$this->assertStringContainsString( 'value="post"', $out, 'The own scoped search reaches the render.' );
		$this->assertDoesNotMatchRegularExpression( '#value="city" checked#', $out, 'The empty scoped key clears the bookmark for this Query.' );
		$this->assertSame( 4, $response->get_data()['totalItems'] );
		$this->assertStringContainsString( '<input type="hidden" name="page_id" value="7" />', $out, 'Re-rendered forms carry the page URL, not the endpoint’s.' );
		$this->assertStringContainsString( '<input type="hidden" name="filter_category__' . self::B . '[]" value="coast" />', $out );
		$this->assertArrayNotHasKey( 'designsetgo_query_force_multi', $GLOBALS, 'The flag must not outlive the request.' );
	}
}
