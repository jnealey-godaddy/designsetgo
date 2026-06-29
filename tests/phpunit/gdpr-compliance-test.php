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
}
