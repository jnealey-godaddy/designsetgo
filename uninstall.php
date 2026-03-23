<?php
/**
 * Uninstall DesignSetGo Plugin
 *
 * Fired when plugin is deleted (not deactivated).
 * Removes all plugin data from database.
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

// Exit if not called by WordPress uninstaller.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Wrap all cleanup in a try/catch so deletion never fatals.
try {
	// 1. Delete all form submissions (custom post type).
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->posts} WHERE post_type = %s",
			'dsgo_form_submission'
		)
	);

	// 2. Delete orphaned post meta (form submission metadata).
	// Note: Meta keys use _dsg_ prefix (not _dsgo_) - verified in class-form-handler.php:442-447
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_dsg_' ) . '%'
		)
	);

	// 3. Delete plugin options.
	delete_option( 'designsetgo_global_styles' );
	delete_option( 'designsetgo_settings' );
	delete_option( 'designsetgo_llms_txt_physical' );

	// 4. Delete all plugin transients (rate limiting, block detection, form counts).
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			    OR option_name LIKE %s
			    OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_form_submit_' ) . '%',
			$wpdb->esc_like( '_transient_dsgo_has_blocks_' ) . '%',
			$wpdb->esc_like( '_transient_dsgo_form_submissions_count' ) . '%'
		)
	);

	// 5. Delete transient timeout entries.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			    OR option_name LIKE %s
			    OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_timeout_form_submit_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_dsgo_has_blocks_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_dsgo_form_submissions_count' ) . '%'
		)
	);

	// 6. Clear object cache (guard against missing or incompatible implementations).
	if ( function_exists( 'wp_cache_delete_group' ) ) {
		wp_cache_delete_group( 'designsetgo' );
	} elseif ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'designsetgo' );
	}

	// 7. Clear scheduled cron jobs.
	$timestamp = wp_next_scheduled( 'designsetgo_cleanup_old_submissions' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'designsetgo_cleanup_old_submissions' );
	}
} catch ( \Throwable $e ) {
	// Log failure but never block plugin deletion.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'DesignSetGo: Uninstall cleanup error: ' . $e->getMessage() );
	}
}

// Log successful uninstallation for debugging.
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	error_log( 'DesignSetGo: Plugin data cleaned up successfully.' );
}
