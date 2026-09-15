<?php
/**
 * PHPUnit coverage for the designsetgo/build-page and
 * designsetgo/get-build-status abilities (Task 19): input-shape rejection,
 * permission gating, Tree_Validator wiring, the "new" draft-creation path,
 * and the get-build-status round trip.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Abilities_Registry;
use DesignSetGo\Abilities\Agent_Build\Build_Page;
use DesignSetGo\Abilities\Agent_Build\Build_Store;
use DesignSetGo\Abilities\Agent_Build\Get_Build_Status;

/**
 * Build_Page / Get_Build_Status ability tests.
 *
 * @group abilities
 * @group agent-build
 */
class Abilities_Build_Page_Test extends WP_UnitTestCase {

	/**
	 * Editor who owns the test post and can create new ones.
	 *
	 * @var int
	 */
	private int $editor_id;

	/**
	 * Subscriber with no editing capabilities.
	 *
	 * @var int
	 */
	private int $subscriber_id;

	/**
	 * Existing published page the "post_id" path targets.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->editor_id     = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->post_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Original</p><!-- /wp:paragraph -->',
				'post_author'  => $this->editor_id,
			)
		);

		wp_set_current_user( $this->editor_id );
	}

	/**
	 * A minimal well-formed tree fixture.
	 *
	 * @return array
	 */
	private function valid_tree(): array {
		return array(
			'version' => 1,
			'blocks'  => array(
				array(
					'name'       => 'core/paragraph',
					'attributes' => array( 'content' => 'Hello from an agent' ),
				),
			),
		);
	}

	/**
	 * The shared store instance the abilities themselves read/write through.
	 *
	 * @return Build_Store
	 */
	private function store(): Build_Store {
		return \DesignSetGo\Plugin::instance()->agent_build_store;
	}

	/**
	 * Total post count across all statuses/types, used to assert nothing
	 * was created.
	 *
	 * @return int
	 */
	private function total_post_count(): int {
		return count(
			get_posts( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- suppress_filters is false, see below.
				array(
					'post_type'        => 'any',
					'post_status'      => 'any',
					'numberposts'      => -1,
					'fields'           => 'ids',
					'suppress_filters' => false,
				)
			)
		);
	}

	// -------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------

	/**
	 * Both abilities are registered under their documented names.
	 */
	public function test_both_abilities_are_registered(): void {
		$registry = Abilities_Registry::get_instance();

		$this->assertTrue( $registry->has_ability( 'designsetgo/build-page' ) );
		$this->assertTrue( $registry->has_ability( 'designsetgo/get-build-status' ) );
	}

