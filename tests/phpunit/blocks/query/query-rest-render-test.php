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

	public function test_requires_nonce_for_write_like_scope() {
		$request  = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
		$request->set_param( 'queryId', 'abc' );
		$request->set_param( 'attributes', array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 3 ) );
		$request->set_param( 'page', 2 );
		// No nonce.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
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
}
