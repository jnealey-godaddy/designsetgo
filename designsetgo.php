<?php
/**
 * Plugin Name:       DesignSetGo
 * Plugin URI:        https://designsetgoblocks.com
 * Description:       Professional Gutenberg block library with 52 blocks and 16 powerful extensions - complete Form Builder, container system, interactive elements, maps, modals, breadcrumbs, timelines, scroll effects, and animations. Built with WordPress standards for guaranteed editor/frontend parity.
 * Version:           2.0.51
 * Requires at least: 6.7
 * Requires PHP:      8.0
 * Author:            DesignSetGo
 * Author URI:        https://designsetgoblocks.com/nealey
 * License:           GPL-2.0-or-later
 * Text Domain:       designsetgo
 * Domain Path:       /languages
 *
 * @package DesignSetGo
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DESIGNSETGO_VERSION', '2.0.51' );
define( 'DESIGNSETGO_FILE', __FILE__ );
define( 'DESIGNSETGO_PATH', plugin_dir_path( __FILE__ ) );
define( 'DESIGNSETGO_URL', plugin_dir_url( __FILE__ ) );
define( 'DESIGNSETGO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the plugin.
 */
require_once DESIGNSETGO_PATH . 'includes/class-plugin.php';

/**
 * Load animation attributes helper (used by dynamic blocks).
 */
require_once DESIGNSETGO_PATH . 'includes/block-animation-attributes.php';

/**
 * Load SVG icon library (used by dynamic blocks).
 */
require_once DESIGNSETGO_PATH . 'includes/icon-svg-library.php';

/**
 * Load breadcrumbs helper functions (used by breadcrumbs block).
 */
require_once DESIGNSETGO_PATH . 'includes/breadcrumbs-functions.php';

/**
 * Initialize the plugin.
 */
function designsetgo_init() {
	return \DesignSetGo\Plugin::instance();
}

// Kick off the plugin.
designsetgo_init();

/**
 * Plugin activation hook.
 *
 * Schedules cron jobs for data retention cleanup and flushes rewrite rules.
 */
function designsetgo_activate() {
	// Schedule daily cleanup of old form submissions.
	if ( ! wp_next_scheduled( 'designsetgo_cleanup_old_submissions' ) ) {
		wp_schedule_event( time(), 'daily', 'designsetgo_cleanup_old_submissions' );
	}

	// Schedule rewrite rules flush for llms.txt feature.
	// Uses transient-based approach since rewrite rules aren't registered yet.
	require_once DESIGNSETGO_PATH . 'includes/llms-txt/class-controller.php';
	\DesignSetGo\LLMS_Txt\Controller::schedule_flush_rewrite_rules();

	// Clear cached pattern file list so new/changed patterns are picked up.
	\DesignSetGo\Patterns\Loader::clear_cache();
}
register_activation_hook( __FILE__, 'DesignSetGo\designsetgo_activate' );

/**
 * Plugin deactivation hook.
 *
 * Unschedules all cron jobs.
 */
function designsetgo_deactivate() {
	// Clear scheduled cleanup job.
	$timestamp = wp_next_scheduled( 'designsetgo_cleanup_old_submissions' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'designsetgo_cleanup_old_submissions' );
	}

	// Remove physical llms.txt if we wrote it.
	if ( get_option( 'designsetgo_llms_txt_physical' ) ) {
		$file_path = ABSPATH . 'llms.txt';
		if ( file_exists( $file_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Direct file operation required.
			unlink( $file_path );
		}
		delete_option( 'designsetgo_llms_txt_physical' );
	}
}
register_deactivation_hook( __FILE__, 'DesignSetGo\designsetgo_deactivate' );