	/**
	 * Build-page declares the annotations the brief pins.
	 */
	public function test_build_page_annotations_and_category(): void {
		$config = ( new Build_Page() )->get_config();

		$this->assertSame( 'blocks', $config['category'] );
		$this->assertSame(
			array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			),
			$config['annotations']
		);
	}

	/**
	 * Get-build-status declares readonly: true.
	 */
	public function test_get_build_status_is_readonly(): void {
		$config = ( new Get_Build_Status() )->get_config();

		$this->assertTrue( $config['annotations']['readonly'] );
	}

	// -------------------------------------------------------------------
	// build-page: input shape
	// -------------------------------------------------------------------

	/**
	 * Post_id and new together is a data problem, and nothing is written.
	 */
	public function test_post_id_and_new_together_is_rejected_and_writes_nothing(): void {
		$before = $this->total_post_count();

		$result = ( new Build_Page() )->execute(
			array(
				'post_id' => $this->post_id,
				'new'     => array( 'title' => 'Should not be created' ),
				'tree'    => $this->valid_tree(),
			)
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'designsetgo_invalid_input', $result['problems'][0]['code'] );
		$this->assertSame( 'post_id', $result['problems'][0]['path'] );

		$this->assertSame( $before, $this->total_post_count() );
		$this->assertNull( $this->store()->pending( $this->post_id ) );
	}

	/**
	 * Neither post_id nor new is also a data problem.
	 */
	public function test_neither_post_id_nor_new_is_rejected(): void {
		$result = ( new Build_Page() )->execute( array( 'tree' => $this->valid_tree() ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'designsetgo_invalid_input', $result['problems'][0]['code'] );
		$this->assertSame( 'post_id/new', $result['problems'][0]['path'] );
	}

	/**
	 * An unrecognised mode value is a data problem.
	 */
	public function test_invalid_mode_is_rejected(): void {
		$result = ( new Build_Page() )->execute(
			array(
				'post_id' => $this->post_id,
				'tree'    => $this->valid_tree(),
				'mode'    => 'destroy',
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'designsetgo_invalid_input', $result['problems'][0]['code'] );
		$this->assertSame( 'mode', $result['problems'][0]['path'] );
	}

	/**
	 * A missing tree is reported the same way Tree_Validator reports one:
	 * as a designsetgo_invalid_tree data problem.
	 */
	public function test_missing_tree_is_a_data_problem(): void {
		$result = ( new Build_Page() )->execute( array( 'post_id' => $this->post_id ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'designsetgo_invalid_tree', $result['problems'][0]['code'] );
	}

	/**
	 * A structurally invalid tree (unknown block) returns Tree_Validator's
	 * problems and leaves post_content and the pending-build meta untouched.
	 */
	public function test_invalid_tree_returns_problems_and_writes_nothing(): void {
		$original_content = get_post( $this->post_id )->post_content;

		$result = ( new Build_Page() )->execute(
			array(
				'post_id' => $this->post_id,
				'tree'    => array(
					'version' => 1,
					'blocks'  => array( array( 'name' => 'core/does-not-exist' ) ),
				),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['problems'] );
		$this->assertSame( 'designsetgo_unknown_block', $result['problems'][0]['code'] );

		$this->assertSame( $original_content, get_post( $this->post_id )->post_content );
		$this->assertNull( $this->store()->pending( $this->post_id ) );
	}

	/**
	 * An invalid tree targeting "new" creates no post at all - validation
	 * runs before any post is created.
	 */
	public function test_no_post_created_when_new_target_has_invalid_tree(): void {
		$before = $this->total_post_count();

		$result = ( new Build_Page() )->execute(
			array(
				'new'  => array( 'title' => 'Agent Draft' ),
				'tree' => array(
					'version' => 2,
					'blocks'  => array(),
				),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'designsetgo_unsupported_tree_version', $result['problems'][0]['code'] );
		$this->assertSame( $before, $this->total_post_count() );
	}

	// -------------------------------------------------------------------
	// build-page: permissions
	// -------------------------------------------------------------------

	/**
	 * A subscriber cannot build into an existing post; permission failures
	 * come back as a WP_Error, unlike input problems.
	 */
	public function test_permission_denied_for_existing_post_returns_wp_error(): void {
		wp_set_current_user( $this->subscriber_id );

		$result = ( new Build_Page() )->execute(
			array(
				'post_id' => $this->post_id,
				'tree'    => $this->valid_tree(),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertNull( $this->store()->pending( $this->post_id ) );
	}

	/**
	 * A subscriber cannot create a new page either, and nothing is created.
	 */
	public function test_permission_denied_for_new_post_returns_wp_error_and_creates_nothing(): void {
		wp_set_current_user( $this->subscriber_id );
		$before = $this->total_post_count();

		$result = ( new Build_Page() )->execute(
			array(
				'new'  => array( 'title' => 'Should not be created' ),
				'tree' => $this->valid_tree(),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( $before, $this->total_post_count() );
	}

	/**
	 * A non-existent post_id is a data problem, not a WP_Error - unlike
	 * Add_Block, which reserves this for a 404 WP_Error.
	 */
	public function test_nonexistent_post_id_is_a_data_problem(): void {
		$result = ( new Build_Page() )->execute(
			array(
				'post_id' => 999999999,
				'tree'    => $this->valid_tree(),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'designsetgo_invalid_post', $result['problems'][0]['code'] );
		$this->assertSame( 'post_id', $result['problems'][0]['path'] );
	}

	/**
	 * An unregistered/non-REST post type for "new" is a data problem.
	 */
	public function test_invalid_new_post_type_is_a_data_problem(): void {
		$result = ( new Build_Page() )->execute(
			array(
				'new'  => array( 'post_type' => 'not-a-real-post-type' ),
				'tree' => $this->valid_tree(),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'designsetgo_invalid_input', $result['problems'][0]['code'] );
		$this->assertSame( 'new.post_type', $result['problems'][0]['path'] );
	}

	// -------------------------------------------------------------------
	// build-page: success paths
	// -------------------------------------------------------------------

	/**
	 * A valid tree against an existing, published post leaves post_content
	 * untouched, stores the pending build, and returns a finish_url.
	 */
	public function test_valid_tree_on_existing_post_leaves_content_untouched_and_stores_meta(): void {
		$original_content = get_post( $this->post_id )->post_content;

		$result = ( new Build_Page() )->execute(
			array(
				'post_id' => $this->post_id,
				'tree'    => $this->valid_tree(),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'pending', $result['status'] );
		$this->assertSame( $this->post_id, $result['post_id'] );
		$this->assertStringContainsString( 'dsgo-finish=1', $result['finish_url'] );

		// post_content is untouched - only post meta was written.
		$this->assertSame( $original_content, get_post( $this->post_id )->post_content );

		$pending = $this->store()->pending( $this->post_id );
		$this->assertNotNull( $pending );
		$this->assertSame( $this->valid_tree(), $pending['tree'] );
		$this->assertSame( 'replace', $pending['mode'] );
	}

	/**
	 * Mode defaults to "replace" but "append" is honoured and stored.
	 */
	public function test_append_mode_is_stored(): void {
		$result = ( new Build_Page() )->execute(
			array(
				'post_id' => $this->post_id,
				'tree'    => $this->valid_tree(),
				'mode'    => 'append',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'append', $this->store()->pending( $this->post_id )['mode'] );
	}

	/**
	 * "new" creates a draft with empty content and the sanitized title,
	 * then stores the pending build against it.
	 */
	public function test_new_creates_draft_with_empty_content_and_stores_meta(): void {
		$before = $this->total_post_count();

		$result = ( new Build_Page() )->execute(
			array(
				'new'  => array(
					'title'     => 'Agent Landing Page',
					'post_type' => 'page',
				),
				'tree' => $this->valid_tree(),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( $before + 1, $this->total_post_count() );

		$post = get_post( $result['post_id'] );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( '', $post->post_content );
		$this->assertSame( 'Agent Landing Page', $post->post_title );

		$this->assertNotNull( $this->store()->pending( $result['post_id'] ) );
	}

	/**
	 * "new" defaults post_type to "page" when omitted entirely.
	 */
	public function test_new_defaults_to_page_post_type(): void {
		$result = ( new Build_Page() )->execute(
			array(
				'new'  => array(),
				'tree' => $this->valid_tree(),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'page', get_post( $result['post_id'] )->post_type );
	}

	// -------------------------------------------------------------------
	// get-build-status
	// -------------------------------------------------------------------

	/**
	 * Missing post_id is a data problem.
	 */
	public function test_status_missing_post_id_is_a_data_problem(): void {
		$result = ( new Get_Build_Status() )->execute( array() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'designsetgo_missing_post_id', $result['problems'][0]['code'] );
	}

	/**
	 * A non-existent post is a data problem, not a WP_Error.
	 */
	public function test_status_nonexistent_post_is_a_data_problem(): void {
		$result = ( new Get_Build_Status() )->execute( array( 'post_id' => 999999999 ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'designsetgo_invalid_post', $result['problems'][0]['code'] );
	}

	/**
	 * A subscriber cannot poll status for a post they cannot edit.
	 */
	public function test_status_permission_denied_returns_wp_error(): void {
		wp_set_current_user( $this->subscriber_id );

		$result = ( new Get_Build_Status() )->execute( array( 'post_id' => $this->post_id ) );

		$this->assertWPError( $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * With no build ever submitted, status reports pending: false and an
	 * empty report.
	 */
	public function test_status_reports_no_pending_build_initially(): void {
		$result = ( new Get_Build_Status() )->execute( array( 'post_id' => $this->post_id ) );

		$this->assertTrue( $result['success'] );
		$this->assertSame( $this->post_id, $result['post_id'] );
		$this->assertFalse( $result['pending'] );
		$this->assertSame( array(), (array) $result['report'] );
	}

	/**
	 * Full round trip: build-page parks a pending build (status reports it
	 * pending with a "pending" report), then a terminal report - as
	 * Build_REST::post_item() would write once the editor finishes
	 * assembling it - clears pending and status reflects the outcome.
	 */
	public function test_status_round_trip_reflects_terminal_report(): void {
		( new Build_Page() )->execute(
			array(
				'post_id' => $this->post_id,
				'tree'    => $this->valid_tree(),
			)
		);

		$pending_status = ( new Get_Build_Status() )->execute( array( 'post_id' => $this->post_id ) );
		$this->assertTrue( $pending_status['pending'] );
		$this->assertSame( 'pending', $pending_status['report']->status );

		// Simulate the editor plugin (Task 20) posting a terminal report
		// back through Build_REST, the same way it does in production.
		$this->store()->write_report(
			$this->post_id,
			array(
				'status'    => 'finished',
				'invalid'   => array(),
				'findings'  => array(),
				'treeHash'  => $pending_status['report']->treeHash ?? '',
				'updatedAt' => gmdate( 'c' ),
			)
		);
		$this->store()->clear( $this->post_id );

		$final_status = ( new Get_Build_Status() )->execute( array( 'post_id' => $this->post_id ) );
		$this->assertFalse( $final_status['pending'] );
		$this->assertSame( 'finished', $final_status['report']->status );
	}
}
