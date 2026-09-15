<?php
/**
 * Build_Store owns the two post-meta keys agent-submitted block trees are
 * parked in (never post_content) until an editor plugin assembles them.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Agent_Build\Build_Store;

/**
 * Build_Store tests.
 *
 * @group abilities
 * @group agent-build
 */
class Agent_Build_Store_Test extends WP_UnitTestCase {

	/**
	 * Store instance under test.
	 *
	 * @var Build_Store
	 */
	private $store;

	/**
	 * Post the store operates on.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->store   = new Build_Store();
		$this->post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Build_Store is a plain post-meta wrapper, never registered as an ability.
	 */
	public function test_helper_class_is_not_an_ability(): void {
		$this->assertFalse( is_subclass_of( Build_Store::class, Abstract_Ability::class ) );
	}

	/**
	 * A post with no pending build reports null / an empty report.
	 */
	public function test_pending_and_report_are_empty_before_any_store(): void {
		$this->assertNull( $this->store->pending( $this->post_id ) );
		$this->assertSame( array(), $this->store->report( $this->post_id ) );
		$this->assertFalse( $this->store->is_conflict( $this->post_id ) );
	}

	/**
	 * Round-trips the tree, mode, and a base snapshot via store(), and writes
	 * a pending report with a stable tree hash.
	 */
	public function test_store_round_trips_tree_mode_and_base(): void {
		$tree = array(
			'version' => 1,
			'blocks'  => array(
				array(
					'name'        => 'core/paragraph',
					'attrs'       => array( 'content' => 'Hello' ),
					'innerBlocks' => array(),
				),
			),
		);

		$this->store->store( $this->post_id, $tree, 'replace' );

		$pending = $this->store->pending( $this->post_id );
		$this->assertIsArray( $pending );
		$this->assertSame( $tree, $pending['tree'] );
		$this->assertSame( 'replace', $pending['mode'] );
		$this->assertSame( get_post_field( 'post_modified_gmt', $this->post_id ), $pending['base'] );

		$report = $this->store->report( $this->post_id );
		$this->assertSame( 'pending', $report['status'] );
		$this->assertNotEmpty( $report['treeHash'] );
		$this->assertSame( $report['treeHash'], hash( 'sha256', wp_json_encode( $tree ) ) );
	}

	/**
	 * Storing a build never changes post_modified_gmt - only
	 * update_post_meta(), never wp_update_post() - so an immediate GET does
	 * not report a false conflict.
	 */
	public function test_store_does_not_bump_modified_date(): void {
		$before = get_post_field( 'post_modified_gmt', $this->post_id );

		// Ensure any timestamp bump, if it happened, would be observable.
		sleep( 1 );

		$this->store->store(
			$this->post_id,
			array(
				'version' => 1,
				'blocks'  => array(),
			),
			'replace'
		);

		$after = get_post_field( 'post_modified_gmt', $this->post_id );
		$this->assertSame( $before, $after );
		$this->assertFalse( $this->store->is_conflict( $this->post_id ) );
	}

	/**
	 * Conflict detection flips true once the post changes after the base was
	 * captured.
	 */
	public function test_is_conflict_after_post_update(): void {
		$this->store->store(
			$this->post_id,
			array(
				'version' => 1,
				'blocks'  => array(),
			),
			'replace'
		);
		$this->assertFalse( $this->store->is_conflict( $this->post_id ) );

		// post_modified_gmt has one-second resolution; without this, a fast
		// test run can land both writes in the same second and hide a real
		// conflict.
		sleep( 1 );

		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => 'changed out from under the pending build',
			)
		);

		$this->assertTrue( $this->store->is_conflict( $this->post_id ) );
	}

	/**
	 * Writing a report overwrites the stored report independently of the
	 * pending tree.
	 */
	public function test_write_report_overwrites_report(): void {
		$this->store->store(
			$this->post_id,
			array(
				'version' => 1,
				'blocks'  => array(),
			),
			'replace'
		);

		$this->store->write_report(
			$this->post_id,
			array(
				'status' => 'finished',
			)
		);

		$this->assertSame( array( 'status' => 'finished' ), $this->store->report( $this->post_id ) );
	}

	/**
	 * Clearing removes the pending tree but leaves the report alone.
	 */
	public function test_clear_removes_pending_tree_but_keeps_report(): void {
		$this->store->store(
			$this->post_id,
			array(
				'version' => 1,
				'blocks'  => array(),
			),
			'replace'
		);
		$this->store->write_report( $this->post_id, array( 'status' => 'finished' ) );

		$this->store->clear( $this->post_id );

		$this->assertNull( $this->store->pending( $this->post_id ) );
		$this->assertSame( array( 'status' => 'finished' ), $this->store->report( $this->post_id ) );
	}

	/**
	 * The two meta keys are protected (leading underscore) and not exposed
	 * via the REST post meta index.
	 */
	public function test_meta_keys_are_protected_and_not_shown_in_rest(): void {
		// Call register_meta() directly rather than re-firing the 'init'
		// action - 'init' already ran during bootstrap, and refiring it
		// trips _doing_it_wrong notices from unrelated double registrations
		// (blocks, payment gateways) that WP_UnitTestCase treats as failures.
		$this->store->register_meta();

		$this->assertTrue( registered_meta_key_exists( 'post', '_dsgo_pending_tree' ) );
		$this->assertTrue( registered_meta_key_exists( 'post', '_dsgo_build_report' ) );

		$keys = get_registered_meta_keys( 'post' );
		$this->assertFalse( $keys['_dsgo_pending_tree']['show_in_rest'] );
		$this->assertFalse( $keys['_dsgo_build_report']['show_in_rest'] );
	}
}
