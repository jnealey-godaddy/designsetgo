<?php
/**
 * Global Styles Management Class
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
 * Global Styles Class - Integrates with theme.json
 */
class Global_Styles {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wp_theme_json_data_theme', array( $this, 'extend_theme_json' ) );
		// Temporarily disabled - no settings page needed at this time.
		// add_action( 'admin_menu', array( $this, 'add_admin_menu' ) ).
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Extend theme.json with DesignSetGo presets.
	 *
	 * Adds DesignSetGo-specific settings and spacing presets:
	 * - Spacing sizes (both standard WP slugs and legacy DSG slugs) are only added
	 *   when the theme doesn't define its own spacingSizes, respecting theme values.
	 * - Font sizes are only added when theme doesn't define them.
	 * - Color palette, gradients, and duotone are NOT added (respects theme colors).
	 *
	 * @param \WP_Theme_JSON_Data $theme_json Theme JSON object.
	 * @return \WP_Theme_JSON_Data Modified theme JSON.
	 */
	public function extend_theme_json( $theme_json ) {
		// Get existing theme data to check what's already defined.
		$theme_data = $theme_json->get_data();

		// Build settings array, only adding presets as fallbacks.
		$dsg_settings = array(
			'version'  => 2,
			'settings' => array(
				'spacing' => array(
					'units' => array( 'px', 'em', 'rem', 'vh', 'vw', '%' ),
				),
				'custom'  => array(
					'designsetgo' => array(
						'borderRadius'          => array(
							'none'   => '0',
							'small'  => '0.25rem',
							'medium' => '0.5rem',
							'large'  => '1rem',
							'full'   => '9999px',
						),
						'shadow'                => array(
							'small'  => '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
							'medium' => '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
							'large'  => '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
							'xlarge' => '0 20px 25px -5px rgba(0, 0, 0, 0.1)',
						),
						'defaultIconButtonHover' => sanitize_key(
							Settings::get_settings()['animations']['default_icon_button_hover'] ?? 'fill-diagonal'
						),
					),
				),
			),
			'styles'   => array(
				'blocks' => array(
					'designsetgo/container' => $this->get_container_block_styles(),
					'designsetgo/tabs'      => $this->get_tabs_block_styles(),
					'designsetgo/tab'       => $this->get_tab_block_styles(),
				),
			),
		);

		// Only add spacing presets and block spacing defaults when the theme
		// doesn't define its own spacingSizes. Themes that define spacingSizes
		// (e.g. with clamp() or responsive values) should not be overwritten.
		// Blocks inherit the theme's spacing rhythm via blockGap support and
		// the theme's spacing presets resolve automatically.
		if ( ! $this->theme_has_spacing_sizes( $theme_data ) ) {
			$dsg_settings['settings']['spacing']['spacingSizes'] = array_merge(
				$this->get_standard_spacing_sizes(),
				$this->get_legacy_spacing_sizes()
			);
			$dsg_settings['settings']['spacing']['spacingScale'] = array( 'steps' => 0 );

			// Block spacing defaults — only applied when DSG controls the spacing
			// scale. When theme has spacing, blocks inherit from the theme's system.
			$dsg_settings['styles']['blocks']['designsetgo/container']['spacing'] = array(
				'padding'  => array(
					'top'    => 'var(--wp--preset--spacing--40, 1.5rem)',
					'bottom' => 'var(--wp--preset--spacing--40, 1.5rem)',
					'left'   => 'var(--wp--preset--spacing--20, 0.5rem)',
					'right'  => 'var(--wp--preset--spacing--20, 0.5rem)',
				),
				'blockGap' => 'var(--wp--preset--spacing--30, 1rem)',
			);
			$dsg_settings['styles']['blocks']['designsetgo/tabs']['spacing'] = array(
				'margin' => array(
					'top'    => 'var(--wp--preset--spacing--40, 1.5rem)',
					'bottom' => 'var(--wp--preset--spacing--40, 1.5rem)',
				),
			);
			$dsg_settings['styles']['blocks']['designsetgo/tab']['spacing'] = array(
				'padding'  => array(
					'top'    => 'var(--wp--preset--spacing--50, 2rem)',
					'bottom' => 'var(--wp--preset--spacing--50, 2rem)',
					'left'   => 'var(--wp--preset--spacing--40, 1.5rem)',
					'right'  => 'var(--wp--preset--spacing--40, 1.5rem)',
				),
				'blockGap' => 'var(--wp--preset--spacing--30, 1rem)',
			);
		}

		// Only add font sizes if theme doesn't define them.
		if ( ! $this->theme_has_font_sizes( $theme_data ) ) {
			$dsg_settings['settings']['typography'] = array(
				'fontSizes' => $this->get_font_sizes(),
			);
		}

		return $theme_json->update_with( $dsg_settings );
	}

