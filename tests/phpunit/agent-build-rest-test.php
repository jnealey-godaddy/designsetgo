<?php
/**
 * REST coverage for the agent-build pending-tree handshake: permissions,
 * the GET pending/conflict contract, and the POST report status enum +
 * clearing rules.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Agent_Build\Build_REST;
use DesignSetGo\Abilities\Agent_Build\Build_Store;

/**
 * Build_REST tests.
 *
 * @group abilities
 * @group agent-build
 */
class Agent_Build_REST_Test extends WP_UnitTestCase {

	/**
	 * Store instance backing the route under test.
	 *
	 * @var Build_Store
	 */
	private $store;

	/**
	 * REST namespace + base for the route under test.
	 *
	 * @var string
	 */
	private $route_base = '/designsetgo/v1/agent-build/';

	/**
	 * Editor who owns the test post.
	 *
	 * @var int
	 */
	private $editor_id;

	/**
	 * Another editor, with no relation to the test post.
	 *
	 * @var int
	 */
	private $other_editor_id;

	/**
	 * Subscriber with no editing capabilities.
	 *
	 * @var int
	 */
	private $subscriber_id;

	/**
	 * Post the pending build targets.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up test fixtures and register REST routes.
	 */
	public function set_up() {
		parent::set_up();

		$this->store = new Build_Store();
		new Build_REST( $this->store );

		$this->editor_id       = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->other_editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->subscriber_id   = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_author' => $this->editor_id,
			)
		);

		rest_get_server()->override_by_default = true;
		do_action( 'rest_api_init' );
	}

	/**
	 * A minimal well-formed tree fixture for store() calls.
	 *
	 * @return array
	 */
	private function tree(): array {
		return array(
			'version' => 1,
			'blocks'  => array(
				array(
					'name'        => 'core/paragraph',
					'attrs'       => array( 'content' => 'Hello' ),
					'innerBlocks' => array(),
				),
			),
		);
	}

	/**
	 * GET requires edit_post; a subscriber is forbidden.
	 */
	public function test_get_forbidden_for_subscriber(): void {
		wp_set_current_user( $this->subscriber_id );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . $this->post_id ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * GET is forbidden for an editor who is not the post's author and lacks
	 * edit_others_posts (contributor-on-others'-post shape, modeled here
	 * with an editor for simplicity since both roles gate on edit_post).
	 */
	public function test_get_forbidden_for_unrelated_contributor(): void {
		$contributor_id = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$others_post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_author' => $this->editor_id,
			)
		);

		wp_set_current_user( $contributor_id );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . $others_post_id ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * GET 404s for a post that does not exist.
	 */
	public function test_get_404_for_missing_post(): void {
		wp_set_current_user( $this->editor_id );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . '999999' ) );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'rest_post_invalid_id', $response->as_error()->get_error_code() );
	}

	/**
	 * GET with nothing pending returns pending:false plus postStatus, 200.
	 */
	public function test_get_returns_pending_false_when_nothing_pending(): void {
		wp_set_current_user( $this->editor_id );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . $this->post_id ) );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( array( 'pending', 'postStatus' ), array_keys( $data ) );
		$this->assertFalse( $data['pending'] );
		$this->assertSame( 'publish', $data['postStatus'] );
	}

	/**
	 * GET with a pending build returns the tree, mode, conflict, postStatus
	 * and designContext, and editor as authorized editor can read it.
	 */
	public function test_get_returns_full_pending_payload(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );

		wp_set_current_user( $this->editor_id );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . $this->post_id ) );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['pending'] );
		$this->assertSame( $this->tree(), $data['tree'] );
		$this->assertSame( 'replace', $data['mode'] );
		$this->assertFalse( $data['conflict'] );
		$this->assertSame( 'publish', $data['postStatus'] );
		$this->assertArrayHasKey( 'designContext', $data );
		$this->assertArrayHasKey( 'settings', $data['designContext'] );
	}

	/**
	 * GET after wp_update_post() reports conflict:true.
	 */
	public function test_get_reports_conflict_after_post_update(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );

		// post_modified_gmt has one-second resolution.
		sleep( 1 );

		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => 'changed out from under the pending build',
			)
		);

		wp_set_current_user( $this->editor_id );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . $this->post_id ) );

		$this->assertTrue( $response->get_data()['conflict'] );
	}

	/**
	 * POST with an unknown status is rejected with 400.
	 */
	public function test_post_rejects_unknown_status(): void {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', $this->route_base . $this->post_id );
		$request->set_param( 'status', 'not_a_real_status' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * POST finished clears the pending tree but keeps the report.
	 */
	public function test_post_finished_clears_pending(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );

		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'POST', $this->route_base . $this->post_id );
		$request->set_param( 'status', 'finished' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( $this->store->pending( $this->post_id ) );

		$report = $this->store->report( $this->post_id );
		$this->assertSame( 'finished', $report['status'] );
		$this->assertNotEmpty( $report['treeHash'] );
	}

	/**
	 * POST failed keeps the pending tree in place (a remote agent may want
	 * to retry against the same tree).
	 */
	public function test_post_failed_keeps_pending(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );

		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'POST', $this->route_base . $this->post_id );
		$request->set_param( 'status', 'failed' );
		$request->set_param(
			'invalid',
			array(
				array(
					'path'    => 'blocks[0]',
					'message' => 'could not place block',
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotNull( $this->store->pending( $this->post_id ) );

		$report = $this->store->report( $this->post_id );
		$this->assertSame( 'failed', $report['status'] );
		$this->assertSame( 'could not place block', $report['invalid'][0]['message'] );
	}

	/**
	 * A report body over 1 MB is rejected with a 413-style rest_invalid_param error.
	 */
	public function test_post_rejects_oversized_body(): void {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', $this->route_base . $this->post_id );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'status'   => 'failed',
					'findings' => array(
						array( 'message' => str_repeat( 'x', 2 * 1024 * 1024 ) ),
					),
				)
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 413, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code() );
	}

	/**
	 * POST is forbidden for a subscriber.
	 */
	public function test_post_forbidden_for_subscriber(): void {
		wp_set_current_user( $this->subscriber_id );

		$request = new WP_REST_Request( 'POST', $this->route_base . $this->post_id );
		$request->set_param( 'status', 'finished' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}
}
