<?php
/**
 * Tests for GDPR Compliance
 *
 * Tests the get_statistics method to verify correct counts
 * of form submissions using WP_Query.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\Admin\GDPR_Compliance;

/**
 * GDPR Compliance Test Case
 */
class Test_GDPR_Compliance extends WP_UnitTestCase {

	/**
	 * GDPR compliance instance.
	 *
	 * @var GDPR_Compliance
	 */
	private $gdpr;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$this->gdpr = new GDPR_Compliance();

		register_post_type(
			'dsgo_form_submission',
			array(
				'public' => false,
			)
		);
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		parent::tear_down();
	}

	/**
	 * Test get_statistics returns zero counts when no submissions exist.
	 */
	public function test_get_statistics_empty() {
		$stats = $this->gdpr->get_statistics();

		$this->assertIsArray( $stats );
		$this->assertEquals( 0, $stats['total'] );
		$this->assertEquals( 0, $stats['old_submissions'] );
	}

	/**
	 * Test get_statistics counts total submissions correctly.
	 */
	public function test_get_statistics_total_count() {
		$post_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$post_ids[] = wp_insert_post(
				array(
					'post_type'   => 'dsgo_form_submission',
					'post_status' => 'private',
					'post_title'  => "Submission {$i}",
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) ),
				)
			);
		}

		$stats = $this->gdpr->get_statistics();

		$this->assertEquals( 3, $stats['total'] );

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Test get_statistics counts old submissions (>30 days) correctly.
	 */
	public function test_get_statistics_old_submissions() {
		// Create 2 old submissions (>30 days).
		$old_ids = array();
		for ( $i = 0; $i < 2; $i++ ) {
			$old_ids[] = wp_insert_post(
				array(
					'post_type'   => 'dsgo_form_submission',
					'post_status' => 'private',
					'post_title'  => "Old Submission {$i}",
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
				)
			);
		}

		// Create 1 recent submission (<30 days).
		$new_id = wp_insert_post(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'private',
				'post_title'  => 'Recent Submission',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) ),
			)
		);

		$stats = $this->gdpr->get_statistics();

		$this->assertEquals( 3, $stats['total'] );
		$this->assertEquals( 2, $stats['old_submissions'] );

		foreach ( $old_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		wp_delete_post( $new_id, true );
	}

	/**
	 * Test get_statistics does not count other post types.
	 */
	public function test_get_statistics_ignores_other_post_types() {
		// Create a regular post.
		$page_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Regular Post',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			)
		);

		// Create a form submission.
		$sub_id = wp_insert_post(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'private',
				'post_title'  => 'Form Submission',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) ),
			)
		);

		$stats = $this->gdpr->get_statistics();

		$this->assertEquals( 1, $stats['total'] );
		$this->assertEquals( 0, $stats['old_submissions'] );

		wp_delete_post( $page_id, true );
		wp_delete_post( $sub_id, true );
	}

	/**
	 * Create a private submission with one email field.
	 *
	 * @param string $email Email stored in the submission.
	 * @param int    $days  Age in days (larger = older).
	 * @return int Post ID.
	 */
	private function make_submission( $email, $days ) {
		$id = wp_insert_post(
			array(
				'post_type'     => 'dsgo_form_submission',
				'post_status'   => 'private',
				'post_title'    => 'Submission',
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) ),
			)
		);
		update_post_meta(
			$id,
			'_dsg_form_fields',
			wp_slash(
				array(
					'email' => array(
						'type'  => 'email',
						'value' => $email,
					),
				)
			)
		);
		return $id;
	}

	/**
	 * Export must reach a matching submission older than the newest 100.
	 */
	public function test_export_finds_match_beyond_newest_hundred() {
		for ( $i = 1; $i <= 149; $i++ ) {
			$this->make_submission( "other{$i}@example.com", $i );
		}
		$old = $this->make_submission( 'Target@Example.com', 400 );

		$items = array();
		$page  = 1;
		do {
			$result = $this->gdpr->export_form_submissions( 'target@example.com', $page );
			$items  = array_merge( $items, $result['data'] );
			++$page;
		} while ( ! $result['done'] && $page < 10 );

		$this->assertCount( 1, $items );
		$this->assertSame( "form-submission-{$old}", $items[0]['item_id'] );
	}

	/**
	 * Export pages through more than one page of matches.
	 */
	public function test_export_pages_through_many_matches() {
		for ( $i = 1; $i <= 120; $i++ ) {
			$this->make_submission( 'same@example.com', $i );
		}

		$count = 0;
		$page  = 1;
		do {
			$result = $this->gdpr->export_form_submissions( 'same@example.com', $page );
			$count += count( $result['data'] );
			++$page;
		} while ( ! $result['done'] && $page < 10 );

		$this->assertSame( 120, $count );
	}

	/**
	 * Erase removes matches beyond the newest 100 and leaves others alone.
	 */
	public function test_erase_deletes_matches_beyond_newest_hundred() {
		$keep = array();
		for ( $i = 1; $i <= 149; $i++ ) {
			$keep[] = $this->make_submission( "other{$i}@example.com", $i );
		}
		$old_a = $this->make_submission( 'target@example.com', 400 );
		$old_b = $this->make_submission( 'TARGET@example.com', 401 );
		$new_a = $this->make_submission( 'target@example.com', 0 );

		$result = $this->gdpr->erase_form_submissions( 'target@example.com', 1 );

		$this->assertTrue( $result['done'] );
		$this->assertTrue( $result['items_removed'] );
		$this->assertNull( get_post( $old_a ) );
		$this->assertNull( get_post( $old_b ) );
		$this->assertNull( get_post( $new_a ) );
		foreach ( $keep as $id ) {
			$this->assertNotNull( get_post( $id ) );
		}
	}

	/**
	 * Submissions retained by the filter survive and the eraser still finishes.
	 */
	public function test_erase_respects_retention_filter_and_finishes() {
		$retained = $this->make_submission( 'target@example.com', 5 );
		$gone     = $this->make_submission( 'target@example.com', 6 );

		add_filter(
			'designsetgo_can_delete_form_submission',
			static function ( $can, $post_id ) use ( $retained ) {
				return $post_id === $retained ? false : $can;
			},
			10,
			2
		);

		$result = $this->gdpr->erase_form_submissions( 'target@example.com', 1 );

		$this->assertTrue( $result['done'] );
		$this->assertTrue( $result['items_retained'] );
		$this->assertNotNull( get_post( $retained ) );
		$this->assertNull( get_post( $gone ) );
	}
}