	/**
	 * Check if theme defines spacing sizes.
	 *
	 * @param array $theme_data Theme JSON data.
	 * @return bool True if theme has spacing sizes.
	 */
	private function theme_has_spacing_sizes( $theme_data ) {
		return ! empty( $theme_data['settings']['spacing']['spacingSizes'] );
	}

	/**
	 * Check if theme defines font sizes.
	 *
	 * @param array $theme_data Theme JSON data.
	 * @return bool True if theme has font sizes.
	 */
	private function theme_has_font_sizes( $theme_data ) {
		return ! empty( $theme_data['settings']['typography']['fontSizes'] );
	}

	/**
	 * Get WordPress standard spacing sizes.
	 *
	 * These are used as fallbacks when the active theme does not define
	 * its own spacingSizes in theme.json.
	 *
	 * @return array Standard spacing sizes.
	 */
	private function get_standard_spacing_sizes() {
		return array(
			array(
				'slug' => '20',
				'size' => '0.5rem',
				'name' => __( '1 — 8px', 'designsetgo' ),
			),
			array(
				'slug' => '30',
				'size' => '1rem',
				'name' => __( '2 — 16px', 'designsetgo' ),
			),
			array(
				'slug' => '40',
				'size' => '1.5rem',
				'name' => __( '3 — 24px', 'designsetgo' ),
			),
			array(
				'slug' => '50',
				'size' => '2rem',
				'name' => __( '4 — 32px', 'designsetgo' ),
			),
			array(
				'slug' => '60',
				'size' => '3rem',
				'name' => __( '5 — 48px', 'designsetgo' ),
			),
			array(
				'slug' => '70',
				'size' => '4rem',
				'name' => __( '6 — 64px', 'designsetgo' ),
			),
			array(
				'slug' => '80',
				'size' => '5rem',
				'name' => __( '7 — 80px', 'designsetgo' ),
			),
		);
	}

