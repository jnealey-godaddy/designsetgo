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

/**
 * Run a cleanup step, logging any failure without halting subsequent steps.
 *
 * @param string   $label    Human-readable step description for debug logs.
 * @param callable $callback The cleanup operation to execute.
 */
function designsetgo_uninstall_step( $label, $callback ) {
	try {
		$callback();
	} catch ( \Throwable $e ) {
		wp_trigger_error( __FUNCTION__, 'DesignSetGo uninstall (' . $label . '): ' . $e->getMessage(), E_USER_NOTICE );
	}
}

// 1. Delete all form submissions (custom post type).
designsetgo_uninstall_step(
	'form submissions',
	function () use ( $wpdb ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall bulk cleanup; no WP API for mass post deletion by type without loading each post.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->posts} WHERE post_type = %s",
				'dsgo_form_submission'
			)
		);
	}
);

// 2. Delete orphaned post meta (form submission metadata).
// Note: Meta keys use _dsg_ prefix (not _dsgo_) - verified in class-form-handler.php:442-447
designsetgo_uninstall_step(
	'post meta',
	function () use ( $wpdb ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall bulk cleanup; no WP API for mass meta deletion by key pattern.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( '_dsg_' ) . '%'
			)
		);
	}
);

// 3. Remove physical llms.txt / llms-full.txt if we own them, then delete plugin options.
designsetgo_uninstall_step(
	'options and llms.txt',
	function () {
		// wp_delete_file() uses @unlink() internally, safe to call without existence checks.
		if ( get_option( 'designsetgo_llms_txt_physical' ) ) {
			wp_delete_file( ABSPATH . 'llms.txt' );
		}

		if ( get_option( 'designsetgo_llms_full_txt_physical' ) ) {
			wp_delete_file( ABSPATH . 'llms-full.txt' );
		}

		delete_option( 'designsetgo_global_styles' );
		delete_option( 'designsetgo_settings' );
		delete_option( 'designsetgo_llms_txt_physical' );
		delete_option( 'designsetgo_llms_full_txt_physical' );
		delete_option( 'designsetgo_llms_htaccess_backfilled' );
	}
);

// 4. Delete all plugin transients (rate limiting, block detection, form counts).
designsetgo_uninstall_step(
	'transients',
	function () use ( $wpdb ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall bulk cleanup; no WP API for mass transient deletion by pattern.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				    OR option_name LIKE %s
				    OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_dsgo_form_submit_' ) . '%',
				$wpdb->esc_like( '_transient_dsgo_has_blocks_' ) . '%',
				$wpdb->esc_like( '_transient_dsgo_form_submissions_count' ) . '%'
			)
		);

		// Delete transient timeout entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall bulk cleanup; no WP API for mass transient deletion by pattern.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				    OR option_name LIKE %s
				    OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_dsgo_form_submit_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_dsgo_has_blocks_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_dsgo_form_submissions_count' ) . '%'
			)
		);
	}
);

// 5. Clear object cache (guard against missing or incompatible implementations).
designsetgo_uninstall_step(
	'object cache',
	function () {
		if ( function_exists( 'wp_cache_delete_group' ) ) {
			wp_cache_delete_group( 'designsetgo' );
		} elseif ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'designsetgo' );
		}
	}
);

// 6. Clear scheduled cron jobs.
designsetgo_uninstall_step(
	'cron jobs',
	function () {
		$timestamp = wp_next_scheduled( 'designsetgo_cleanup_old_submissions' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'designsetgo_cleanup_old_submissions' );
		}
	}
);

// Signal successful completion for debugging/testing hooks.
do_action( 'designsetgo_uninstalled' );
