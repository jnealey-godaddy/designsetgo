<?php
/**
 * Settings Management Class
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
 * Settings Class - Manages plugin settings and REST API
 */
class Settings {
	/**
	 * Option name for storing settings
	 */
	const OPTION_NAME = 'designsetgo_settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Cached fresh-install detection result.
	 * Prevents multiple database queries per request.
	 *
	 * @var bool|null
	 */
	private static $is_fresh_install = null;

	/**
	 * Get default settings
	 *
	 * @return array Default settings.
	 */
	public static function get_defaults() {
		// Determine whether this is a fresh installation or an existing one.
		// For existing installations, avoid introducing new default exclusions
		// that could change behavior without explicit user consent.
		// Cache the result to avoid multiple database queries per request.
		if ( null === self::$is_fresh_install ) {
			self::$is_fresh_install = ( false === get_option( self::OPTION_NAME, false ) );
		}

		if ( self::$is_fresh_install ) {
			// Fresh install: apply conservative defaults with known conflict exclusions.
			$excluded_blocks_default = array(
				// Common third-party blocks known to have REST API conflicts.
				'gravityforms/*',
				'mailpoet/*',
				'woocommerce/*',
				'jetpack/*',
			);
		} else {
			// Existing install: do not add new exclusions automatically.
			$excluded_blocks_default = array();
		}

		return array(
			'enabled_blocks'     => array(), // Empty = all enabled.
			'enabled_extensions' => array(), // Empty = all enabled.
			'excluded_blocks'    => $excluded_blocks_default,
			'performance'        => array(
				'conditional_loading' => true,
				'cache_duration'      => 3600, // 1 hour.
			),
			'forms'              => array(
				'enable_honeypot'      => true,
				'enable_rate_limiting' => true,
				'enable_email_logging' => false,
				'retention_days'       => 30,
			),
			'animations'         => array(
				'enable_animations'              => true,
				'default_duration'               => 600,
				'default_easing'                 => 'ease-in-out',
				'respect_prefers_reduced_motion' => true,
				'default_icon_button_hover'      => 'fill-diagonal',
			),
			'security'           => array(
				'log_ip_addresses' => true,
				'log_user_agents'  => true,
				'log_referrers'    => false,
			),
			'integrations'       => array(
				'google_maps_api_key'    => '',
				'turnstile_site_key'     => '',
				'turnstile_secret_key'   => '',
			),
			'sticky_header'      => array(
				'enable'                    => true,
				'custom_selector'           => '',
				'z_index'                   => 100,
				'shadow_on_scroll'          => true,
				'shadow_size'               => 'medium',
				'shrink_on_scroll'          => true,
				'shrink_amount'             => 50,
				'mobile_enabled'            => true,
				'mobile_breakpoint'         => 768,
				'transition_speed'          => 300,
				'scroll_threshold'          => 50,
				'hide_on_scroll_down'       => false,
				'background_on_scroll'      => false,
				'background_scroll_color'   => '',
				'background_scroll_opacity' => 100,
				'text_scroll_color'         => '',
			),
			'draft_mode'         => array(
				'enable'                 => true,
				'show_page_list_actions' => true,
				'show_page_list_column'  => true,
				'show_frontend_preview'  => true,
				'auto_save_enabled'      => true,
				'auto_save_interval'     => 60,
			),
			'llms_txt'           => array(
				'enable'           => false,
				'post_types'       => array( 'page', 'post' ),
				'description'      => '',
				'generate_full_txt' => false,
			),
		);
	}

	/**
	 * Cached blocks registry data.
	 *
	 * @var array|null
	 */
	private static $cached_blocks_registry = null;