	/**
	 * Get legacy DSG spacing sizes for backward compatibility.
	 *
	 * Only added when theme doesn't define custom spacing, to support
	 * existing content using DSG slugs without polluting the spacing picker.
	 *
	 * @return array Legacy spacing sizes.
	 */
	private function get_legacy_spacing_sizes() {
		return array(
			array(
				'slug' => 'xxs',
				'size' => '0.25rem',
				'name' => __( '2XS — 4px (legacy)', 'designsetgo' ),
			),
			array(
				'slug' => 'xs',
				'size' => '0.5rem',
				'name' => __( 'XS — 8px (legacy)', 'designsetgo' ),
			),
			array(
				'slug' => 'sm',
				'size' => '0.75rem',
				'name' => __( 'S — 12px (legacy)', 'designsetgo' ),
			),
			array(
				'slug' => 'md',
				'size' => '1rem',
				'name' => __( 'M — 16px (legacy)', 'designsetgo' ),
			),
			array(
				'slug' => 'lg',
				'size' => '1.5rem',
				'name' => __( 'L — 24px (legacy)', 'designsetgo' ),
			),
			array(
				'slug' => 'xl',
				'size' => '2rem',
				'name' => __( 'XL — 32px (legacy)', 'designsetgo' ),
			),
			array(
				'slug' => 'xxl',
				'size' => '3rem',
				'name' => __( '2XL — 48px (legacy)', 'designsetgo' ),
			),
			array(
				'slug' => 'xxxl',
				'size' => '4rem',
				'name' => __( '3XL — 64px (legacy)', 'designsetgo' ),
			),
			array(
				'slug' => 'xxxxl',
				'size' => '5rem',
				'name' => __( '4XL — 80px (legacy)', 'designsetgo' ),
			),
			array(
				'slug' => 'xxxxxl',
				'size' => '6rem',
				'name' => __( '5XL — 96px (legacy)', 'designsetgo' ),
			),
			array(
				'slug' => 'jumbo',
				'size' => '8rem',
				'name' => __( 'Jumbo — 128px (legacy)', 'designsetgo' ),
			),
			array(
				'slug' => 'mega',
				'size' => '10rem',
				'name' => __( 'Mega — 160px (legacy)', 'designsetgo' ),
			),
		);
	}

	/**
	 * Get font sizes with user customizations.
	 *
	 * @return array Font sizes.
	 */
	private function get_font_sizes() {
		return array(
			array(
				'slug' => 'small',
				'size' => '0.875rem',
				'name' => __( 'Small (14px)', 'designsetgo' ),
			),
			array(
				'slug' => 'medium',
				'size' => '1rem',
				'name' => __( 'Medium (16px)', 'designsetgo' ),
			),
			array(
				'slug' => 'large',
				'size' => '1.5rem',
				'name' => __( 'Large (24px)', 'designsetgo' ),
			),
			array(
				'slug' => 'x-large',
				'size' => '2rem',
				'name' => __( 'XL (32px)', 'designsetgo' ),
			),
			array(
				'slug' => 'xx-large',
				'size' => '2.5rem',
				'name' => __( 'XXL (40px)', 'designsetgo' ),
			),
		);
	}

	/**
	 * Get Container block global styles (non-spacing).
	 *
	 * Returns border and color defaults. Spacing (padding, blockGap) is added
	 * conditionally in extend_theme_json() only when the theme doesn't define
	 * its own spacingSizes, so blocks inherit the theme's spacing rhythm.
	 *
	 * @return array Container block styles.
	 */
	private function get_container_block_styles() {
		return array(
			'border' => array(
				'radius' => 'var(--wp--custom--designsetgo--border-radius--medium)',
			),
			'color'  => array(
				'background' => 'transparent',
			),
		);
	}

	/**
	 * Get Tabs block global styles (non-spacing).
	 *
	 * Returns typography, color, and element defaults. Spacing (margin) is
	 * added conditionally in extend_theme_json() only when the theme doesn't
	 * define its own spacingSizes.
	 *
	 * @return array Tabs block styles.
	 */
	private function get_tabs_block_styles() {
		return array(
			'typography' => array(
				'fontSize'   => 'var(--wp--preset--font-size--medium)',
				'fontWeight' => '500',
			),
			'color'      => array(
				'text' => 'var(--wp--preset--color--contrast)',
			),
			'elements'   => array(
				'button' => array(
					'color'   => array(
						'text'       => 'var(--wp--preset--color--contrast)',
						'background' => 'transparent',
					),
					':hover'  => array(
						'color' => array(
							'text' => 'var(--wp--preset--color--accent-2)',
						),
					),
					':active' => array(
						'color'  => array(
							'text'       => 'var(--wp--preset--color--accent-2)',
							'background' => 'var(--wp--preset--color--base)',
						),
						'border' => array(
							'color' => 'var(--wp--preset--color--accent-2)',
							'width' => '2px',
						),
					),
				),
			),
		);
	}

