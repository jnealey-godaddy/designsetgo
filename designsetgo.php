<?php
/**
 * Plugin Name:       DesignSetGo
 * Plugin URI:        https://designsetgoblocks.com
 * Description:       Professional Gutenberg block library with 52 blocks and 16 powerful extensions - complete Form Builder, container system, interactive elements, maps, modals, breadcrumbs, timelines, scroll effects, and animations. Built with WordPress standards for guaranteed editor/frontend parity.
 * Version:           2.4.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
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
use DesignSetGo\LLMS_Txt\Controller;
use DesignSetGo\LLMS_Txt\File_Manager;
use DesignSetGo\Patterns\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DESIGNSETGO_VERSION', '2.4.0' );
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
require_once DESIGNSETGO_PATH . 'includes/data/block-animation-attributes.php';

/**
 * Load SVG icon library (used by dynamic blocks).
 */
require_once DESIGNSETGO_PATH . 'includes/data/icon-svg-library.php';

/**
 * Load breadcrumbs helper functions (used by breadcrumbs block).
 */
require_once DESIGNSETGO_PATH . 'includes/features/breadcrumbs-functions.php';

/**
 * Load pattern placeholder image helper (used by block patterns).
 */
require_once DESIGNSETGO_PATH . 'includes/patterns/placeholder-images.php';

/**
 * Load block-support routing helper (used by dynamic blocks whose wrapper is
 * a content-column positioning box, e.g. Pill, Icon).
 */
require_once DESIGNSETGO_PATH . 'includes/block-support-routing.php';

/**
 * Initialize the plugin.
 */
function designsetgo_init() {
	return Plugin::instance();
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
	Controller::schedule_flush_rewrite_rules();

	// Clear cached pattern file list so new/changed patterns are picked up.
	Loader::clear_cache();
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

	// Remove physical llms.txt if we wrote it. Only clear the ownership option when
	// the delete actually succeeds — if the filesystem is unavailable (e.g. FTP host
	// without credentials), leave the option intact so the plugin still knows it owns
	// the file on next activation rather than misreporting it as a third-party conflict.
	if ( get_option( 'designsetgo_llms_txt_physical' ) ) {
		if ( File_Manager::fs_delete( File_Manager::site_root_path() . 'llms.txt' ) ) {
			delete_option( 'designsetgo_llms_txt_physical' );
		}
	}
}
register_deactivation_hook( __FILE__, 'DesignSetGo\designsetgo_deactivate' );
