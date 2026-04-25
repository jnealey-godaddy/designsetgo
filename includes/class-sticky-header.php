<?php
/**
 * Sticky Header Class
 *
 * Handles sticky header functionality for WordPress template parts.
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sticky Header Class
 */
class Sticky_Header {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'output_inline_styles' ), 20 );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Add body class when sticky header is enabled
	 *
	 * @param array $classes Body classes.
	 * @return array Modified body classes.
	 */
	public function add_body_class( $classes ) {
		// Get settings.
		$settings        = \DesignSetGo\Admin\Settings::get_settings();
		$sticky_settings = isset( $settings['sticky_header'] ) ? $settings['sticky_header'] : array();

		// Merge with defaults.
		$defaults        = \DesignSetGo\Admin\Settings::get_defaults();
		$sticky_settings = wp_parse_args( $sticky_settings, $defaults['sticky_header'] );

		// Add class if enabled.
		if ( $sticky_settings['enable'] ) {
			$classes[] = 'dsgo-sticky-header-enabled';
		}

		return $classes;
	}

	/**
	 * Enqueue sticky header assets
	 */
	public function enqueue_assets() {
		// Get settings.
		$settings        = \DesignSetGo\Admin\Settings::get_settings();
		$sticky_settings = isset( $settings['sticky_header'] ) ? $settings['sticky_header'] : array();

		// Merge with defaults.
		$defaults        = \DesignSetGo\Admin\Settings::get_defaults();
		$sticky_settings = wp_parse_args( $sticky_settings, $defaults['sticky_header'] );

		// Early exit if disabled.
		if ( ! $sticky_settings['enable'] ) {
			return;
		}

		// Enqueue JavaScript.
		$asset_file = DESIGNSETGO_PATH . 'build/utils/sticky-header.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = include $asset_file;

			// Enqueue compiled CSS (extracted by webpack from sticky-header.scss).
			$css_file = DESIGNSETGO_PATH . 'build/utils/sticky-header.css';
			if ( file_exists( $css_file ) ) {
				wp_enqueue_style(
					'designsetgo-sticky-header',
					DESIGNSETGO_URL . 'build/utils/sticky-header.css',
					array(),
					$asset['version']
				);
			}

			wp_enqueue_script(
				'designsetgo-sticky-header',
				DESIGNSETGO_URL . 'build/utils/sticky-header.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			// Localize settings.
			wp_localize_script(
				'designsetgo-sticky-header',
				'dsgStickyHeaderSettings',
				array(
					'enable'                  => (bool) $sticky_settings['enable'],
					'customSelector'          => \DesignSetGo\Admin\Settings::sanitize_css_selector( $sticky_settings['custom_selector'] ),
					'zIndex'                  => absint( $sticky_settings['z_index'] ),
					'shadowOnScroll'          => (bool) $sticky_settings['shadow_on_scroll'],
					'shadowSize'              => sanitize_text_field( $sticky_settings['shadow_size'] ),
					'shrinkOnScroll'          => (bool) $sticky_settings['shrink_on_scroll'],
					'shrinkAmount'            => absint( $sticky_settings['shrink_amount'] ),
					'mobileEnabled'           => (bool) $sticky_settings['mobile_enabled'],
					'mobileBreakpoint'        => absint( $sticky_settings['mobile_breakpoint'] ),
					'transitionSpeed'         => absint( $sticky_settings['transition_speed'] ),
					'scrollThreshold'         => absint( $sticky_settings['scroll_threshold'] ),
					'hideOnScrollDown'        => (bool) $sticky_settings['hide_on_scroll_down'],
					'backgroundOnScroll'      => (bool) $sticky_settings['background_on_scroll'],
					'backgroundScrollColor'   => sanitize_hex_color( $sticky_settings['background_scroll_color'] ),
					'backgroundScrollOpacity' => absint( $sticky_settings['background_scroll_opacity'] ),
					'textScrollColor'         => sanitize_hex_color( $sticky_settings['text_scroll_color'] ),
				)
			);
		}
	}

	/**
	 * Output inline styles for sticky header
	 *
	 * This ensures the CSS is available even before the main stylesheet loads.
	 */
	public function output_inline_styles() {
		// Get settings.
		$settings        = \DesignSetGo\Admin\Settings::get_settings();
		$sticky_settings = isset( $settings['sticky_header'] ) ? $settings['sticky_header'] : array();

		// Merge with defaults.
		$defaults        = \DesignSetGo\Admin\Settings::get_defaults();
		$sticky_settings = wp_parse_args( $sticky_settings, $defaults['sticky_header'] );

		// Early exit if disabled.
		if ( ! $sticky_settings['enable'] ) {
			return;
		}

		// Output only dynamic CSS custom properties that depend on admin settings.
		// All layout rules are in the compiled sticky-header.css bundle.
		?>
		<style id="dsgo-sticky-header-critical">
			:root {
				--dsgo-sticky-header-z-index: <?php echo absint( $sticky_settings['z_index'] ); ?>;
				--dsgo-sticky-header-transition-speed: <?php echo absint( $sticky_settings['transition_speed'] ); ?>ms;
			}
		</style>
		<?php
	}
}