	/**
	 * Get Tab block global styles (non-spacing).
	 *
	 * Returns color defaults. Spacing (padding, blockGap) is added
	 * conditionally in extend_theme_json() only when the theme doesn't
	 * define its own spacingSizes.
	 *
	 * @return array Tab block styles.
	 */
	private function get_tab_block_styles() {
		return array(
			'color' => array(
				'background' => 'transparent',
				'text'       => 'var(--wp--preset--color--contrast)',
			),
		);
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'DesignSetGo', 'designsetgo' ),
			__( 'DesignSetGo', 'designsetgo' ),
			'manage_options',
			'designsetgo',
			array( $this, 'render_admin_page' ),
			'dashicons-layout',
			59
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		// Verify user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'designsetgo' ) );
		}

		// Add REST API nonce for security.
		$nonce = wp_create_nonce( 'wp_rest' );
		echo '<div id="designsetgo-admin-root" data-nonce="' . esc_attr( $nonce ) . '"></div>';

		// Enqueue admin script.
		$asset_file_path = DESIGNSETGO_PATH . 'build/admin.asset.php';

		if ( ! file_exists( $asset_file_path ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'DesignSetGo: Admin asset file not found. Run `npm run build`.' );
			}
			return;
		}

		$asset_file = include $asset_file_path;

		if ( ! is_array( $asset_file ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'DesignSetGo: Invalid admin asset file format.' );
			}
			return;
		}

		wp_enqueue_script(
			'designsetgo-admin',
			DESIGNSETGO_URL . 'build/admin.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_enqueue_style(
			'designsetgo-admin',
			DESIGNSETGO_URL . 'build/admin.css',
			array( 'wp-components' ),
			$asset_file['version']
		);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'designsetgo/v1',
			'/global-styles',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_global_styles' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		register_rest_route(
			'designsetgo/v1',
			'/global-styles',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_global_styles' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'args'                => array(
					'spacing'    => array(
						'type'        => 'object',
						'description' => __( 'Spacing style overrides.', 'designsetgo' ),
					),
					'typography' => array(
						'type'        => 'object',
						'description' => __( 'Typography style overrides.', 'designsetgo' ),
					),
					'color'      => array(
						'type'        => 'object',
						'description' => __( 'Color style overrides.', 'designsetgo' ),
					),
					'border'     => array(
						'type'        => 'object',
						'description' => __( 'Border style overrides.', 'designsetgo' ),
					),
				),
			)
		);
	}

	/**
	 * Check read permission for REST API.
	 *
	 * @param \WP_REST_Request $_request Request object (unused but required by REST API).
	 * @return bool True if user has permission.
	 */
	public function check_read_permission( $_request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check write permission for REST API.
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
	 * Get global styles via REST API.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_global_styles() {
		$styles = get_option( 'designsetgo_global_styles', array() );
		return rest_ensure_response( $styles );
	}

	/**
	 * Update global styles via REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 */
	public function update_global_styles( $request ) {
		$styles = $request->get_json_params();

		// Validate and sanitize input.
		$sanitized_styles = $this->sanitize_global_styles( $styles );

		if ( is_wp_error( $sanitized_styles ) ) {
			return $sanitized_styles;
		}

		update_option( 'designsetgo_global_styles', $sanitized_styles );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Sanitize global styles data.
	 *
	 * @param mixed $styles Styles data to sanitize.
	 * @return array|\WP_Error Sanitized styles or error.
	 */
	private function sanitize_global_styles( $styles ) {
		if ( ! is_array( $styles ) ) {
			return new \WP_Error(
				'invalid_format',
				__( 'Styles data must be an array.', 'designsetgo' ),
				array( 'status' => 400 )
			);
		}

		$sanitized = array();

		// Sanitize each key-value pair.
		foreach ( $styles as $key => $value ) {
			$sanitized_key = sanitize_key( $key );

			// Handle nested arrays (for complex style structures).
			if ( is_array( $value ) ) {
				$sanitized[ $sanitized_key ] = $this->sanitize_styles_array( $value );
			} else {
				$text = sanitize_text_field( $value );
				$sanitized[ $sanitized_key ] = $this->is_valid_css_value( $text ) ? $text : '';
			}
		}

		return $sanitized;
	}

	/**
	 * Validate a CSS value string against the allowed pattern allowlist.
	 *
	 * Accepts values that match any of the following patterns (permissive enough
	 * for typical theme.json values):
	 *  - Numeric with optional unit: -?[\d.]+(px|em|rem|%|vh|vw|fr|ch|s|ms)?
	 *  - CSS custom property reference: var(--name, fallback) — fallback may
	 *    contain nested var() / calc() / etc.
	 *  - Hex color: #RGB / #RRGGBB / #RGBA / #RRGGBBAA
	 *  - Functional color: rgb()/rgba()/hsl()/hsla() — no url(/expression(
	 *  - Math functions: calc()/clamp()/min()/max() — common in theme.json
	 *  - Named keyword (a-z + hyphens): e.g. transparent, normal, bold
	 *  - Typography family list: letters, digits, spaces, commas, quotes, underscores, hyphens
	 *
	 * Every functional form is gated against `<`, `;`, `url(`, `expression(`,
	 * and `javascript:` to keep injection vectors out even where browsers would
	 * already reject the result as invalid CSS.
	 *
	 * @param string $value CSS value to validate.
	 * @return bool True when the value is acceptable.
	 */
	private function is_valid_css_value( $value ) {
		// Numeric with optional CSS unit.
		if ( preg_match( '/^-?[\d.]+(px|em|rem|%|vh|vw|fr|ch|s|ms)?$/', $value ) ) {
			return true;
		}

		// Hex color.
		if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) {
			return true;
		}

		// Named keyword: lowercase letters and hyphens only.
		if ( preg_match( '/^[a-z][a-z0-9-]*$/', $value ) ) {
			return true;
		}

		// Typography family list: letters, digits, spaces, commas, quotes, underscores, hyphens.
		if ( preg_match( '/^[a-zA-Z0-9 ,.\'"_-]+$/', $value ) ) {
			return true;
		}

		// Functional CSS values (var, calc, clamp, min, max, rgb/rgba/hsl/hsla).
		// We allow nested calls inside the args (e.g. var(--a, var(--b))) but
		// blocklist the dangerous CSS sinks. Browsers would reject these anyway
		// when the value resolves, but we don't want to persist them.
		if ( preg_match( '/^(var|calc|clamp|min|max|rgba?|hsla?)\(/i', $value ) && str_ends_with( $value, ')' ) ) {
			if (
				! str_contains( $value, '<' )
				&& ! str_contains( $value, ';' )
				&& false === stripos( $value, 'url(' )
				&& false === stripos( $value, 'expression(' )
				&& false === stripos( $value, 'javascript:' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively sanitize nested style arrays.
	 *
	 * Keys are run through sanitize_key(). String leaf values are validated
	 * against the CSS value allowlist in is_valid_css_value(); values that do
	 * not match are silently dropped (empty string) rather than stored.
	 *
	 * @param array $array Array to sanitize.
	 * @return array Sanitized array.
	 */
	private function sanitize_styles_array( $array ) {
		$sanitized = array();

		foreach ( $array as $key => $value ) {
			$sanitized_key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $sanitized_key ] = $this->sanitize_styles_array( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $sanitized_key ] = floatval( $value );
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $sanitized_key ] = (bool) $value;
			} else {
				$text = sanitize_text_field( $value );
				$sanitized[ $sanitized_key ] = $this->is_valid_css_value( $text ) ? $text : '';
			}
		}

		return $sanitized;
	}
}
