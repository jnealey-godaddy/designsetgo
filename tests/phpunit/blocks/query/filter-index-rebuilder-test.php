<?php
/**
 * PHPUnit tests for FilterIndexRebuilder.
 *
 * @package DesignSetGo
 * @group query-block
 */

use DesignSetGo\Blocks\Query\FilterIndex;
use DesignSetGo\Blocks\Query\FilterIndexRebuilder;
use DesignSetGo\Blocks\Query\FilterRegistry;

/**
 * Rebuilder tests.
 */
class DesignSetGo_Query_Filter_Index_Rebuilder_Test extends WP_UnitTestCase {

	public function tear_down(): void {
		global $wpdb;

		// TRUNCATE in rebuild_all causes an implicit MySQL commit, ending the
		// test's transaction. All subsequent DB writes run in an implicit
		// transaction (autocommit=0 session var persists). parent::tear_down()
		// issues ROLLBACK, which would roll back any option deletes we do before
		// it. So: run parent first (commits the ROLLBACK), then clean up outside
		// any pending transaction.
		parent::tear_down();

		// Ensure we are not inside an implicit transaction before cleanup.
		$wpdb->query( 'COMMIT' );

		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'dsgo_query_filter_index' );
		delete_option( FilterIndex::OPTION_SCHEMA );
		delete_option( FilterIndex::OPTION_STATUS );
		delete_option( FilterRegistry::OPTION );

