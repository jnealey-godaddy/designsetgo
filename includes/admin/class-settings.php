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
	 * Placeholder returned in place of a stored write-only secret on read.
	 *
	 * The REST GET endpoint redacts write-only secrets to this value so they
	 * never reach the browser. The write path treats an incoming value equal
	 * to this placeholder as "unchanged" and preserves the stored secret. A
	 * real secret will never equal this string.
	 *
	 * Pure ASCII so it is byte-identical across encodings and opcode caches; a
	 * value this distinctive will not collide with a legitimate secret.
	 *
	 * Migration note: this placeholder changed from the prior '••••••••' on
	 * upgrade. A browser that still has the old settings form cached at deploy
	 * time echoes the old placeholder back on submit. The write path accepts
	 * LEGACY_REDACTED_PLACEHOLDER as "unchanged" too, so that stale submit
	 * preserves the stored secret instead of overwriting it. See
	 * is_redaction_placeholder().
	 */
	const REDACTED_PLACEHOLDER = '__DSGO_REDACTED__';

	/**
	 * Legacy redaction placeholder served by builds before REDACTED_PLACEHOLDER
	 * became ASCII.
	 *
	 * Eight U+2022 bullets. Defined with \u escapes so the bytes are exact
	 * regardless of how this file is saved or cached in an opcode cache. Only
	 * ever accepted on write (treated as "unchanged"); read paths emit
	 * REDACTED_PLACEHOLDER exclusively, so this is never sent to a browser.
	 */
	const LEGACY_REDACTED_PLACEHOLDER = "\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}";

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
	 * Source of truth for the settings structure. When adding or removing
	 * a setting key here, keep Settings_Schema::get() and
	 * get_sanitization_schema() in sync — each of the three descriptions
	 * covers the same shape and omissions silently hide keys from either
	 * the Abilities API surface (Settings_Schema) or the sanitization
	 * pipeline (get_sanitization_schema).
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
				'block_animations_enabled'       => false,
				'block_animations'               => array(),
			),
			'security'           => array(
				'log_ip_addresses' => true,
				'log_user_agents'  => true,
				'log_referrers'    => false,
			),
			'integrations'       => array(
				'google_maps_api_key'  => '',
				'turnstile_site_key'   => '',
				'turnstile_secret_key' => '',
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
				'enable'            => false,
				'post_types'        => array( 'page', 'post' ),
				'description'       => '',
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

		$raw_data = wp_json_file_decode( $json_path, array( 'associative' => true ) );

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
			array(
				'name'        => 'dynamic-tags',
				'title'       => __( 'Dynamic Tags', 'designsetgo' ),
				'description' => __( 'Bind block attributes to post / site / archive / user data or custom fields (ACF, Meta Box, Pods, JetEngine).', 'designsetgo' ),
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

		$saved_settings        = get_option( self::OPTION_NAME, array() );
		$defaults              = self::get_defaults();
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
	 * Write-only secrets (e.g. the Turnstile secret key) are redacted to a
	 * placeholder so they never reach the browser. get_settings() itself keeps
	 * returning the real values for server-side consumers (e.g. Turnstile
	 * verification).
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings_endpoint() {
		return rest_ensure_response( self::redact_secrets( self::get_settings() ) );
	}

	/**
	 * Map of write-only secret fields, keyed by settings group.
	 *
	 * Single source of truth shared by the read-redaction (redact_secrets) and
	 * the write-strip in sanitize_settings(). Add a field here to have it
	 * redacted on read and preserved-on-placeholder on write.
	 *
	 * @return array<string, string[]> Group => list of secret field keys.
	 */
	private static function write_only_secret_keys(): array {
		return array(
			'integrations' => array( 'turnstile_secret_key' ),
		);
	}

	/**
	 * Whether an incoming secret value is a redaction placeholder meaning
	 * "unchanged — keep the stored secret rather than persisting this value".
	 *
	 * Accepts both the current placeholder and the legacy one so a browser
	 * holding a stale settings form at upgrade time cannot overwrite the real
	 * secret with the old bullet string.
	 *
	 * @param mixed $value Submitted field value.
	 * @return bool True when the value is a placeholder and must not be saved.
	 */
	private static function is_redaction_placeholder( $value ): bool {
		return self::REDACTED_PLACEHOLDER === $value
			|| self::LEGACY_REDACTED_PLACEHOLDER === $value;
	}

	/**
	 * Return a copy of $settings with write-only secrets replaced by the
	 * redaction placeholder.
	 *
	 * Only non-empty string secrets are redacted; an empty value stays empty so
	 * the UI renders an empty field when no secret has been saved.
	 *
	 * @param array $settings Full settings array.
	 * @return array Settings with secrets redacted.
	 */
	private static function redact_secrets( array $settings ): array {
		foreach ( self::write_only_secret_keys() as $group => $fields ) {
			foreach ( $fields as $field ) {
				if ( isset( $settings[ $group ][ $field ] )
					&& is_string( $settings[ $group ][ $field ] )
					&& '' !== $settings[ $group ][ $field ] ) {
					$settings[ $group ][ $field ] = self::REDACTED_PLACEHOLDER;
				}
			}
		}

		return $settings;
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
		return rest_ensure_response(
			array(
				'success'  => true,
				'settings' => self::update_settings( (array) $request->get_json_params() ),
			)
		);
	}

	/**
	 * Apply a partial settings update.
	 *
	 * Sanitizes only keys present in $input, merges with existing saved
	 * settings so unsubmitted keys are preserved, persists, and invalidates
	 * the cache. Shared between the REST endpoint and the Abilities API
	 * update-settings ability so sanitization stays centralized.
	 *
	 * IMPORTANT: This method does not perform any authorization. Callers
	 * are responsible for verifying that the current user is allowed to
	 * mutate plugin settings (e.g. current_user_can( 'manage_options' ))
	 * before invoking it. The REST endpoint and update-settings ability
	 * both gate on manage_options before calling through.
	 *
	 * @param array $input Raw settings to apply (partial, nested).
	 * @return array Current settings after the update.
	 */
	public static function update_settings( array $input ): array {
		$sanitized = self::sanitize_settings( $input );

		$existing = get_option( self::OPTION_NAME, array() );
		$merged   = array_replace_recursive( $existing, $sanitized );

		// List fields must be replaced wholesale — array_replace_recursive
		// merges lists by numeric index, which would strand stale entries.
		if ( isset( $sanitized['animations']['block_animations'] ) ) {
			$merged['animations']['block_animations'] = $sanitized['animations']['block_animations'];
		}

		update_option( self::OPTION_NAME, $merged );
		self::invalidate_cache();

		return self::get_settings();
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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached via transient above; wp_count_posts() does not cover custom post types reliably.
			$form_submissions = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
					'dsgo_form_submission'
				)
			);

			// Cache for 1 hour. Invalidated on submission create/delete in
			// class-form-handler.php, so a longer TTL stays accurate.
			set_transient( 'dsgo_form_submissions_count', $form_submissions, HOUR_IN_SECONDS );
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
	 * Keep in sync with get_defaults() and Settings_Schema::get() — a key
	 * missing from this schema is silently dropped by sanitize_settings()
	 * even if the client sends it.
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
				'block_animations_enabled'       => 'bool',
				'block_animations'               => 'block_animations',
			),
			'security'           => array(
				'log_ip_addresses' => 'bool',
				'log_user_agents'  => 'bool',
				'log_referrers'    => 'bool',
			),
			'integrations'       => array(
				'google_maps_api_key'  => 'text',
				'turnstile_site_key'   => 'text',
				'turnstile_secret_key' => 'text',
			),
			'sticky_header'      => array(
				'enable'                    => 'bool',
				'custom_selector'           => 'css_selector',
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
				'enable'            => 'bool',
				'post_types'        => 'key_list',
				'description'       => 'textarea',
				'generate_full_txt' => 'bool',
			),
		);
	}

	/**
	 * Sanitize a single value according to its sanitizer type.
	 *
	 * @param mixed  $value     The value to sanitize.
	 * @param string $sanitizer The sanitizer type.
	 * @param mixed  $fallback  The fallback value to return if sanitization fails.
	 * @return mixed Sanitized value.
	 */
	private static function sanitize_value( $value, string $sanitizer, $fallback ) {
		switch ( $sanitizer ) {
			case 'bool':
				return (bool) $value;
			case 'absint':
				return absint( $value );
			case 'text':
				return sanitize_text_field( $value );
			case 'css_selector':
				return self::sanitize_css_selector( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'hex_color':
				$color = sanitize_hex_color( $value );
				return $color ? $color : $fallback;
			case 'key':
				return sanitize_key( $value );
			case 'text_list':
				return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : $fallback;
			case 'key_list':
				return is_array( $value ) ? array_map( 'sanitize_key', $value ) : $fallback;
			case 'block_animations':
				return self::sanitize_block_animations_list( is_array( $value ) ? $value : array() );
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Sanitize a CSS selector, rejecting values with HTML or dangerous CSS/JS patterns.
	 *
	 * Quotes ARE allowed because attribute selectors like `[data-foo="bar"]`
	 * and `[aria-expanded='true']` are valid and common — only `<` and `>`
	 * are rejected outright. The remaining patterns (`javascript:`,
	 * `expression(`, `url(`, `@import`) are real CSS injection vectors with
	 * no legitimate use inside a selector.
	 *
	 * @param mixed $value Raw input value.
	 * @return string Sanitized selector or empty string if the value is unsafe.
	 */
	public static function sanitize_css_selector( $value ): string {
		$trimmed = trim( (string) $value );
		if ( '' === $trimmed ) {
			return '';
		}
		// Reject HTML angle brackets and known dangerous CSS/JS injection patterns.
		if ( preg_match( '/[<>]/u', $trimmed ) ||
			preg_match( '/javascript:/i', $trimmed ) ||
			preg_match( '/expression\s*\(/i', $trimmed ) ||
			preg_match( '/url\s*\(/i', $trimmed ) ||
			preg_match( '/@import/i', $trimmed ) ) {
			return '';
		}
		return sanitize_text_field( $trimmed );
	}

	/**
	 * Sanitize the per-block-type animation defaults list.
	 *
	 * Each entry targets one or more valid blocks (exact name or `namespace/*`)
	 * and must carry at least an entrance or exit animation. Enum fields fall
	 * back to their defaults on an unknown value; numeric fields are clamped.
	 * A block name may only appear once across the whole list — see
	 * dedupe_block_animation_targets().
	 *
	 * @param array $value Raw list of entries.
	 * @return array Sanitized list (re-indexed).
	 */
	public static function sanitize_block_animations_list( array $value ): array {
		$clean = array();
		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$blocks = self::sanitize_block_animation_targets( $entry );
			if ( empty( $blocks ) ) {
				continue;
			}

			$fields = self::sanitize_block_animation_fields( $entry );

			// An entry that animates nothing is meaningless.
			if ( '' === $fields['entrance'] && '' === $fields['exit'] ) {
				continue;
			}

			$clean[] = array( 'blocks' => $blocks ) + $fields;
		}

		return self::dedupe_block_animation_targets( $clean );
	}

	/**
	 * Extract and validate the block names a single entry targets.
	 *
	 * Reads the `blocks` list, falling back to the historical singular `block`
	 * key so theme.json / Style Kits authored against the first shape of this
	 * feature keep resolving. Each name must be an exact `namespace/name` or a
	 * `namespace/*` wildcard; anything else is dropped. Duplicates within the
	 * entry are collapsed.
	 *
	 * Shared by the admin-option sanitizer and Animation_Defaults::get_effective()
	 * so theme.json-sourced names get the same format validation as admin ones.
	 *
	 * @param array $entry Raw entry.
	 * @return array<int, string> Validated block names (may be empty).
	 */
	public static function sanitize_block_animation_targets( array $entry ): array {
		$raw = array();
		if ( isset( $entry['blocks'] ) && is_array( $entry['blocks'] ) ) {
			$raw = $entry['blocks'];
		} elseif ( isset( $entry['block'] ) && is_string( $entry['block'] ) ) {
			$raw = array( $entry['block'] );
		}

		$blocks = array();
		foreach ( $raw as $block ) {
			if ( ! is_string( $block ) ) {
				continue;
			}

			$block = trim( $block );
			// namespace/name or namespace/* — lowercase letters, digits, hyphens.
			if ( ! preg_match( '#^[a-z0-9-]+/(\*|[a-z0-9-]+)$#', $block ) ) {
				continue;
			}

			if ( ! in_array( $block, $blocks, true ) ) {
				$blocks[] = $block;
			}
		}

		return $blocks;
	}

	/**
	 * Ensure no block name is claimed by more than one entry.
	 *
	 * Animation_Defaults::get_effective() builds a `block name => config` map,
	 * so a name claimed twice silently resolves to whichever entry was written
	 * last. Rather than persist rows that can never take effect, strip the
	 * earlier claims here — matching the map's last-wins precedence — and drop
	 * any entry left targeting nothing.
	 *
	 * @param array $list Sanitized entries, in author order.
	 * @return array Entries with unique targets (re-indexed).
	 */
	private static function dedupe_block_animation_targets( array $list ): array {
		$seen  = array();
		$clean = array();

		foreach ( array_reverse( $list ) as $entry ) {
			$blocks = array();
			foreach ( $entry['blocks'] as $block ) {
				if ( isset( $seen[ $block ] ) ) {
					continue;
				}
				$seen[ $block ] = true;
				$blocks[]       = $block;
			}

			if ( empty( $blocks ) ) {
				continue;
			}

			$entry['blocks'] = $blocks;
			$clean[]         = $entry;
		}

		return array_reverse( $clean );
	}

	/**
	 * Validate + normalize the animation-config fields of a single entry.
	 *
	 * Enforces the enum whitelist (entrance/exit/trigger/easing) and numeric
	 * clamps (duration/delay/offset), filling defaults for missing or invalid
	 * values. Does NOT include the `block` key and never drops the entry — it
	 * always returns a complete 8-field config. Shared by the admin-option
	 * sanitizer (above) and the resolve-time normalizer in Animation_Defaults
	 * so theme.json / Style-Kit entries get the same guarantees as
	 * admin-submitted ones.
	 *
	 * @param array $entry Raw entry (may contain a `block` key, ignored here).
	 * @return array{entrance:string,exit:string,trigger:string,duration:int,delay:int,easing:string,offset:int,once:bool}
	 */
	public static function sanitize_block_animation_fields( array $entry ): array {
		$entrances = array( 'fadeIn', 'fadeInUp', 'fadeInDown', 'fadeInLeft', 'fadeInRight', 'slideInUp', 'slideInDown', 'slideInLeft', 'slideInRight', 'zoomIn', 'bounceIn', 'flipInX', 'flipInY' );
		$exits     = array( 'fadeOut', 'fadeOutUp', 'fadeOutDown', 'fadeOutLeft', 'fadeOutRight', 'slideOutUp', 'slideOutDown', 'slideOutLeft', 'slideOutRight', 'zoomOut', 'bounceOut' );
		$triggers  = array( 'scroll', 'load', 'hover', 'click' );
		$easings   = array( 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'linear', 'cubic-bezier(0.68, -0.55, 0.265, 1.55)' );

		// is_string() before the cast, matching sanitize_block_animation_targets():
		// a theme.json entry that nests an array under one of these keys would
		// otherwise emit "Array to string conversion" on every request that
		// resolves the block type, before falling through to the default anyway.
		return array(
			'entrance' => isset( $entry['entrance'] ) && is_string( $entry['entrance'] ) && in_array( $entry['entrance'], $entrances, true ) ? $entry['entrance'] : '',
			'exit'     => isset( $entry['exit'] ) && is_string( $entry['exit'] ) && in_array( $entry['exit'], $exits, true ) ? $entry['exit'] : '',
			'trigger'  => isset( $entry['trigger'] ) && is_string( $entry['trigger'] ) && in_array( $entry['trigger'], $triggers, true ) ? $entry['trigger'] : 'scroll',
			'duration' => isset( $entry['duration'] ) ? max( 100, min( 5000, absint( $entry['duration'] ) ) ) : 600,
			'delay'    => isset( $entry['delay'] ) ? max( 0, min( 5000, absint( $entry['delay'] ) ) ) : 0,
			'easing'   => isset( $entry['easing'] ) && is_string( $entry['easing'] ) && in_array( $entry['easing'], $easings, true ) ? $entry['easing'] : 'ease-out',
			'offset'   => isset( $entry['offset'] ) ? max( 0, min( 1000, absint( $entry['offset'] ) ) ) : 100,
			'once'     => isset( $entry['once'] ) ? (bool) $entry['once'] : true,
		);
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
	public static function sanitize_settings( array $settings ): array {
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

			$group_defaults = $defaults[ $key ] ?? array();
			$group_values   = array();

			foreach ( $field_schema as $field_key => $sanitizer ) {
				// Only sanitize fields that were actually submitted.
				// Missing fields are omitted so array_replace_recursive in
				// the caller preserves existing saved values.
				if ( ! isset( $settings[ $key ][ $field_key ] ) ) {
					continue;
				}

				$default = $group_defaults[ $field_key ] ?? null;

				$group_values[ $field_key ] = self::sanitize_value(
					$settings[ $key ][ $field_key ],
					$sanitizer,
					$default
				);
			}

			// Drop write-only secrets the client echoed back unchanged (i.e. the
			// redaction placeholder served by get_settings_endpoint()). Leaving
			// them out lets array_replace_recursive() in update_settings()
			// preserve the stored secret instead of overwriting it with bullets.
			$secret_fields = self::write_only_secret_keys()[ $key ] ?? array();
			foreach ( $secret_fields as $secret_field ) {
				if ( isset( $settings[ $key ][ $secret_field ] )
					&& self::is_redaction_placeholder( $settings[ $key ][ $secret_field ] ) ) {
					unset( $group_values[ $secret_field ] );
				}
			}

			// Skip groups where no fields were actually submitted. Persisting
			// an empty array for a nested group would suppress the group's
			// defaults on subsequent get_settings() reads because wp_parse_args
			// is not recursive — a fresh install would then see e.g.
			// integrations => [] instead of the default shape.
			if ( ! empty( $group_values ) ) {
				$sanitized[ $key ] = $group_values;
			}
		}

		return $sanitized;
	}
}
