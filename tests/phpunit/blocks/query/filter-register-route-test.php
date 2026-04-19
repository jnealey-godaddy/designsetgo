<?php
/**
 * PHPUnit tests for /designsetgo/v1/query/facet-register REST route.
 *
 * @package DesignSetGo
 * @group query-block
 */

use DesignSetGo\Blocks\Query\FacetRegistry;

class DesignSetGo_Query_Facet_Register_Route_Test extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( FacetRegistry::OPTION );
		parent::tear_down();
	}

	public function test_route_is_registered() {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/designsetgo/v1/query/facet-register', $routes );
	}

	public function test_admin_can_register_facet() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-register' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'facet_key', 'category' );
		$request->set_param( 'config', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['registered'] );
		$this->assertArrayHasKey( 'category', get_option( 'dsgo_query_facets' ) );
	}

	public function test_response_contains_registered_config() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-register' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'facet_key', 'post_tag' );
		$request->set_param( 'config', array( 'type' => 'taxonomy', 'source' => 'post_tag' ) );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'post_tag', $data['facet_key'] );
		$this->assertIsArray( $data['config'] );
		$this->assertSame( 'taxonomy', $data['config']['type'] );
		$this->assertSame( 'post_tag', $data['config']['source'] );
	}

	public function test_editor_is_denied() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-register' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'facet_key', 'category' );
		$request->set_param( 'config', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_subscriber_is_denied() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-register' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'facet_key', 'category' );
		$request->set_param( 'config', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertArrayNotHasKey( 'category', get_option( 'dsgo_query_facets', array() ) );
	}

	public function test_missing_params_return_400() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-register' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'facet_key', 'category' );
		$request->set_param( 'config', array() ); // missing type + source

		$response = rest_do_request( $request );
		$this->assertSame( 400, $response->get_status() );
	}

	public function test_missing_source_returns_400() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-register' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'facet_key', 'category' );
		$request->set_param( 'config', array( 'type' => 'taxonomy' ) ); // missing source

		$response = rest_do_request( $request );
		$this->assertSame( 400, $response->get_status() );
	}

	public function test_invalid_type_returns_400() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-register' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'facet_key', 'category' );
		$request->set_param( 'config', array( 'type' => 'invalid_type', 'source' => 'category' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'dsgo_facet_invalid_type', $response->get_data()['code'] );
	}

	public function test_no_nonce_is_rejected() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-register' );
		$request->set_param( 'facet_key', 'category' );
		$request->set_param( 'config', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_anonymous_user_is_rejected() {
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-register' );
		$request->set_param( 'facet_key', 'category' );
		$request->set_param( 'config', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status() );
	}
}