		// Flush the WP object cache so the next test reads fresh option values.
		wp_cache_flush();
	}

	public function test_rebuild_all_populates_index_from_scratch() {
		FilterIndex::install();
		FilterRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$cat      = $this->factory->category->create();
		$post_ids = $this->factory->post->create_many( 5, array(
			'post_status'   => 'publish',
			'post_category' => array( $cat ),
		) );

		// Wipe index — simulate fresh install.
		global $wpdb;
		$wpdb->query( 'TRUNCATE ' . FilterIndex::table_name() );
		$this->assertSame( '0', $wpdb->get_var( 'SELECT COUNT(*) FROM ' . FilterIndex::table_name() ) );

		$result = FilterIndexRebuilder::rebuild_all( array( 'batch_size' => 2 ) );

		$this->assertSame( 'complete', $result['status'] );
		$this->assertGreaterThanOrEqual( 5, $result['processed'] );
		$this->assertSame( 5, (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT object_id) FROM ' . FilterIndex::table_name() ) );
	}

	public function test_rebuild_all_truncates_before_repopulating() {
		FilterIndex::install();
		FilterRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		// Seed a stale row that doesn't correspond to any real post.
		global $wpdb;
		$wpdb->insert( FilterIndex::table_name(), array(
			'object_id'   => 999999,
			'object_type' => 'post',
			'filter_key'   => 'category',
			'filter_value' => 'stale',
		), array( '%d', '%s', '%s', '%s' ) );

		FilterIndexRebuilder::rebuild_all();

		$count = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . FilterIndex::table_name() . ' WHERE object_id = %d',
			999999
		) );
		$this->assertSame( 0, $count, 'Stale row must be removed by rebuild_all.' );
	}

	public function test_rebuild_all_status_option_records_completion() {
		FilterIndex::install();
		FilterIndexRebuilder::rebuild_all();

		$status = get_option( FilterIndex::OPTION_STATUS );
		$this->assertIsArray( $status );
		$this->assertFalse( $status['in_progress'] );
		$this->assertArrayHasKey( 'last_rebuilt_at', $status );
		$this->assertArrayHasKey( 'total_rows', $status );
		$this->assertArrayHasKey( 'processed', $status );
	}

	public function test_rebuild_filter_only_wipes_target_key() {
		FilterIndex::install();
		FilterRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		FilterRegistry::register( 'price',    array( 'type' => 'meta',     'source' => '_price' ) );

		$cat     = $this->factory->category->create();
		$post_id = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_category' => array( $cat ),
		) );
		update_post_meta( $post_id, '_price', '19.99' );
		FilterIndex::reindex_object( 'post', $post_id );

		// Seed a stale price row.
		global $wpdb;
		$wpdb->insert( FilterIndex::table_name(), array(
			'object_id'   => 77777,
			'object_type' => 'post',
			'filter_key'   => 'price',
			'filter_value' => 'stale',
		), array( '%d', '%s', '%s', '%s' ) );

		$result = FilterIndexRebuilder::rebuild_filter( 'price' );

		// Stale price row gone.
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . FilterIndex::table_name() . ' WHERE filter_key = %s AND filter_value = %s',
			'price',
			'stale'
		) ) );

		// Real price row repopulated.
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . FilterIndex::table_name() . ' WHERE object_id = %d AND filter_key = %s',
			$post_id,
			'price'
		) ) );

		// Category row untouched.
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . FilterIndex::table_name() . ' WHERE object_id = %d AND filter_key = %s',
			$post_id,
			'category'
		) ) );
	}

	public function test_rebuild_filter_on_unregistered_key_is_noop() {
		FilterIndex::install();
		$result = FilterIndexRebuilder::rebuild_filter( 'never_registered' );
		$this->assertSame( 'skipped', $result['status'] );
		$this->assertSame( 0, $result['processed'] );
	}

	public function test_status_reports_zero_when_table_empty() {
		FilterIndex::install();
		$status = FilterIndexRebuilder::status();
		$this->assertIsArray( $status );
		$this->assertSame( 0, $status['total_rows'] );
		$this->assertFalse( $status['in_progress'] );
		$this->assertArrayHasKey( 'last_rebuilt_at', $status );
	}

	public function test_rebuild_filter_with_custom_batch_size_completes_correctly() {
		FilterIndex::install();
		FilterRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$cat      = $this->factory->category->create();
		$post_ids = $this->factory->post->create_many( 5, array(
			'post_status'   => 'publish',
			'post_category' => array( $cat ),
		) );

		$result = FilterIndexRebuilder::rebuild_filter( 'category', array( 'batch_size' => 2 ) );

		$this->assertSame( 'complete', $result['status'] );
		$this->assertGreaterThanOrEqual( 5, $result['processed'] );
		$this->assertGreaterThanOrEqual( 5, $result['total_rows'] );
	}

	public function test_rebuild_all_returns_locked_when_lock_held() {
		FilterIndex::install();

		// Manually acquire the lock so rebuild_all sees it as held.
		add_option( FilterIndexRebuilder::LOCK_OPTION, time(), '', 'no' );

		$result = FilterIndexRebuilder::rebuild_all();

		// Clean up lock so subsequent tests aren't blocked.
		delete_option( FilterIndexRebuilder::LOCK_OPTION );

		$this->assertSame( 'locked', $result['status'] );
		$this->assertSame( 0, $result['processed'] );
		$this->assertSame( 0, $result['total_rows'] );
	}

	public function test_rebuild_all_releases_lock_on_completion() {
		FilterIndex::install();

		$result = FilterIndexRebuilder::rebuild_all();

		$this->assertSame( 'complete', $result['status'] );
		// Lock must be released after a successful run.
		$this->assertFalse( get_option( FilterIndexRebuilder::LOCK_OPTION, false ) );
	}

	public function test_status_clears_stale_in_progress() {
		FilterIndex::install();

		// Seed a stale in-progress status (started > 5 min ago).
		update_option(
			FilterIndex::OPTION_STATUS,
			array(
				'in_progress' => true,
				'started_at'  => time() - ( 6 * MINUTE_IN_SECONDS ),
				'processed'   => 0,
			),
			false
		);

		$status = FilterIndexRebuilder::status();

		$this->assertFalse( $status['in_progress'] );
		$this->assertSame( 'timed_out', $status['error'] );
	}

	public function test_rebuild_all_reports_error_when_table_missing() {
		FilterIndex::install();

		// Drop the table so TRUNCATE fails.
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . FilterIndex::table_name() );
		// Suppress the expected MySQL error so the test output stays clean.
		$wpdb->suppress_errors( true );

		$result = FilterIndexRebuilder::rebuild_all();

		$wpdb->suppress_errors( false );

		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( 0, $result['processed'] );

		$status = get_option( FilterIndex::OPTION_STATUS );
		$this->assertArrayHasKey( 'error', $status );
		$this->assertSame( 'truncate_failed', $status['error'] );
		$this->assertFalse( $status['in_progress'] );
	}

	public function test_rebuild_all_only_processes_publish_posts() {
		FilterIndex::install();
		FilterRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		// TRUNCATE in rebuild_all commits the DB transaction, so posts from prior
		// tests may still be in the DB. Delete all existing posts before this test
		// to get a clean, deterministic count.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->posts}" );

		$cat = $this->factory->category->create();
		$this->factory->post->create( array( 'post_status' => 'publish', 'post_category' => array( $cat ) ) );
		$this->factory->post->create( array( 'post_status' => 'draft',   'post_category' => array( $cat ) ) );
		$this->factory->post->create( array( 'post_status' => 'publish', 'post_category' => array( $cat ) ) );

		$wpdb->query( 'TRUNCATE ' . FilterIndex::table_name() );

		$result = FilterIndexRebuilder::rebuild_all();
		$this->assertSame( 2, $result['processed'] );  // Only 2 published posts.
	}
}
