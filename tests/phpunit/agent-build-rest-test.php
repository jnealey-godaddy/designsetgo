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
	 * A POST report request carrying the pending build's id (or a
	 * placeholder when nothing is pending, for tests that never reach the
	 * callback).
	 *
	 * @return WP_REST_Request
	 */
	private function report_request(): WP_REST_Request {
		$pending = $this->store->pending( $this->post_id );
		$request = new WP_REST_Request( 'POST', $this->route_base . $this->post_id );
		$request->set_param( 'buildId', null !== $pending ? $pending['buildId'] : 'no-build-pending' );

		return $request;
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
	 * GET is forbidden for a contributor who does not own the post and
	 * lacks edit_others_posts.
	 */
	public function test_get_forbidden_for_unrelated_contributor(): void {
		$contributor_id = self::factory()->user->create( array( 'role' => 'contributor' ) );

		wp_set_current_user( $contributor_id );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . $this->post_id ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * By contrast, an editor who does not own the post is still allowed -
	 * editors have edit_others_posts by default, so this is a positive
	 * case, not a forbidden one.
	 */
	public function test_get_allowed_for_unrelated_editor(): void {
		wp_set_current_user( $this->other_editor_id );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . $this->post_id ) );

		$this->assertSame( 200, $response->get_status() );
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
	 * GET names the build's submitter and whether the current user is them,
	 * so the editor only auto-saves a build for the person who submitted it.
	 */
	public function test_get_reports_submitter_and_is_submitter(): void {
		wp_set_current_user( $this->editor_id );
		$this->store->store( $this->post_id, $this->tree(), 'replace' );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . $this->post_id ) );
		$data     = $response->get_data();
		$this->assertSame( $this->editor_id, $data['submitter'] );
		$this->assertTrue( $data['isSubmitter'] );

		wp_set_current_user( $this->other_editor_id );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . $this->post_id ) );
		$data     = $response->get_data();
		$this->assertSame( $this->editor_id, $data['submitter'] );
		$this->assertFalse( $data['isSubmitter'] );
	}

	/**
	 * GET's raw JSON response keeps an empty node "attributes" as an object
	 * ("{}"), not an array ("[]"). PHP's json_decode( $json, true ) can't
	 * tell an empty JSON object from an empty JSON array - both become
	 * array() - so this has to be checked against the actual encoded JSON
	 * bytes, not an in-PHP array comparison (which would pass either way).
	 * Regression test for the {} -> [] round-trip bug Task 21's e2e spec
	 * found: an agent tree node with no attribute overrides parked fine via
	 * build-page, but the browser's checkTreeShape() then rejected it as
	 * "attributes must be a plain object when present" once GET handed it
	 * back as [].
	 */
	public function test_get_response_json_keeps_empty_attributes_as_object(): void {
		$this->store->store(
			$this->post_id,
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'designsetgo/section',
						'attributes' => array(),
					),
				),
			),
			'replace'
		);

		wp_set_current_user( $this->editor_id );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . $this->post_id ) );

		$this->assertSame( 200, $response->get_status() );

		$json = wp_json_encode( $response->get_data() );
		$this->assertStringContainsString( '"attributes":{}', $json );
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

		$request = $this->report_request();
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
		$request = $this->report_request();
		$request->set_param( 'status', 'finished' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( $this->store->pending( $this->post_id ) );

		$report = $this->store->report( $this->post_id );
		$this->assertSame( 'finished', $report['status'] );
		$this->assertNotEmpty( $report['treeHash'] );
	}

	/**
	 * GET hands the browser the pending build's id, which every report must
	 * echo back.
	 */
	public function test_get_returns_build_id(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );

		wp_set_current_user( $this->editor_id );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $this->route_base . $this->post_id ) );

		$this->assertTrue( wp_is_uuid( $response->get_data()['buildId'] ) );
		$this->assertSame( $this->store->pending( $this->post_id )['buildId'], $response->get_data()['buildId'] );
	}

	/**
	 * A report without a buildId is rejected before it is recorded.
	 */
	public function test_post_requires_build_id(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );

		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'POST', $this->route_base . $this->post_id );
		$request->set_param( 'status', 'finished' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertNotNull( $this->store->pending( $this->post_id ) );
		$this->assertSame( 'pending', $this->store->report( $this->post_id )['status'] );
	}

	/**
	 * A stale tab reporting on an earlier build gets 409, and neither the
	 * newer pending build nor its report is touched.
	 */
	public function test_post_with_a_different_build_id_is_a_409_mismatch(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );
		$stale_id = $this->store->pending( $this->post_id )['buildId'];
		$this->store->store( $this->post_id, $this->tree(), 'append' );

		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'POST', $this->route_base . $this->post_id );
		$request->set_param( 'status', 'finished' );
		$request->set_param( 'buildId', $stale_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'designsetgo_build_mismatch', $response->as_error()->get_error_code() );
		$this->assertSame( 'append', $this->store->pending( $this->post_id )['mode'] );
		$this->assertSame( 'pending', $this->store->report( $this->post_id )['status'] );
	}

	/**
	 * A report with nothing pending is a 409 mismatch too, never recorded.
	 */
	public function test_post_with_nothing_pending_is_a_409_mismatch(): void {
		wp_set_current_user( $this->editor_id );
		$request = $this->report_request();
		$request->set_param( 'status', 'failed' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'designsetgo_build_mismatch', $response->as_error()->get_error_code() );
		$this->assertSame( array(), $this->store->report( $this->post_id ) );
	}

	/**
	 * POST conflict is terminal: it clears the pending tree but keeps the
	 * report.
	 */
	public function test_post_conflict_clears_pending(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );

		wp_set_current_user( $this->editor_id );
		$request = $this->report_request();
		$request->set_param( 'status', 'conflict' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( $this->store->pending( $this->post_id ) );
		$this->assertSame( 'conflict', $this->store->report( $this->post_id )['status'] );
	}

	/**
	 * POST awaiting_review is not terminal: the tree stays pending until
	 * the review is saved or discarded.
	 */
	public function test_post_awaiting_review_keeps_pending(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );

		wp_set_current_user( $this->editor_id );
		$request = $this->report_request();
		$request->set_param( 'status', 'awaiting_review' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotNull( $this->store->pending( $this->post_id ) );
	}

	/**
	 * POST failed is terminal: it clears the pending tree and keeps the
	 * report, so the agent reads the failure and resubmits.
	 */
	public function test_post_failed_clears_pending(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );

		wp_set_current_user( $this->editor_id );
		$request = $this->report_request();
		$request->set_param( 'status', 'failed' );
		$request->set_param(
			'invalid',
			array(
				array(
					'path'   => 'blocks[0]',
					'block'  => 'core/unknown-block',
					'reason' => 'could not place block',
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( $this->store->pending( $this->post_id ) );

		$report = $this->store->report( $this->post_id );
		$this->assertSame( 'failed', $report['status'] );
		$this->assertSame( 'could not place block', $report['invalid'][0]['reason'] );
	}

	/**
	 * A schema-valid report with a huge findings[].message is still
	 * rejected with 413 - the route-level body-size check runs before
	 * sanitize_params() (and therefore before Report_Schema's sanitizer
	 * ever iterates the oversized entry).
	 */
	public function test_post_rejects_oversized_body(): void {
		wp_set_current_user( $this->editor_id );

		$request = $this->report_request();
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'status'   => 'failed',
					'findings' => array(
						array(
							'rule'     => 'no-custom-html',
							'severity' => 'error',
							'path'     => 'blocks[0]',
							'message'  => str_repeat( 'x', 2 * 1024 * 1024 ),
						),
					),
				)
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 413, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code() );
	}

	/**
	 * An `invalid` entry missing a required field (`block`/`reason`) is
	 * rejected with 400 before it ever reaches the store.
	 */
	public function test_post_rejects_invalid_entry_missing_required_field(): void {
		wp_set_current_user( $this->editor_id );

		$request = $this->report_request();
		$request->set_param( 'status', 'failed' );
		$request->set_param(
			'invalid',
			array(
				array(
					'path' => 'blocks[0]',
					// 'block' and 'reason' are required and deliberately omitted.
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code() );
	}

	/**
	 * A `findings` entry with a severity outside the error|warning enum is
	 * rejected with 400.
	 */
	public function test_post_rejects_finding_with_unknown_severity(): void {
		wp_set_current_user( $this->editor_id );

		$request = $this->report_request();
		$request->set_param( 'status', 'failed' );
		$request->set_param(
			'findings',
			array(
				array(
					'rule'     => 'no-custom-html',
					'severity' => 'fatal',
					'path'     => 'blocks[0]',
					'message'  => 'bad',
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * An entry carrying an unrecognized key is rejected outright (400),
	 * not silently stripped - `additionalProperties: false` on the item
	 * schema is enforced by rest_validate_value_from_schema() during
	 * has_valid_params(), before sanitize_params() (and Report_Schema's
	 * sanitizer, which is what would otherwise drop unknown keys) ever
	 * runs. WordPress's behavior here is reject-the-request, not
	 * strip-and-continue.
	 */
	public function test_post_rejects_invalid_entry_with_unknown_key(): void {
		wp_set_current_user( $this->editor_id );

		$request = $this->report_request();
		$request->set_param( 'status', 'failed' );
		$request->set_param(
			'invalid',
			array(
				array(
					'path'       => 'blocks[0]',
					'block'      => 'core/unknown-block',
					'reason'     => 'could not place block',
					'unexpected' => 'nope',
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code() );
	}

	/**
	 * A fully valid report - both invalid and findings entries, including
	 * their optional fields - round-trips through sanitization intact.
	 */
	public function test_post_valid_full_report_round_trips(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );

		wp_set_current_user( $this->editor_id );
		$request = $this->report_request();
		$request->set_param( 'status', 'finished_with_findings' );
		$request->set_param(
			'invalid',
			array(
				array(
					'path'   => 'blocks[0]',
					'block'  => 'core/unknown-block',
					'reason' => 'could not place block',
					'code'   => 'designsetgo_unknown_block',
				),
			)
		);
		$request->set_param(
			'findings',
			array(
				array(
					'rule'       => 'no-custom-html',
					'severity'   => 'warning',
					'path'       => 'blocks[1]',
					'message'    => 'core/html renders arbitrary markup.',
					'suggestion' => 'Use designsetgo/icon instead.',
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$report = $this->store->report( $this->post_id );
		$this->assertSame(
			array(
				'path'   => 'blocks[0]',
				'block'  => 'core/unknown-block',
				'reason' => 'could not place block',
				'code'   => 'designsetgo_unknown_block',
			),
			$report['invalid'][0]
		);
		$this->assertSame(
			array(
				'rule'       => 'no-custom-html',
				'severity'   => 'warning',
				'path'       => 'blocks[1]',
				'message'    => 'core/html renders arbitrary markup.',
				'suggestion' => 'Use designsetgo/icon instead.',
			),
			$report['findings'][0]
		);

		// finished_with_findings is terminal - clears the pending tree.
		$this->assertNull( $this->store->pending( $this->post_id ) );
	}

	/**
	 * POST is forbidden for a subscriber.
	 */
	public function test_post_forbidden_for_subscriber(): void {
		wp_set_current_user( $this->subscriber_id );

		$request = $this->report_request();
		$request->set_param( 'status', 'finished' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}
}
