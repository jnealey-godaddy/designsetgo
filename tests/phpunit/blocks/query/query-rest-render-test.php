<?php
/**
 * @group query-block
 */
class DesignSetGo_Query_Rest_Test extends WP_UnitTestCase {

	public function test_route_registers() {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/designsetgo/v1/query/render', $routes );
		$this->assertArrayHasKey( '/designsetgo/v1/query/render-preview', $routes );
	}

	public function test_rejects_anonymous_requests() {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render-preview' );
		$request->set_param( 'queryId', 'abc' );
		$request->set_param( 'attributes', array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 3 ) );
		$request->set_param( 'page', 2 );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Serialized query markup with a one-item-per-page post template.
	 *
	 * @param string $query_id Query ID.
	 * @return string Block markup.
	 */
	private function query_markup( $query_id ) {
		return '<!-- wp:designsetgo/query {"queryId":"' . $query_id . '","perPage":1} -->'
			. '<!-- wp:designsetgo/query-results --><!-- wp:post-title /--><!-- /wp:designsetgo/query-results -->'
			. '<!-- /wp:designsetgo/query -->';
	}

	/**
	 * Render a post's content the way its page does.
	 *
	 * @param WP_Post     $post    Post being viewed.
	 * @param string|null $content Content to render; defaults to the post's own.
	 * @return string Rendered HTML.
	 */
	private function render_as_content( $post, $content = null ) {
		$GLOBALS['post'] = $post;
		setup_postdata( $post );
		$html = apply_filters( 'the_content', null === $content ? $post->post_content : $content );
		wp_reset_postdata();

		return $html;
	}

	/**
	 * Pull the signed refresh source a rendered query region carries.
	 *
	 * @param string $html     Rendered HTML.
	 * @param string $query_id Query ID.
	 * @return array{source: string, signature: string}
	 */
	private function refresh_source( $html, $query_id ) {
		$pattern = '#data-dsgo-blobs-for="' . preg_quote( $query_id, '#' ) . '" data-dsgo-refresh-source="([A-Za-z0-9+/=]+)" data-dsgo-signature="([a-f0-9]{64})"#';
		$this->assertSame( 1, preg_match( $pattern, $html, $match ), 'A rendered query must carry a signed refresh source.' );

		return array(
			'source'    => $match[1],
			'signature' => $match[2],
		);
	}

	/**
	 * Ask the public route to refresh a query, as view.js does.
	 *
	 * @param string $query_id Query ID.
	 * @param array  $source   Signed refresh source.
	 * @param int    $page     Page to render.
	 * @return WP_REST_Response
	 */
	private function refresh( $query_id, array $source, $page = 1 ) {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
		$request->set_param( 'queryId', $query_id );
		$request->set_param( 'source', $source['source'] );
		$request->set_param( 'signature', $source['signature'] );
		$request->set_param( 'page', $page );

		return rest_get_server()->dispatch( $request );
	}

	public function test_public_refresh_renders_a_query_saved_in_post_content() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$page = self::factory()->post->create_and_get( array( 'post_type' => 'page', 'post_content' => $this->query_markup( 'in-content' ) ) );
		$source = $this->refresh_source( $this->render_as_content( $page ), 'in-content' );

