<?php
/**
 * @group query-block
 */
class DesignSetGo_Query_Rest_Test extends WP_UnitTestCase {

	public function test_route_registers() {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/designsetgo/v1/query/render', $routes );
	}

	public function test_rejects_anonymous_requests() {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
		$request->set_param( 'queryId', 'abc' );
		$request->set_param( 'attributes', array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 3 ) );
		$request->set_param( 'page', 2 );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_rejects_logged_in_user_without_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
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

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
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

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
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

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
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
}
