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

/**
 * Resolve the directory the plugin writes llms.txt / llms-full.txt to.
 *
 * Mirrors LLMS_Txt\File_Manager::site_root_path() (get_home_path()), which
 * differs from ABSPATH when WordPress lives in a subdirectory. The plugin's
 * classes are not loaded during uninstall, so the logic is repeated here.
 *
 * @return string Trailing-slashed absolute path.
 */
function designsetgo_uninstall_site_root_path() {
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	return trailingslashit( apply_filters( 'designsetgo_llms_txt_site_root', get_home_path() ) );
}

/**
 * Remove all DesignSetGo data for the current site.
 *
 * Form submissions are deleted along with the rest of the plugin's data, as
 * this file has always done. Draft-mode copies are ordinary draft posts the
 * author may still want, so only the plugin's linking meta is removed and the
 * drafts themselves are left in place.
 */
function designsetgo_uninstall_site() {
	global $wpdb;

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

	// 2. Delete post meta: form submission data (_dsg_ prefix, verified in
	// class-form-handler.php), draft-mode links and llms.txt settings (_dsgo_,
	// _designsetgo_), and the overlay header settings (dsgo_overlay_).
	designsetgo_uninstall_step(
		'post meta',
		function () use ( $wpdb ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall bulk cleanup; no WP API for mass meta deletion by key pattern.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta}
					 WHERE meta_key LIKE %s
					    OR meta_key LIKE %s
					    OR meta_key LIKE %s
					    OR meta_key LIKE %s",
					$wpdb->esc_like( '_dsg_' ) . '%',
					$wpdb->esc_like( '_dsgo_' ) . '%',
					$wpdb->esc_like( '_designsetgo_' ) . '%',
					$wpdb->esc_like( 'dsgo_overlay_' ) . '%'
				)
			);
		}
	);

	// 3. Remove physical llms.txt / llms-full.txt if we own them, the generated
	// Markdown directory, and the filter index table.
	designsetgo_uninstall_step(
		'llms.txt files',
		function () {
			// wp_delete_file() uses @unlink() internally, safe to call without existence checks.
			if ( get_option( 'designsetgo_llms_txt_physical' ) ) {
				wp_delete_file( designsetgo_uninstall_site_root_path() . 'llms.txt' );
			}

			if ( get_option( 'designsetgo_llms_full_txt_physical' ) ) {
				wp_delete_file( designsetgo_uninstall_site_root_path() . 'llms-full.txt' );
			}

			$upload_dir = wp_upload_dir( null, false );
			if ( empty( $upload_dir['basedir'] ) ) {
				return;
			}
			$markdown_dir = trailingslashit( $upload_dir['basedir'] ) . 'designsetgo/llms';
			if ( ! is_dir( $markdown_dir ) ) {
				return;
			}

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			global $wp_filesystem;
			if ( WP_Filesystem() && $wp_filesystem ) {
				$wp_filesystem->delete( $markdown_dir, true );
				// Remove the parent only when nothing else lives in it.
				$parent = dirname( $markdown_dir );
				if ( is_dir( $parent ) && array() === array_values( array_diff( (array) scandir( $parent ), array( '.', '..' ) ) ) ) {
					$wp_filesystem->delete( $parent, true );
				}
			}
		}
	);

	designsetgo_uninstall_step(
		'filter index table',
		function () use ( $wpdb ) {
			$table = $wpdb->prefix . 'dsgo_query_filter_index';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall drops the plugin's own table.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}
	);

	// 4. Delete plugin options and user meta.
	designsetgo_uninstall_step(
		'options',
		function () {
			$options = array(
				'designsetgo_global_styles',
				'designsetgo_settings',
				'designsetgo_llms_txt_physical',
				'designsetgo_llms_full_txt_physical',
				'designsetgo_llms_htaccess_backfilled',
				'designsetgo_llms_public_exports_repaired',
				'designsetgo_db_upgrade_failure',
				'designsetgo_db_version',
				'dsgo_last_draft_created',
				'dsgo_query_filter_index_schema',
				'dsgo_query_filter_index_status',
				'dsgo_query_filters',
				'dsgo_query_filter_rebuild_lock',
			);
			foreach ( $options as $option ) {
				delete_option( $option );
			}

			delete_metadata( 'user', 0, 'designsetgo_db_upgrade_notice_dismissed', '', true );
			delete_metadata( 'user', 0, 'designsetgo_llms_conflict_dismissed', '', true );
		}
	);

	// 5. Delete all plugin transients (rate limiting, block detection, form
	// definitions and counts, llms.txt caches, pattern caches, draft map,
	// signed query sources). Every plugin transient is namespaced dsgo_ or
	// designsetgo_.
	designsetgo_uninstall_step(
		'transients',
		function () use ( $wpdb ) {
			$prefixes = array( 'dsgo_', 'designsetgo_' );
			foreach ( array( '_transient_', '_transient_timeout_' ) as $base ) {
				foreach ( $prefixes as $prefix ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall bulk cleanup; no WP API for mass transient deletion by pattern.
					$wpdb->query(
						$wpdb->prepare(
							"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
							$wpdb->esc_like( $base . $prefix ) . '%'
						)
					);
				}
			}
		}
	);

	// 6. Clear object cache (guard against missing or incompatible implementations).
	designsetgo_uninstall_step(
		'object cache',
		function () {
			if ( function_exists( 'wp_cache_delete_group' ) ) {
				wp_cache_delete_group( 'designsetgo' );
				wp_cache_delete_group( 'designsetgo_query_filter_index' );
			} elseif ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( 'designsetgo' );
				wp_cache_flush_group( 'designsetgo_query_filter_index' );
			}
		}
	);

	// 7. Clear scheduled cron jobs.
	designsetgo_uninstall_step(
		'cron jobs',
		function () {
			wp_unschedule_hook( 'designsetgo_cleanup_old_submissions' );
			wp_unschedule_hook( 'designsetgo_form_webhook_retry' );
			wp_unschedule_hook( 'designsetgo_query_filter_backfill' );
		}
	);
}

if ( is_multisite() ) {
	$designsetgo_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $designsetgo_site_ids as $designsetgo_site_id ) {
		switch_to_blog( (int) $designsetgo_site_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- Uninstall must clean every site; only options, meta and files are touched.
		designsetgo_uninstall_site();
		restore_current_blog();
	}
} else {
	designsetgo_uninstall_site();
}

// Signal successful completion for debugging/testing hooks.
do_action( 'designsetgo_uninstalled' );
