<?php
/**
 * Query Filter Index Admin Page
 *
 * Registers the Settings → DesignSetGo → Dynamic Query admin page
 * and enqueues the React dashboard bundle.
 *
 * @package DesignSetGo
 * @since 2.2.0
 */

namespace DesignSetGo\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query Filter Index Admin class
 */
class Query_Filter_Index_Admin {

	/**
	 * Admin page hook suffix returned by add_submenu_page().
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the Dynamic Query submenu page under Settings → DesignSetGo.
	 */
	public function register_page() {
		$this->page_hook = add_submenu_page(
			'designsetgo',
			__( 'Dynamic Query', 'designsetgo' ),
			__( 'Dynamic Query', 'designsetgo' ),
			'manage_options',
			'designsetgo-dynamic-query',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the mount point for the React app.
	 */
	public function render_page() {
		echo '<div id="dsgo-query-filter-index-dashboard"></div>';
	}

	/**
	 * Enqueue the React bundle only on our page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		$asset_file = DESIGNSETGO_PATH . 'build/admin/query-filter-index-dashboard.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'dsgo-query-filter-index-dashboard',
			DESIGNSETGO_URL . 'build/admin/query-filter-index-dashboard.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations(
			'dsgo-query-filter-index-dashboard',
			'designsetgo',
			DESIGNSETGO_PATH . 'languages'
		);

		wp_enqueue_style(
			'dsgo-query-filter-index-dashboard',
			DESIGNSETGO_URL . 'build/admin/query-filter-index-dashboard.css',
			array( 'wp-components' ),
			$asset['version']
		);

		wp_localize_script(
			'dsgo-query-filter-index-dashboard',
			'dsgoQueryFilterIndexDashboard',
			array(
				'apiUrl' => esc_url_raw( rest_url( 'designsetgo/v1' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
