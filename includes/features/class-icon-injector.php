<?php
/**
 * Icon Injector
 *
 * Handles frontend icon injection for blocks that use icons.
 *
 * Only the icons a page actually renders are sent to the browser: every
 * `data-icon-name` / `data-icon` seen during `render_block` is collected and
 * printed as `dsgoIcons` just before the footer scripts. Icons that arrive
 * after first paint (Query "load more", soft navigation) are fetched on demand
 * from the public `designsetgo/v1/icons` route.
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
	 * Most icon names a single REST request may ask for.
	 */
	const MAX_REST_NAMES = 100;

	/**
	 * Blocks that use icons and need the injector script.
	 *
	 * @var array
	 */
	private $icon_blocks = array(
		// designsetgo/icon and designsetgo/divider render their SVGs server-side
		// (render.php) and no longer emit a .dsgo-lazy-icon placeholder, so they
		// do not need the injector.
		'designsetgo/icon-button',
		'designsetgo/icon-list-item',
		'designsetgo/tabs',
		'designsetgo/modal-trigger',
	);

	/**
	 * Blocks whose own markup names an icon. Tabs builds its nav client-side
	 * from each tab panel's `data-icon`, so the tab (not tabs) is scanned.
	 *
	 * @var array
	 */
	private $scanned_blocks = array(
		'designsetgo/icon-button',
		'designsetgo/icon-list-item',
		'designsetgo/tab',
		'designsetgo/modal-trigger',
	);

	/**
	 * Whether the icon injector script has been enqueued for this request.
	 *
	 * @var bool
	 */
	private $injector_enqueued = false;

	/**
	 * Icon names seen in rendered markup this request, as keys.
	 *
	 * @var array<string,true>
	 */
	private $icon_names = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_icon_injector' ) );
		add_filter( 'render_block', array( $this, 'maybe_enqueue_on_render' ), 10, 2 );
		// Before _wp_footer_scripts (priority 10) prints the injector.
		add_action( 'wp_print_footer_scripts', array( $this, 'print_icon_data' ), 1 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_icon_library' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Enqueue shared icon library for the block editor.
	 *
	 * The editor needs the static icon library to render icon previews
	 * in blocks. The frontend receives only the icons a page uses instead.
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

		$asset = include $asset_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- build artifact; path resolved from plugin directory

		wp_register_script(
			'designsetgo-icon-injector',
			DESIGNSETGO_URL . 'build/frontend/lazy-icon-injector.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Where to fetch icons that were not rendered on first paint. The
		// version busts any HTTP cache when the icon library changes.
		wp_localize_script(
			'designsetgo-icon-injector',
			'dsgoIconsRest',
			array(
				'url'     => rest_url( 'designsetgo/v1/icons' ),
				'version' => DESIGNSETGO_VERSION,
			)
		);

		// Provide theme-level icon defaults so blocks that leave size/style
		// unset inherit the value configured in theme.json under
		// settings.custom.designsetgo.icon.
		wp_localize_script(
			'designsetgo-icon-injector',
			'dsgoIconDefaults',
			self::get_icon_defaults()
		);
	}

	/**
	 * Resolve the theme-level icon defaults from global settings.
	 *
	 * Reads settings.custom.designsetgo.icon from theme.json (or a Style Kit)
	 * and falls back to the plugin defaults when unset.
	 *
	 * @return array{size:int,style:string} Resolved icon defaults.
	 */
	public static function get_icon_defaults() {
		$icon_settings = array();

		if ( function_exists( 'wp_get_global_settings' ) ) {
			$icon_settings = wp_get_global_settings( array( 'custom', 'designsetgo', 'icon' ) );
		}

		if ( ! is_array( $icon_settings ) ) {
			$icon_settings = array();
		}

		$size = isset( $icon_settings['defaultSize'] ) ? absint( $icon_settings['defaultSize'] ) : 48;
		if ( $size <= 0 ) {
			$size = 48;
		}

		$style = isset( $icon_settings['defaultStyle'] ) ? (string) $icon_settings['defaultStyle'] : 'filled';
		if ( ! in_array( $style, array( 'filled', 'outlined' ), true ) ) {
			$style = 'filled';
		}

		return array(
			'size'  => $size,
			'style' => $style,
		);
	}

	/**
	 * Enqueue the icon injector when an icon block is rendered, and record
	 * which icons the block names.
	 *
	 * Uses render_block filter to detect icon blocks wherever they appear:
	 * post content, templates, or template parts (e.g. header/footer).
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The full block, including name and attributes.
	 * @return string The unmodified block content.
	 */
	public function maybe_enqueue_on_render( $block_content, $block ) {
		$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';

		if ( ! $this->injector_enqueued && in_array( $block_name, $this->icon_blocks, true ) ) {
			wp_enqueue_script( 'designsetgo-icon-injector' );
			$this->injector_enqueued = true;
		}

		if (
			is_string( $block_content )
			&& in_array( $block_name, $this->scanned_blocks, true )
			&& preg_match_all( '/\sdata-icon(?:-name)?="([^"]+)"/', $block_content, $matches )
		) {
			foreach ( $matches[1] as $raw_name ) {
				$name = designsetgo_sanitize_icon_slug( trim( $raw_name ) );
				if ( '' !== $name ) {
					$this->icon_names[ $name ] = true;
				}
			}
		}

		return $block_content;
	}

	/**
	 * Print the icons this page rendered as `window.dsgoIcons`.
	 *
	 * Runs just before the footer scripts, after every block has rendered.
	 */
	public function print_icon_data() {
		if ( ! wp_script_is( 'designsetgo-icon-injector', 'enqueued' ) ) {
			return;
		}

		wp_add_inline_script(
			'designsetgo-icon-injector',
			'var dsgoIcons = ' . wp_json_encode( (object) self::get_icons( array_keys( $this->icon_names ) ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
			'before'
		);
	}

	/**
	 * Resolve icon names (or aliases) to their SVG markup.
	 *
	 * Every requested name is present in the result; an unknown name maps to
	 * an empty string so the browser knows not to ask for it again.
	 *
	 * @param string[] $names Sanitized icon names.
	 * @return array<string,string> Icon name => SVG markup.
	 */
	public static function get_icons( array $names ) {
		$icons = array();

		foreach ( $names as $name ) {
			$icons[ $name ] = designsetgo_get_icon_svg( $name );
		}

		return $icons;
	}

	/**
	 * Register the public icon lookup route.
	 *
	 * The icon library is static, public plugin data, so the route needs no
	 * authentication.
	 */
	public function register_routes() {
		register_rest_route(
			'designsetgo/v1',
			'/icons',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_icons' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'names' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Return the SVG markup for a comma-separated list of icon names.
	 *
	 * @param \WP_REST_Request $request Request with a `names` parameter.
	 * @return \WP_REST_Response Icon name => SVG markup.
	 */
	public function rest_get_icons( \WP_REST_Request $request ) {
		$names = array_map( 'designsetgo_sanitize_icon_slug', explode( ',', (string) $request->get_param( 'names' ) ) );
		$names = array_slice( array_values( array_unique( array_filter( $names ) ) ), 0, self::MAX_REST_NAMES );

		$response = new \WP_REST_Response( (object) self::get_icons( $names ) );
		// The library only changes with the plugin version, which the client
		// puts in the URL.
		$response->header( 'Cache-Control', 'public, max-age=' . DAY_IN_SECONDS );

		return $response;
	}
}
