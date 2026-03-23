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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'DesignSetGo uninstall (' . $label . '): ' . $e->getMessage() );
		}
	}
}

// 1. Delete all form submissions (custom post type).
designsetgo_uninstall_step(
	'form submissions',
	function () use ( $wpdb ) {
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
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( '_dsg_' ) . '%'
			)
		);
	}
);

// 3. Remove physical llms.txt if we own it, then delete plugin options.
designsetgo_uninstall_step(
	'options and llms.txt',
	function () {
		if ( get_option( 'designsetgo_llms_txt_physical' ) ) {
			$file_path = ABSPATH . 'llms.txt';
			if ( file_exists( $file_path ) && is_writable( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		}

		delete_option( 'designsetgo_global_styles' );
		delete_option( 'designsetgo_settings' );
		delete_option( 'designsetgo_llms_txt_physical' );
	}
);

// 4. Delete all plugin transients (rate limiting, block detection, form counts).
designsetgo_uninstall_step(
	'transients',
	function () use ( $wpdb ) {
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

		// Delete transient timeout entries.
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

// Log successful completion.
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	error_log( 'DesignSetGo: Plugin uninstall cleanup completed.' );
}
