<?php
/**
 * PHPUnit tests for /designsetgo/v1/query/preview REST route.
 *
 * @package DesignSetGo
 * @group query-block
 */

class DesignSetGo_Query_Preview_Route_Test extends WP_UnitTestCase {

	/**
	 * Set up an editor-role user for each test.
	 */
	public function set_up(): void {
		parent::set_up();
		do_action( 'rest_api_init' );
	}

	// -------------------------------------------------------------------------
	// Route registration
	// -------------------------------------------------------------------------

	public function test_route_is_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/designsetgo/v1/query/preview', $routes );
	}

	// -------------------------------------------------------------------------
	// Permission checks
	// -------------------------------------------------------------------------

	public function test_anonymous_user_is_rejected() {
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/preview' );
		$request->set_param( 'attributes', array( 'source' => 'users' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_subscriber_is_rejected() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'attributes', array( 'source' => 'users' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_editor_is_allowed() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'attributes', array( 'source' => 'users' ) );

		$response = rest_do_request( $request );
		// Should be 200 (an array, possibly empty — no users created yet is fine).
		$this->assertSame( 200, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// Posts source — not needed error
	// -------------------------------------------------------------------------

	public function test_posts_source_returns_400() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'attributes', array( 'source' => 'posts' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'not_needed', $response->get_data()['code'] );
	}

	// -------------------------------------------------------------------------
	// Users source
	// -------------------------------------------------------------------------

	public function test_users_source_returns_items_array() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );
		$this->factory->user->create_many( 3, array( 'role' => 'subscriber' ) );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'attributes', array( 'source' => 'users', 'perPage' => 3 ) );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		$first = $data[0];
		$this->assertArrayHasKey( 'id', $first );
		$this->assertArrayHasKey( 'name', $first );
		$this->assertArrayHasKey( 'type', $first );
		$this->assertSame( 'user', $first['type'] );
	}

	public function test_users_source_respects_per_page() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );
		$this->factory->user->create_many( 10, array( 'role' => 'subscriber' ) );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'attributes', array( 'source' => 'users', 'perPage' => 4 ) );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 4, $data );
	}

	// -------------------------------------------------------------------------
	// Terms source
	// -------------------------------------------------------------------------

	public function test_terms_source_returns_items_array() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );
		$this->factory->category->create_many( 3 );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'attributes', array( 'source' => 'terms', 'taxonomy' => 'category', 'perPage' => 3 ) );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		$first = $data[0];
		$this->assertArrayHasKey( 'id', $first );
		$this->assertArrayHasKey( 'name', $first );
		$this->assertArrayHasKey( 'type', $first );
		$this->assertSame( 'term', $first['type'] );
	}

	public function test_terms_source_respects_per_page() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );
		$this->factory->category->create_many( 10 );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/preview' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'attributes', array( 'source' => 'terms', 'taxonomy' => 'category', 'perPage' => 5 ) );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		// WordPress includes 'Uncategorized' by default — just verify we get ≤5.
		$this->assertLessThanOrEqual( 5, count( $data ) );
	}
}