		wp_set_current_user( 0 );
		$response = $this->refresh( 'in-content', $source, 2 );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( 'wp-block-post-title', $response->get_data()['html'] );
	}

	public function test_public_refresh_renders_a_query_from_a_block_template() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		// Templates and template parts render through do_blocks(), never the_content.
		$source = $this->refresh_source( do_blocks( $this->query_markup( 'in-template' ) ), 'in-template' );

		wp_set_current_user( 0 );
		$response = $this->refresh( 'in-template', $source, 2 );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( 'wp-block-post-title', $response->get_data()['html'] );
	}

	public function test_public_refresh_renders_a_query_inside_a_synced_pattern() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$pattern_id = self::factory()->post->create( array( 'post_type' => 'wp_block', 'post_content' => $this->query_markup( 'in-pattern' ) ) );
		$page       = self::factory()->post->create_and_get( array( 'post_type' => 'page', 'post_content' => '<!-- wp:block {"ref":' . $pattern_id . '} /-->' ) );
		$source     = $this->refresh_source( $this->render_as_content( $page ), 'in-pattern' );

		wp_set_current_user( 0 );
		$response = $this->refresh( 'in-pattern', $source, 2 );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_public_refresh_rejects_a_tampered_source() {
		$source  = $this->refresh_source( do_blocks( $this->query_markup( 'tampered' ) ), 'tampered' );
		$decoded = json_decode( base64_decode( $source['source'] ), true );
		$decoded['attributes']['postType'] = 'shop_coupon';
		$source['source'] = base64_encode( wp_json_encode( $decoded ) );

		wp_set_current_user( 0 );
		$response = $this->refresh( 'tampered', $source );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_public_refresh_rejects_unsigned_query_settings() {
		// The 2.7.3 contract: the browser sent the query settings to render.
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
		$request->set_param( 'queryId', 'unsigned' );
		$request->set_param( 'attributes', array( 'source' => 'posts', 'postType' => 'shop_coupon', 'perPage' => 100 ) );
		$request->set_param( 'innerBlocks', '<!-- wp:post-title /-->' );

		wp_set_current_user( 0 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_public_refresh_hides_a_private_source_from_a_subscriber() {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$page   = self::factory()->post->create_and_get( array( 'post_type' => 'page', 'post_status' => 'private', 'post_content' => $this->query_markup( 'private-source' ) ) );
		$source = $this->refresh_source( $this->render_as_content( $page ), 'private-source' );

		$this->assertSame( 200, $this->refresh( 'private-source', $source )->get_status(), 'Someone who can read the page can refresh its query.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 404, $this->refresh( 'private-source', $source )->get_status() );
	}

	public function test_public_refresh_requires_the_source_post_password() {
		$page   = self::factory()->post->create_and_get( array( 'post_type' => 'page', 'post_password' => 'secret', 'post_content' => $this->query_markup( 'protected-source' ) ) );
		$source = $this->refresh_source( $this->render_as_content( $page ), 'protected-source' );

		wp_set_current_user( 0 );
		$response = $this->refresh( 'protected-source', $source );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_refresh_response_carries_a_signed_source_for_the_next_page() {
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );
		$source = $this->refresh_source( do_blocks( $this->query_markup( 'chained' ) ), 'chained' );

		wp_set_current_user( 0 );
		$first = $this->refresh( 'chained', $source, 2 );
		$this->assertSame( 200, $first->get_status() );

		// view.js swaps the region, so the next Load more sends this one.
		$next   = $this->refresh_source( $first->get_data()['html'], 'chained' );
		$second = $this->refresh( 'chained', $next, 3 );

		$this->assertSame( 200, $second->get_status() );
		$this->assertNotSame( $first->get_data()['html'], $second->get_data()['html'] );
	}

	public function test_first_paint_sends_a_rest_nonce_only_to_logged_in_users() {
		wp_set_current_user( 0 );
		$this->assertStringContainsString( '"nonce":""', do_blocks( $this->query_markup( 'visitor-nonce' ) ), 'A cached page outlives its nonce, and core rejects a stale one outright.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertStringNotContainsString( '"nonce":""', do_blocks( $this->query_markup( 'member-nonce' ) ) );
	}

	public function test_rejects_logged_in_user_without_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render-preview' );
		$request->set_param( 'queryId', 'abc' );
		$request->set_param( 'attributes', array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 3 ) );
		$request->set_param( 'page', 2 );
		// No X-WP-Nonce header.

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_rejects_user_without_read_capability() {
		// Create a user with no role so they lack the read cap entirely,
		// exercising the current_user_can('read') gate in check_permission().
		// Using role '' is more reliable than remove_cap() in unit tests because
		// remove_cap() cannot override role-inherited capabilities.
		$user_id = self::factory()->user->create( array( 'role' => '' ) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render-preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'queryId', 'abc' );
		$request->set_param( 'attributes', array( 'source' => 'posts' ) );
		$request->set_param( 'page', 1 );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_returns_html_shell_for_valid_request() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render-preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'queryId', 'abc' );
		$request->set_param( 'attributes', array(
			'source'   => 'posts',
			'postType' => 'post',
			'perPage'  => 3,
		) );
		$request->set_param( 'page', 1 );
		$request->set_param( 'innerBlocks', '' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'html', $data );
		$this->assertArrayHasKey( 'totalPages', $data );
		$this->assertArrayHasKey( 'totalItems', $data );
		$this->assertIsString( $data['html'] );
	}

	public function test_render_rejects_missing_query_id_with_400() {
		// queryId is declared required on the route, so WP REST should reject
		// the request at the schema layer with a 400 before the handler runs.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render-preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'attributes', array( 'source' => 'posts', 'perPage' => 1 ) );
		$request->set_param( 'page', 1 );
		// No queryId.

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame(
			400,
			$response->get_status(),
			'Missing required queryId must be rejected by REST schema validation with 400.'
		);
	}

	public function test_render_coerces_negative_page_to_one() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render-preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'queryId', 'neg-page' );
		$request->set_param( 'attributes', array( 'source' => 'posts', 'perPage' => 5 ) );
		$request->set_param( 'page', -42 );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		// No 500 / no fatal; page coerces to 1 via max( 1, ... ).
	}

	public function test_render_params_overlay_is_sanitised_before_get() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render-preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'queryId', 'sanitize-test' );
		$request->set_param( 'attributes', array( 'source' => 'posts', 'perPage' => 5 ) );
		$request->set_param( 'page', 1 );
		// Raw REST-supplied filter value containing a script tag.
		$request->set_param(
			'params',
			array(
				'filter_category' => array( '<script>alert(1)</script>' ),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		// Sanitisation happens at the overlay boundary — confirm $_GET was
		// restored (and therefore our overlay sanitised value never leaked).
		$this->assertArrayNotHasKey(
			'filter_category',
			$_GET,
			'handle_render must restore the original $_GET after the try/finally.'
		);
	}

	public function test_rest_output_matches_direct_region_render_call() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$attributes = array(
			'source'   => 'posts',
			'postType' => 'post',
			'perPage'  => 5,
		);
		$inner = '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->';

		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		// The REST controller now delegates to designsetgo_query_render_region()
		// so we compare against that helper (not the bare designsetgo_query_render).
		$direct = designsetgo_query_render_region(
			$attributes,
			array(
				'query_id'   => 'x',
				'page'       => 1,
				'inner_html' => $inner,
			)
		);

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render-preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'queryId', 'x' );
		$request->set_param( 'attributes', $attributes );
		$request->set_param( 'page', 1 );
		$request->set_param( 'innerBlocks', $inner );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $direct['html'], $data['html'] );
		$this->assertSame( $direct['totalItems'], $data['totalItems'] );
		$this->assertSame( $direct['totalPages'], $data['totalPages'] );
	}

	/**
	 * Build an authenticated editor-preview request.
	 *
	 * @param array $attributes Query attributes.
	 * @return WP_REST_Request
	 */
	private function preview_request( array $attributes ) {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render-preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'queryId', 'preview' );
		$request->set_param( 'attributes', $attributes );
		$request->set_param( 'innerBlocks', '<!-- wp:post-title /-->' );

		return $request;
	}

	public function test_preview_route_rejects_a_subscriber() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = rest_get_server()->dispatch( $this->preview_request( array( 'source' => 'posts', 'postType' => 'post' ) ) );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_preview_route_refuses_a_post_type_the_user_cannot_see() {
		register_post_type(
			'dsgo_hidden_type',
			array(
				'public'          => false,
				'capability_type' => 'dsgo_hidden_type',
				'map_meta_cap'    => true,
			)
		);
		self::factory()->post->create( array( 'post_type' => 'dsgo_hidden_type', 'post_title' => 'HIDDEN-TITLE' ) );

		try {
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
			$contributor = rest_get_server()->dispatch( $this->preview_request( array( 'source' => 'posts', 'postType' => 'dsgo_hidden_type' ) ) );
			// Unknown sources (and `current`) fall through to the posts renderer.
			$unknown_source = rest_get_server()->dispatch( $this->preview_request( array( 'source' => 'not-a-source', 'postType' => 'dsgo_hidden_type' ) ) );

			// A custom capability_type grants nobody its caps until something does,
			// the way WooCommerce grants shop managers the coupon caps.
			$manager = self::factory()->user->create( array( 'role' => 'administrator' ) );
			get_userdata( $manager )->add_cap( 'edit_dsgo_hidden_types' );
			wp_set_current_user( $manager );
			$admin = rest_get_server()->dispatch( $this->preview_request( array( 'source' => 'posts', 'postType' => 'dsgo_hidden_type' ) ) );
		} finally {
			unregister_post_type( 'dsgo_hidden_type' );
		}

		$this->assertSame( 403, $contributor->get_status() );
		$this->assertSame( 403, $unknown_source->get_status() );
		$this->assertSame( 200, $admin->get_status() );
		$this->assertStringContainsString( 'HIDDEN-TITLE', $admin->get_data()['html'] );
	}

	public function test_preview_output_carries_no_refresh_source() {
		// Signing editor-supplied settings would let a preview mint a source the
		// public route then trusts.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = rest_get_server()->dispatch( $this->preview_request( array( 'source' => 'posts', 'postType' => 'post' ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringNotContainsString( 'data-dsgo-refresh-source', $response->get_data()['html'] );
	}

	public function test_user_search_ignores_email_without_list_users() {
		self::factory()->user->create(
			array(
				'user_login'   => 'plainmember',
				'display_name' => 'Plain Member',
				'user_email'   => 'zq-private-address@example.test',
			)
		);
		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$attributes = array(
			'source' => 'users',
			'search' => 'zq-private-address',
		);

		// Core's own users endpoint searches email only for list_users.
		wp_set_current_user( 0 );
		$visitor = designsetgo_query_render_region( $attributes, array( 'query_id' => 'user-search' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$admin = designsetgo_query_render_region( $attributes, array( 'query_id' => 'user-search' ) );

		$this->assertSame( 0, $visitor['totalItems'] );
		$this->assertSame( 1, $admin['totalItems'] );
	}

	public function test_user_sort_by_email_needs_list_users() {
		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-users.php';

		// A sort order leaks addresses one character at a time to anyone who can
		// register an account and see where it lands. Core refuses it too.
		wp_set_current_user( 0 );
		$this->assertSame( 'display_name', designsetgo_query_sanitize_user_orderby( 'email' ) );
		$this->assertSame( 'display_name', designsetgo_query_sanitize_user_orderby( 'user_email' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 'user_email', designsetgo_query_sanitize_user_orderby( 'user_email' ) );
	}

	public function test_a_signed_source_only_verifies_on_the_site_that_signed_it() {
		// AUTH_SALT is network-wide on multisite, so the signature must also
		// name the site, or one site's query would render on another.
		$signed = \DesignSetGo\Blocks\Query\RefreshSource::sign( 'cross-site', array( 'source' => 'posts' ), '', 0 );
		$this->assertNotNull( \DesignSetGo\Blocks\Query\RefreshSource::verify( $signed['source'], $signed['signature'], 'cross-site' ) );

		$original_blog_id    = $GLOBALS['blog_id'];
		$GLOBALS['blog_id'] = $original_blog_id + 1;
		try {
			$other_site = \DesignSetGo\Blocks\Query\RefreshSource::verify( $signed['source'], $signed['signature'], 'cross-site' );
		} finally {
			$GLOBALS['blog_id'] = $original_blog_id;
		}

		$this->assertNull( $other_site );
	}

	public function test_a_nested_query_keeps_its_source_post_after_a_refresh() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$outer = '<!-- wp:designsetgo/query {"queryId":"outer","perPage":1} -->'
			. '<!-- wp:designsetgo/query-results -->' . $this->query_markup( 'inner' ) . '<!-- /wp:designsetgo/query-results -->'
			. '<!-- /wp:designsetgo/query -->';
		$page   = self::factory()->post->create_and_get( array( 'post_type' => 'page', 'post_status' => 'private', 'post_content' => $outer ) );
		$source = $this->refresh_source( $this->render_as_content( $page ), 'outer' );

		$response = $this->refresh( 'outer', $source, 2 );
		$this->assertSame( 200, $response->get_status() );
		$inner = $this->refresh_source( $response->get_data()['html'], 'inner' );

		// Rendered inside the private page, so it stays behind that page's gate.
		$this->assertSame( $page->ID, json_decode( base64_decode( $inner['source'] ), true )['sourcePostId'] );
	}
}
