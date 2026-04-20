<?php
/**
 * PHPUnit tests for admin-only filter REST routes:
 *   GET  /designsetgo/v1/query/filter-status
 *   POST /designsetgo/v1/query/filter-rebuild
 *   GET  /designsetgo/v1/query/filters
 *   DELETE /designsetgo/v1/query/filters
 *
 * @package DesignSetGo
 * @group query-block
 */

use DesignSetGo\Blocks\Query\FilterRegistry;
use DesignSetGo\Blocks\Query\FilterIndex;

class DesignSetGo_Query_Filter_Admin_Routes_Test extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private $editor_id;

	public function set_up(): void {
		parent::set_up();
		$this->admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		delete_option( FilterRegistry::OPTION );
		delete_option( FilterIndex::OPTION_STATUS );
		parent::tear_down();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Route registration
	// ─────────────────────────────────────────────────────────────────────

	public function test_filter_status_route_is_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/designsetgo/v1/query/filter-status', $routes );
	}

	public function test_filter_rebuild_route_is_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/designsetgo/v1/query/filter-rebuild', $routes );
	}

	public function test_filters_list_route_is_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/designsetgo/v1/query/filters', $routes );
	}

	// ─────────────────────────────────────────────────────────────────────
	// GET /query/filter-status
	// ─────────────────────────────────────────────────────────────────────

	public function test_filter_status_returns_200_for_admin() {
		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/filter-status' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'total_rows', $data );
		$this->assertArrayHasKey( 'in_progress', $data );
		$this->assertArrayHasKey( 'last_rebuilt_at', $data );
		$this->assertArrayHasKey( 'processed', $data );
	}

	public function test_filter_status_returns_403_for_editor() {
		wp_set_current_user( $this->editor_id );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/filter-status' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_filter_status_rejects_missing_nonce() {
		wp_set_current_user( $this->admin_id );

		$request  = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/filter-status' );
		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_filter_status_rejects_anonymous() {
		wp_set_current_user( 0 );

		$request  = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/filter-status' );
		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	// ─────────────────────────────────────────────────────────────────────
	// POST /query/filter-rebuild
	// ─────────────────────────────────────────────────────────────────────

	public function test_filter_rebuild_returns_200_for_admin() {
		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/filter-rebuild' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertContains( $data['status'], array( 'complete', 'error' ) );
	}

	public function test_filter_rebuild_returns_403_for_editor() {
		wp_set_current_user( $this->editor_id );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/filter-rebuild' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	// ─────────────────────────────────────────────────────────────────────
	// GET /query/filters
	// ─────────────────────────────────────────────────────────────────────

	public function test_filters_list_returns_empty_array_when_none_registered() {
		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/filters' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
		$this->assertEmpty( $response->get_data() );
	}

	public function test_filters_list_returns_registered_filters() {
		FilterRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		FilterRegistry::register( 'price', array( 'type' => 'meta', 'source' => '_price' ) );

		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/filters' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'category', $data );
		$this->assertArrayHasKey( 'price', $data );
	}

	public function test_filters_list_returns_403_for_editor() {
		wp_set_current_user( $this->editor_id );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/filters' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	// ─────────────────────────────────────────────────────────────────────
	// DELETE /query/filters
	// ─────────────────────────────────────────────────────────────────────

	public function test_filter_unregister_removes_filter() {
		FilterRegistry::register( 'price', array( 'type' => 'meta', 'source' => '_price' ) );

		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'DELETE', '/designsetgo/v1/query/filters' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'filter_key', 'price' );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['unregistered'] );
		$this->assertNull( FilterRegistry::get( 'price' ) );
	}

	public function test_filter_unregister_returns_404_for_unknown_key() {
		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'DELETE', '/designsetgo/v1/query/filters' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'filter_key', 'nonexistent' );

		$response = rest_do_request( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_filter_unregister_returns_403_for_editor() {
		FilterRegistry::register( 'price', array( 'type' => 'meta', 'source' => '_price' ) );

		wp_set_current_user( $this->editor_id );

		$request = new \WP_REST_Request( 'DELETE', '/designsetgo/v1/query/filters' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'filter_key', 'price' );

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );

		// Verify filter was NOT removed.
		$this->assertNotNull( FilterRegistry::get( 'price' ) );
	}

	public function test_filter_unregister_rejects_missing_nonce() {
		FilterRegistry::register( 'price', array( 'type' => 'meta', 'source' => '_price' ) );

		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'DELETE', '/designsetgo/v1/query/filters' );
		$request->set_param( 'filter_key', 'price' );

		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status() );
	}
}
