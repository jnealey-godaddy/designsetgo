<?php
/**
 * Icon Injector
 *
 * Handles frontend icon injection for blocks that use icons.
 * Provides icon library via wp_localize_script to avoid bundling
 * the 51KB icon library into every block's JS bundle.
 *
 * @package DesignSetGo
 * @since 1.2.0
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icon Injector Class
 */
class Icon_Injector {

	/**
	 * Blocks that use icons and need the injector script.
	 *
	 * @var array
	 */
	private $icon_blocks = array(
		'designsetgo/icon',
		'designsetgo/icon-button',
		'designsetgo/icon-list-item',
		'designsetgo/divider',
		'designsetgo/tabs',
		'designsetgo/modal-trigger',
	);

	/**
	 * Whether the icon injector script has been enqueued for this request.
	 *
	 * @var bool
	 */
	private $injector_enqueued = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_icon_injector' ) );
		add_filter( 'render_block', array( $this, 'maybe_enqueue_on_render' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_icon_library' ) );
	}

	/**
	 * Enqueue shared icon library for the block editor.
	 *
	 * The editor needs the static icon library to render icon previews
	 * in blocks. The frontend uses lazy loading via wp_localize_script instead.
	 */
	public function enqueue_editor_icon_library() {
		if ( file_exists( DESIGNSETGO_PATH . 'build/shared-icon-library-static.js' ) ) {
			wp_enqueue_script(
				'designsetgo-icon-library-static',
				DESIGNSETGO_URL . 'build/shared-icon-library-static.js',
				array(),
				DESIGNSETGO_VERSION,
				true
			);
		}
	}

	/**
	 * Register (but don't enqueue) the icon injector script.
	 *
	 * The script is enqueued later via render_block when an icon block
	 * is actually rendered, which catches blocks in template parts too.
	 */
	public function register_icon_injector() {
		$asset_file = DESIGNSETGO_PATH . 'build/frontend/lazy-icon-injector.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_register_script(
			'designsetgo-icon-injector',
			DESIGNSETGO_URL . 'build/frontend/lazy-icon-injector.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Localize with icon library (from PHP).
		// This avoids bundling 51KB of icons into every block's JS.
		wp_localize_script(
			'designsetgo-icon-injector',
			'dsgoIcons',
			designsetgo_get_all_icons()
		);

		// Provide alias map so frontend can resolve alternate icon names.
		wp_localize_script(
			'designsetgo-icon-injector',
			'dsgoIconAliases',
			designsetgo_get_icon_aliases()
		);
	}

	/**
	 * Enqueue the icon injector when an icon block is rendered.
	 *
	 * Uses render_block filter to detect icon blocks wherever they appear:
	 * post content, templates, or template parts (e.g. header/footer).
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The full block, including name and attributes.
	 * @return string The unmodified block content.
	 */
	public function maybe_enqueue_on_render( $block_content, $block ) {
		if ( $this->injector_enqueued ) {
			return $block_content;
		}

		if ( in_array( $block['blockName'], $this->icon_blocks, true ) ) {
			wp_enqueue_script( 'designsetgo-icon-injector' );
			$this->injector_enqueued = true;
		}

		return $block_content;
	}
}
