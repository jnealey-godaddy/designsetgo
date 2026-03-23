<?php
/**
 * Test Uninstall Cleanup
 *
 * Verifies that uninstall.php removes all plugin data from the database
 * without causing fatal errors.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * Tests for the uninstall routine.
 */
class Test_Uninstall extends WP_UnitTestCase {

	/**
	 * Seed fixture data that the plugin would create during normal use.
	 */
	private function seed_plugin_data() {
		global $wpdb;

		// Form submission post.
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'publish',
				'post_title'  => 'Test Submission',
			)
		);
		update_post_meta( $post_id, '_dsg_form_data', 'test' );
		update_post_meta( $post_id, '_dsg_submitted_at', time() );

		// Plugin options.
		update_option( 'designsetgo_global_styles', array( 'test' => true ) );
		update_option( 'designsetgo_settings', array( 'setting' => 'value' ) );
		update_option( 'designsetgo_llms_txt_physical', true );

		// Transients.
		set_transient( 'form_submit_127_0_0_1', 1, HOUR_IN_SECONDS );
		set_transient( 'dsgo_has_blocks_123', true, DAY_IN_SECONDS );
		set_transient( 'dsgo_form_submissions_count', 5, DAY_IN_SECONDS );

		// Cron job.
		wp_schedule_event( time(), 'daily', 'designsetgo_cleanup_old_submissions' );
	}

	/**
	 * Test that uninstall.php runs without fatal errors and cleans up all data.
	 *
	 * Uses a single test method because uninstall.php can only be required once
	 * per PHP process (no include guard).
	 */
	public function test_uninstall_cleans_up_all_data() {
		global $wpdb;

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		$this->seed_plugin_data();

		// Verify fixture data exists before uninstall.
		$pre_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
				'dsgo_form_submission'
			)
		);
		$this->assertGreaterThan( 0, $pre_count, 'Fixture: form submissions should exist before uninstall' );
		$this->assertNotFalse( get_option( 'designsetgo_settings' ), 'Fixture: options should exist before uninstall' );

		// Run uninstall — should not throw.
		require DESIGNSETGO_PATH . 'uninstall.php';

		// Form submissions deleted.
		$post_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
				'dsgo_form_submission'
			)
		);
		$this->assertSame( 0, $post_count, 'Form submissions should be deleted' );

		// Post meta deleted.
		$meta_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( '_dsg_' ) . '%'
			)
		);
		$this->assertSame( 0, $meta_count, 'Post meta with _dsg_ prefix should be deleted' );

		// Options deleted.
		$this->assertFalse( get_option( 'designsetgo_global_styles' ), 'designsetgo_global_styles should be deleted' );
		$this->assertFalse( get_option( 'designsetgo_settings' ), 'designsetgo_settings should be deleted' );
		$this->assertFalse( get_option( 'designsetgo_llms_txt_physical' ), 'designsetgo_llms_txt_physical should be deleted' );

		// Transients deleted.
		$this->assertFalse( get_transient( 'form_submit_127_0_0_1' ), 'Rate limit transients should be deleted' );
		$this->assertFalse( get_transient( 'dsgo_has_blocks_123' ), 'Block detection transients should be deleted' );
		$this->assertFalse( get_transient( 'dsgo_form_submissions_count' ), 'Form count transients should be deleted' );

		// Cron cleared.
		$this->assertFalse( wp_next_scheduled( 'designsetgo_cleanup_old_submissions' ), 'Cron job should be unscheduled' );
	}

	/**
	 * Test that the uninstall helper function handles exceptions gracefully.
	 */
	public function test_uninstall_step_catches_exceptions() {
		// Ensure the function was defined by the require above.
		$this->assertTrue( function_exists( 'designsetgo_uninstall_step' ), 'Helper function should exist after uninstall.php is loaded' );

		// Should not throw even when callback throws.
		designsetgo_uninstall_step(
			'test step',
			function () {
				throw new \RuntimeException( 'Simulated failure' );
			}
		);

		$this->assertTrue( true, 'designsetgo_uninstall_step should catch exceptions' );
	}
}
