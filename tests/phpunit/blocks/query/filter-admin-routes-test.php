<?php
/**
 * PHPUnit tests for admin-only facet REST routes:
 *   GET  /designsetgo/v1/query/facet-status
 *   POST /designsetgo/v1/query/facet-rebuild
 *   GET  /designsetgo/v1/query/facets
 *   DELETE /designsetgo/v1/query/facets
 *
 * @package DesignSetGo
 * @group query-block
 */

use DesignSetGo\Blocks\Query\FacetRegistry;
use DesignSetGo\Blocks\Query\FacetIndex;

class DesignSetGo_Query_Facet_Admin_Routes_Test extends WP_UnitTestCase {

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
		delete_option( FacetRegistry::OPTION );
		delete_option( FacetIndex::OPTION_STATUS );
		parent::tear_down();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Route registration
	// ─────────────────────────────────────────────────────────────────────

	public function test_facet_status_route_is_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/designsetgo/v1/query/facet-status', $routes );
	}

	public function test_facet_rebuild_route_is_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/designsetgo/v1/query/facet-rebuild', $routes );
	}

	public function test_facets_list_route_is_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/designsetgo/v1/query/facets', $routes );
	}

	// ─────────────────────────────────────────────────────────────────────
	// GET /query/facet-status
	// ─────────────────────────────────────────────────────────────────────

	public function test_facet_status_returns_200_for_admin() {
		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/facet-status' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'total_rows', $data );
		$this->assertArrayHasKey( 'in_progress', $data );
		$this->assertArrayHasKey( 'last_rebuilt_at', $data );
		$this->assertArrayHasKey( 'processed', $data );
	}

	public function test_facet_status_returns_403_for_editor() {
		wp_set_current_user( $this->editor_id );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/facet-status' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_facet_status_rejects_missing_nonce() {
		wp_set_current_user( $this->admin_id );

		$request  = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/facet-status' );
		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_facet_status_rejects_anonymous() {
		wp_set_current_user( 0 );

		$request  = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/facet-status' );
		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	// ─────────────────────────────────────────────────────────────────────
	// POST /query/facet-rebuild
	// ─────────────────────────────────────────────────────────────────────

	public function test_facet_rebuild_returns_200_for_admin() {
		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-rebuild' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertContains( $data['status'], array( 'complete', 'error' ) );
	}

	public function test_facet_rebuild_returns_403_for_editor() {
		wp_set_current_user( $this->editor_id );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-rebuild' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	// ─────────────────────────────────────────────────────────────────────
	// GET /query/facets
	// ─────────────────────────────────────────────────────────────────────

	public function test_facets_list_returns_empty_array_when_none_registered() {
		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/facets' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
		$this->assertEmpty( $response->get_data() );
	}

	public function test_facets_list_returns_registered_facets() {
		FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		FacetRegistry::register( 'price', array( 'type' => 'meta', 'source' => '_price' ) );

		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/facets' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'category', $data );
		$this->assertArrayHasKey( 'price', $data );
	}

	public function test_facets_list_returns_403_for_editor() {
		wp_set_current_user( $this->editor_id );

		$request = new \WP_REST_Request( 'GET', '/designsetgo/v1/query/facets' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	// ─────────────────────────────────────────────────────────────────────
	// DELETE /query/facets
	// ─────────────────────────────────────────────────────────────────────

	public function test_facet_unregister_removes_facet() {
		FacetRegistry::register( 'price', array( 'type' => 'meta', 'source' => '_price' ) );

		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'DELETE', '/designsetgo/v1/query/facets' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'facet_key', 'price' );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['unregistered'] );
		$this->assertNull( FacetRegistry::get( 'price' ) );
	}

	public function test_facet_unregister_returns_404_for_unknown_key() {
		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'DELETE', '/designsetgo/v1/query/facets' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'facet_key', 'nonexistent' );

		$response = rest_do_request( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_facet_unregister_returns_403_for_editor() {
		FacetRegistry::register( 'price', array( 'type' => 'meta', 'source' => '_price' ) );

		wp_set_current_user( $this->editor_id );

		$request = new \WP_REST_Request( 'DELETE', '/designsetgo/v1/query/facets' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'facet_key', 'price' );

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );

		// Verify facet was NOT removed.
		$this->assertNotNull( FacetRegistry::get( 'price' ) );
	}

	public function test_facet_unregister_rejects_missing_nonce() {
		FacetRegistry::register( 'price', array( 'type' => 'meta', 'source' => '_price' ) );

		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'DELETE', '/designsetgo/v1/query/facets' );
		$request->set_param( 'facet_key', 'price' );

		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status() );
	}
}