	/**
	 * Get all available blocks
	 *
	 * Loads block data from blocks-registry.json and applies i18n translations.
	 * The JSON file is the single source of truth for block metadata.
	 *
	 * @return array Block information organized by category.
	 */
	public static function get_available_blocks() {
		if ( null !== self::$cached_blocks_registry ) {
			return self::$cached_blocks_registry;
		}

		$json_path = __DIR__ . '/blocks-registry.json';

		if ( ! file_exists( $json_path ) || ! is_file( $json_path ) || ! is_readable( $json_path ) ) {
			return array();
		}

		$raw_data = json_decode( file_get_contents( $json_path ), true );

		if ( ! is_array( $raw_data ) ) {
			return array();
		}

		// Apply i18n translations to labels, titles, and descriptions.
		$registry = array();
		foreach ( $raw_data as $category_key => $category ) {
			$registry[ $category_key ] = array(
				'label'  => __( $category['label'], 'designsetgo' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				'blocks' => array(),
			);

			foreach ( $category['blocks'] as $block ) {
				$registry[ $category_key ]['blocks'][] = array(
					'name'        => $block['name'],
					'title'       => __( $block['title'], 'designsetgo' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					'description' => __( $block['description'], 'designsetgo' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					'performance' => $block['performance'],
				);
			}
		}

		self::$cached_blocks_registry = $registry;
		return self::$cached_blocks_registry;
	}

	/**
	 * Get all available extensions
	 *
	 * @return array Extension information.
	 */
	public static function get_available_extensions() {
		return array(
			array(
				'name'        => 'animation',
				'title'       => __( 'Animation', 'designsetgo' ),
				'description' => __( 'Base animation framework', 'designsetgo' ),
			),
			array(
				'name'        => 'background-video',
				'title'       => __( 'Background Video', 'designsetgo' ),
				'description' => __( 'Video backgrounds for blocks', 'designsetgo' ),
			),
			array(
				'name'        => 'block-animations',
				'title'       => __( 'Block Animations', 'designsetgo' ),
				'description' => __( 'Entrance/exit animations for all blocks', 'designsetgo' ),
			),
			array(
				'name'        => 'clickable-group',
				'title'       => __( 'Clickable Group', 'designsetgo' ),
				'description' => __( 'Make container blocks clickable with link functionality', 'designsetgo' ),
			),
			array(
				'name'        => 'custom-css',
				'title'       => __( 'Custom CSS', 'designsetgo' ),
				'description' => __( 'Per-block custom CSS', 'designsetgo' ),
			),
			array(
				'name'        => 'grid-span',
				'title'       => __( 'Grid Span', 'designsetgo' ),
				'description' => __( 'Grid layout span controls', 'designsetgo' ),
			),
			array(
				'name'        => 'max-width',
				'title'       => __( 'Max Width', 'designsetgo' ),
				'description' => __( 'Content width constraints', 'designsetgo' ),
			),
			array(
				'name'        => 'responsive',
				'title'       => __( 'Responsive', 'designsetgo' ),
				'description' => __( 'Responsive visibility/settings', 'designsetgo' ),
			),
			array(
				'name'        => 'reveal-control',
				'title'       => __( 'Reveal Control', 'designsetgo' ),
				'description' => __( 'Controls for reveal animations', 'designsetgo' ),
			),
			array(
				'name'        => 'sticky-header-controls',
				'title'       => __( 'Sticky Header Controls', 'designsetgo' ),
				'description' => __( 'Sticky header configuration for template parts', 'designsetgo' ),
			),
			array(
				'name'        => 'text-alignment-inheritance',
				'title'       => __( 'Text Alignment Inheritance', 'designsetgo' ),
				'description' => __( 'Cascading text alignment', 'designsetgo' ),
			),
			array(
				'name'        => 'expanding-background',
				'title'       => __( 'Expanding Background', 'designsetgo' ),
				'description' => __( 'Scroll-driven expanding background effect', 'designsetgo' ),
			),
			array(
				'name'        => 'text-reveal',
				'title'       => __( 'Text Reveal', 'designsetgo' ),
				'description' => __( 'Scroll-triggered text color reveal effect', 'designsetgo' ),
			),
			array(
				'name'        => 'vertical-scroll-parallax',
				'title'       => __( 'Vertical Scroll Parallax', 'designsetgo' ),
				'description' => __( 'Parallax scrolling effects for blocks', 'designsetgo' ),
			),
			array(
				'name'        => 'draft-mode',
				'title'       => __( 'Draft Mode', 'designsetgo' ),
				'description' => __( 'Create draft versions of published pages for safe editing', 'designsetgo' ),
			),
		);
	}

	/**
	 * Cached settings to avoid repeated get_option() + wp_parse_args() calls.
	 *
	 * @var array|null
	 */
	private static $cached_settings = null;

	/**
	 * Get current settings
	 *
	 * @return array Current settings merged with defaults.
	 */
	public static function get_settings() {
		if ( null !== self::$cached_settings ) {
			return self::$cached_settings;
		}

		$saved_settings       = get_option( self::OPTION_NAME, array() );
		$defaults             = self::get_defaults();
		self::$cached_settings = wp_parse_args( $saved_settings, $defaults );

		return self::$cached_settings;
	}

	/**
	 * Invalidate the settings cache.
	 *
	 * Call this whenever settings are saved or updated to ensure
	 * subsequent get_settings() calls return fresh data.
	 *
	 * @since 1.5.1
	 */
	public static function invalidate_cache() {
		self::$cached_settings = null;
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		// Get settings.
		register_rest_route(
			'designsetgo/v1',
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings_endpoint' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		// Update settings.
		register_rest_route(
			'designsetgo/v1',
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_settings_endpoint' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'args'                => array(
					// Sanitization for all args is handled centrally in sanitize_settings()
					// to avoid double-sanitization. Type/description kept for schema docs.
					'enabled_blocks'     => array(
						'type'        => 'array',
						'description' => __( 'List of enabled block names. Empty array means all enabled.', 'designsetgo' ),
					),
					'enabled_extensions' => array(
						'type'        => 'array',
						'description' => __( 'List of enabled extension names. Empty array means all enabled.', 'designsetgo' ),
					),
					'excluded_blocks'    => array(
						'type'        => 'array',
						'description' => __( 'Block name patterns excluded from abilities API.', 'designsetgo' ),
					),
					'performance'        => array(
						'type'        => 'object',
						'description' => __( 'Performance settings (conditional_loading, cache_duration).', 'designsetgo' ),
					),
					'forms'              => array(
						'type'        => 'object',
						'description' => __( 'Form settings (enable_honeypot, enable_rate_limiting, enable_email_logging, retention_days).', 'designsetgo' ),
					),
					'animations'         => array(
						'type'        => 'object',
						'description' => __( 'Animation settings (enable_animations, default_duration, default_easing, respect_prefers_reduced_motion).', 'designsetgo' ),
					),
					'security'           => array(
						'type'        => 'object',
						'description' => __( 'Security logging settings (log_ip_addresses, log_user_agents, log_referrers).', 'designsetgo' ),
					),
					'integrations'       => array(
						'type'        => 'object',
						'description' => __( 'Third-party integration keys (google_maps_api_key, turnstile_site_key, turnstile_secret_key).', 'designsetgo' ),
					),
					'sticky_header'      => array(
						'type'        => 'object',
						'description' => __( 'Sticky header configuration.', 'designsetgo' ),
					),
					'draft_mode'         => array(
						'type'        => 'object',
						'description' => __( 'Draft mode settings (enable, show_page_list_actions, etc.).', 'designsetgo' ),
					),
					'llms_txt'           => array(
						'type'        => 'object',
						'description' => __( 'llms.txt settings (enable, post_types).', 'designsetgo' ),
					),
				),
			)
		);

		// Get available blocks.
		register_rest_route(
			'designsetgo/v1',
			'/blocks',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_blocks_endpoint' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		// Get available extensions.
		register_rest_route(
			'designsetgo/v1',
			'/extensions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_extensions_endpoint' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		// Get plugin stats.
		register_rest_route(
			'designsetgo/v1',
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stats_endpoint' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);
	}

	/**
	 * Check read permission
	 *
	 * @param \WP_REST_Request $_request Request object (unused but required by REST API).
	 * @return bool True if user has permission.
	 */
	public function check_read_permission( $_request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check write permission
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error True if user has permission, WP_Error otherwise.
	 */
	public function check_write_permission( $request ) {
		// Check capability first (more fundamental than nonce).
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Then check nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'invalid_nonce',
				__( 'Invalid security token.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get settings endpoint
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings_endpoint() {
		return rest_ensure_response( self::get_settings() );
	}

	/**
	 * Update settings endpoint
	 *
	 * Supports partial updates: only keys present in the request body are
	 * changed; all other existing settings are preserved. Nested groups are
	 * merged field-by-field so sending { "forms": { "retention_days": 60 } }
	 * updates only that field without resetting the rest of the forms group.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings_endpoint( $request ) {
		$new_settings = $request->get_json_params();

		// Sanitize only the incoming keys.
		$sanitized = $this->sanitize_settings( $new_settings );

		// Merge with existing saved settings so unsubmitted keys are preserved.
		$existing = get_option( self::OPTION_NAME, array() );
		$merged   = array_replace_recursive( $existing, $sanitized );

		update_option( self::OPTION_NAME, $merged );
		self::invalidate_cache();

		return rest_ensure_response(
			array(
				'success'  => true,
				'settings' => self::get_settings(),
			)
		);
	}

	/**
	 * Get blocks endpoint
	 *
	 * @return \WP_REST_Response
	 */
	public function get_blocks_endpoint() {
		return rest_ensure_response( self::get_available_blocks() );
	}

	/**
	 * Get extensions endpoint
	 *
	 * @return \WP_REST_Response
	 */
	public function get_extensions_endpoint() {
		return rest_ensure_response( self::get_available_extensions() );
	}

	/**
	 * Get stats endpoint
	 *
	 * @return \WP_REST_Response
	 */
	public function get_stats_endpoint() {
		global $wpdb;

		$settings     = self::get_settings();
		$all_blocks   = self::get_available_blocks();
		$total_blocks = 0;

		// Count total blocks.
		foreach ( $all_blocks as $category ) {
			$total_blocks += count( $category['blocks'] );
		}

		// Count enabled blocks.
		$enabled_blocks = empty( $settings['enabled_blocks'] ) ? $total_blocks : count( $settings['enabled_blocks'] );

		// Count form submissions (with caching).
		$form_submissions = get_transient( 'dsgo_form_submissions_count' );

		if ( false === $form_submissions ) {
			// Cache miss - run the database query.
			$form_submissions = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
					'dsgo_form_submission'
				)
			);

			// Cache for 5 minutes.
			set_transient( 'dsgo_form_submissions_count', $form_submissions, 5 * MINUTE_IN_SECONDS );
		}

		return rest_ensure_response(
			array(
				'total_blocks'     => $total_blocks,
				'enabled_blocks'   => $enabled_blocks,
				'form_submissions' => (int) $form_submissions,
			)
		);
	}

	/**
	 * Get the sanitization schema for settings fields.
	 *
	 * Maps each setting key to its sanitizer type. The defaults are sourced
	 * from get_defaults() so there is a single source of truth.
	 *
	 * Supported sanitizer types:
	 * - 'bool'       — Cast to boolean.
	 * - 'absint'     — Unsigned integer via absint().
	 * - 'text'       — sanitize_text_field().
	 * - 'textarea'   — sanitize_textarea_field() (preserves newlines).
	 * - 'hex_color'  — sanitize_hex_color().
	 * - 'key'        — sanitize_key().
	 * - 'text_list'  — Array of sanitize_text_field() values.
	 * - 'key_list'   — Array of sanitize_key() values.
	 *
	 * @return array Sanitization schema keyed by setting group then field.
	 */
	private static function get_sanitization_schema(): array {
		return array(
			'enabled_blocks'     => 'text_list',
			'enabled_extensions' => 'text_list',
			'excluded_blocks'    => 'text_list',
			'performance'        => array(
				'conditional_loading' => 'bool',
				'cache_duration'      => 'absint',
			),
			'forms'              => array(
				'enable_honeypot'      => 'bool',
				'enable_rate_limiting' => 'bool',
				'enable_email_logging' => 'bool',
				'retention_days'       => 'absint',
			),
			'animations'         => array(
				'enable_animations'              => 'bool',
				'default_duration'               => 'absint',
				'default_easing'                 => 'text',
				'respect_prefers_reduced_motion' => 'bool',
				'default_icon_button_hover'      => 'key',
			),
			'security'           => array(
				'log_ip_addresses' => 'bool',
				'log_user_agents'  => 'bool',
				'log_referrers'    => 'bool',
			),
			'integrations'       => array(
				'google_maps_api_key'    => 'text',
				'turnstile_site_key'     => 'text',
				'turnstile_secret_key'   => 'text',
			),
			'sticky_header'      => array(
				'enable'                    => 'bool',
				'custom_selector'           => 'text',
				'z_index'                   => 'absint',
				'shadow_on_scroll'          => 'bool',
				'shadow_size'               => 'text',
				'shrink_on_scroll'          => 'bool',
				'shrink_amount'             => 'absint',
				'mobile_enabled'            => 'bool',
				'mobile_breakpoint'         => 'absint',
				'transition_speed'          => 'absint',
				'scroll_threshold'          => 'absint',
				'hide_on_scroll_down'       => 'bool',
				'background_on_scroll'      => 'bool',
				'background_scroll_color'   => 'hex_color',
				'background_scroll_opacity' => 'absint',
				'text_scroll_color'         => 'hex_color',
			),
			'draft_mode'         => array(
				'enable'                 => 'bool',
				'show_page_list_actions' => 'bool',
				'show_page_list_column'  => 'bool',
				'show_frontend_preview'  => 'bool',
				'auto_save_enabled'      => 'bool',
				'auto_save_interval'     => 'absint',
			),
			'llms_txt'           => array(
				'enable'           => 'bool',
				'post_types'       => 'key_list',
				'description'      => 'textarea',
				'generate_full_txt' => 'bool',
			),
		);
	}

	/**
	 * Sanitize a single value according to its sanitizer type.
	 *
	 * @param mixed  $value     The value to sanitize.
	 * @param string $sanitizer The sanitizer type.
	 * @param mixed  $default   The default value to fall back to.
	 * @return mixed Sanitized value.
	 */
	private static function sanitize_value( $value, string $sanitizer, $default ) {
		switch ( $sanitizer ) {
			case 'bool':
				return (bool) $value;
			case 'absint':
				return absint( $value );
			case 'text':
				return sanitize_text_field( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'hex_color':
				$color = sanitize_hex_color( $value );
				return $color ? $color : $default;
			case 'key':
				return sanitize_key( $value );
			case 'text_list':
				return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : $default;
			case 'key_list':
				return is_array( $value ) ? array_map( 'sanitize_key', $value ) : $default;
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Sanitize settings
	 *
	 * Uses the sanitization schema and defaults from get_defaults() to
	 * sanitize each setting value by type, eliminating repetitive
	 * isset/ternary patterns.
	 *
	 * Only processes keys present in the input. For nested groups, only
	 * sanitizes fields that were actually submitted — missing fields are
	 * omitted (not reset to defaults) so the caller can merge correctly.
	 *
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	private function sanitize_settings( $settings ): array {
		$sanitized = array();
		$defaults  = self::get_defaults();
		$schema    = self::get_sanitization_schema();

		foreach ( $schema as $key => $field_schema ) {
			// Skip keys not present in input.
			if ( ! isset( $settings[ $key ] ) ) {
				continue;
			}

			// Top-level list fields (enabled_blocks, enabled_extensions, excluded_blocks).
			if ( is_string( $field_schema ) ) {
				$sanitized[ $key ] = self::sanitize_value(
					$settings[ $key ],
					$field_schema,
					$defaults[ $key ] ?? array()
				);
				continue;
			}

			// Nested object fields — must be an array in input.
			if ( ! is_array( $settings[ $key ] ) ) {
				continue;
			}

			$group_defaults    = $defaults[ $key ] ?? array();
			$sanitized[ $key ] = array();

			foreach ( $field_schema as $field_key => $sanitizer ) {
				// Only sanitize fields that were actually submitted.
				// Missing fields are omitted so array_replace_recursive in
				// the caller preserves existing saved values.
				if ( ! isset( $settings[ $key ][ $field_key ] ) ) {
					continue;
				}

				$default = $group_defaults[ $field_key ] ?? null;

				$sanitized[ $key ][ $field_key ] = self::sanitize_value(
					$settings[ $key ][ $field_key ],
					$sanitizer,
					$default
				);
			}
		}

		return $sanitized;
	}
}
