<?php
/**
 * Admin Menu
 *
 * Handles the DesignSetGo admin menu and pages.
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

namespace DesignSetGo\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Menu class
 */
class Admin_Menu {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the admin menu
	 */
	private function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_menu', array( $this, 'reorder_menu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the admin menu
	 */
	public function register_menu() {
		// Main menu page (Dashboard).
		add_menu_page(
			__( 'DesignSetGo', 'designsetgo' ),
			__( 'DesignSetGo', 'designsetgo' ),
			'manage_options',
			'designsetgo',
			array( $this, 'render_dashboard_page' ),
			'dashicons-layout',
			30
		);

		// Dashboard (duplicate of main page for cleaner menu structure).
		add_submenu_page(
			'designsetgo',
			__( 'Dashboard', 'designsetgo' ),
			__( 'Dashboard', 'designsetgo' ),
			'manage_options',
			'designsetgo',
			array( $this, 'render_dashboard_page' )
		);

		// Blocks & Extensions.
		add_submenu_page(
			'designsetgo',
			__( 'Blocks & Extensions', 'designsetgo' ),
			__( 'Blocks & Extensions', 'designsetgo' ),
			'manage_options',
			'designsetgo-blocks',
			array( $this, 'render_blocks_page' )
		);

		// Note: Form Submissions is automatically added by the custom post type
		// registration in Form_Submissions class (show_in_menu => 'designsetgo').

		// Settings.
		add_submenu_page(
			'designsetgo',
			__( 'Settings', 'designsetgo' ),
			__( 'Settings', 'designsetgo' ),
			'manage_options',
			'designsetgo-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Reorder submenu items to ensure Dashboard is first
	 *
	 * WordPress automatically adds custom post types to submenus,
	 * which can appear before our Dashboard item. This ensures
	 * Dashboard is always first and appears when clicking the
	 * top-level menu item.
	 */
	public function reorder_menu() {
		global $submenu;

		if ( ! isset( $submenu['designsetgo'] ) ) {
			return;
		}

		// Desired order: Dashboard, Blocks & Extensions, Form Submissions, Settings.
		$desired_order = array(
			'designsetgo',              // Dashboard.
			'designsetgo-blocks',       // Blocks & Extensions.
			'edit.php?post_type=dsgo_form_submission', // Form Submissions.
			'designsetgo-settings',     // Settings.
		);

		$reordered = array();

		// Add items in desired order.
		foreach ( $desired_order as $slug ) {
			foreach ( $submenu['designsetgo'] as $key => $item ) {
				if ( $item[2] === $slug ) {
					$reordered[] = $item;
					unset( $submenu['designsetgo'][ $key ] );
					break;
				}
			}
		}

		// Add any remaining items that weren't in our desired order.
		foreach ( $submenu['designsetgo'] as $item ) {
			$reordered[] = $item;
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for menu reordering.
		$submenu['designsetgo'] = $reordered;
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin pages.
		if ( strpos( $hook, 'designsetgo' ) === false ) {
			return;
		}

		$asset_file = DESIGNSETGO_PATH . 'build/admin.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		// Enqueue admin script.
		wp_enqueue_script(
			'designsetgo-admin',
			DESIGNSETGO_URL . 'build/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Set up script translations for admin JavaScript.
		wp_set_script_translations(
			'designsetgo-admin',
			'designsetgo',
			DESIGNSETGO_PATH . 'languages'
		);

		// Enqueue admin styles.
		wp_enqueue_style(
			'designsetgo-admin',
			DESIGNSETGO_URL . 'build/admin.css',
			array( 'wp-components' ),
			$asset['version']
		);

		// Localize script with data.
		wp_localize_script(
			'designsetgo-admin',
			'designSetGoAdmin',
			array(
				'apiUrl'          => esc_url_raw( rest_url( 'designsetgo/v1' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'currentPage'     => $this->get_current_page( $hook ),
				'siteUrl'         => esc_url( home_url() ),
				'adminUrl'        => esc_url( admin_url() ),
				'logoUrl'         => esc_url( DESIGNSETGO_URL . 'build/admin/assets/logo.svg' ),
				'conflictPlugins' => $this->detect_conflicting_plugins(),
			)
		);
	}

	/**
	 * Get current admin page slug from hook
	 *
	 * @param string $hook Admin page hook.
	 * @return string Page slug.
	 */
	private function get_current_page( $hook ) {
		if ( strpos( $hook, 'page_designsetgo-blocks' ) !== false ) {
			return 'blocks';
		} elseif ( strpos( $hook, 'page_designsetgo-settings' ) !== false ) {
			return 'settings';
		} elseif ( strpos( $hook, 'dsgo_form_submission' ) !== false ) {
			return 'submissions';
		}
		return 'dashboard';
	}

	/**
	 * Detect plugins known to conflict with DesignSetGo REST API
	 *
	 * @return array List of detected conflicting plugins.
	 */
	private function detect_conflicting_plugins() {
		$conflicts = array();

		// Spectra (Ultimate Addons for Gutenberg).
		if ( defined( 'UAGB_VER' ) || defined( 'SPECTRA_VERSION' ) || class_exists( 'UAGB_Loader' ) ) {
			$conflicts[] = array(
				'name'    => 'Spectra',
				'slug'    => 'spectra',
				'message' => __( 'Spectra may interfere with the REST API. If you see errors, try temporarily deactivating Spectra.', 'designsetgo' ),
			);
		}

		return $conflicts;
	}

	/**
	 * Render the dashboard page
	 */
	public function render_dashboard_page() {
		echo '<div id="designsetgo-admin-root" class="designsetgo-admin-page"></div>';
	}

	/**
	 * Render the blocks & extensions page
	 */
	public function render_blocks_page() {
		echo '<div id="designsetgo-admin-root" class="designsetgo-admin-page"></div>';
	}

	/**
	 * Render the settings page
	 */
	public function render_settings_page() {
		echo '<div id="designsetgo-admin-root" class="designsetgo-admin-page"></div>';
	}
}
