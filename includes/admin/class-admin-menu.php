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
		// Tint the SVG menu icon with the admin-menu text color (grey → white on
		// hover/active) so it behaves like a native dashicon. Attaches to core's
		// global admin-menu stylesheet, present on every admin screen.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_menu_icon_style' ) );
	}

	/**
	 * The DesignSetGo brand mark (D + chevron) as a monochrome menu glyph.
	 *
	 * Filled with the default admin-menu icon grey so the raw data-URI shown by
	 * WordPress reads correctly even before {@see print_menu_icon_style()} tints
	 * it via CSS mask.
	 *
	 * @return string Inline SVG markup.
	 */
	private static function menu_icon_svg() {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="25 25 200 210">'
			. '<path fill="#a7aaad" d="M 28 43.5 L 28 62 77.058 62 C 129.382 62 136.099 62.456 147 66.744 C 204.638 89.421 201.668 171.52 142.5 191.124 C 135.586 193.415 134.843 193.447 81.75 193.777 L 28 194.111 28 212.627 L 28 231.144 85.75 230.757 C 150.861 230.322 150.71 230.338 168.727 221.971 C 244.088 186.976 247.776 76.291 174.887 37.071 C 154.174 25.925 147.061 25.013 80.75 25.006 L 28 25 28 43.5"/>'
			. '<path fill="#a7aaad" d="M 28 78.742 L 28 84.484 50 106.5 L 72 128.517 50 150.5 L 28 172.483 28 178.241 L 28 184 45.846 184 L 63.692 184 84.255 163.25 C 112.582 134.663 112.364 134.946 111.713 127.775 L 111.191 122.02 87.418 97.51 L 63.645 73 45.823 73 L 28 73 28 78.742"/>'
			. '</svg>';
	}

	/**
	 * The menu glyph as a base64 data URI for add_menu_page().
	 *
	 * @return string data:image/svg+xml;base64 URI.
	 */
	private static function menu_icon_data_uri() {
		return 'data:image/svg+xml;base64,' . base64_encode( self::menu_icon_svg() );
	}

	/**
	 * Attach the CSS that masks the menu icon with the current menu text colour,
	 * so it dims/brightens with hover and active states like core icons. Added to
	 * the core `admin-menu` stylesheet, which is present on every admin screen.
	 */
	public function enqueue_menu_icon_style() {
		// rawurlencode() leaves only URL-safe characters, so the data URI embeds
		// cleanly (unquoted) inside url() in the mask declaration.
		$mask = 'url(data:image/svg+xml,' . rawurlencode( self::menu_icon_svg() ) . ') no-repeat center';
		$css  = '#adminmenu #toplevel_page_designsetgo .wp-menu-image{'
			. 'background-image:none!important;background-color:currentColor;'
			. '-webkit-mask:' . $mask . ';mask:' . $mask . ';'
			. '-webkit-mask-size:20px auto;mask-size:20px auto;}';
		wp_add_inline_style( 'admin-menu', $css );
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
			self::menu_icon_data_uri(),
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

		$asset = include $asset_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- build artifact; path resolved from plugin directory

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
