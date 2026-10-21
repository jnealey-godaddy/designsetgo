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
	 * Whether the script, stylesheet and settings have been queued.
	 *
	 * @var bool
	 */
	private $assets_loaded = false;

	/**
	 * Whether a template part opted in on its own (per-part controls) during
	 * this request, so the critical custom properties are needed even when the
	 * global setting is off.
	 *
	 * @var bool
	 */
	private $per_part_used = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'output_inline_styles' ), 20 );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		// Template parts are dynamic blocks, so the editor's save-time classes
		// never reach the markup; apply them while rendering.
		add_filter( 'render_block_core/template-part', array( $this, 'apply_template_part_options' ), 10, 2 );
	}

	/**
	 * Apply the per-header sticky options to a rendered template part.
	 *
	 * Mirrors `applyStickyHeaderClasses` in
	 * src/extensions/sticky-header-controls/index.js: the same classes and the
	 * same defaults (an attribute equal to its default is not stored in the
	 * block comment, so absence means the default).
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function apply_template_part_options( $block_content, $block ) {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( empty( $attrs['dsgoStickyEnabled'] ) || '' === trim( (string) $block_content ) ) {
			return $block_content;
		}

		if ( Extension_Attributes::is_block_excluded( 'core/template-part', Extension_Attributes::get_extension_exclusions( 'sticky-header-controls' ) ) ) {
			return $block_content;
		}

		$processor = Dynamic_Block_Extension_Props::root_processor( $block_content );
		if ( null === $processor || 'FOOTER' === $processor->get_tag() ) {
			return $block_content;
		}

		$classes = array( 'dsgo-sticky-header-enabled' );

		$shadow = isset( $attrs['dsgoStickyShadow'] ) ? $attrs['dsgoStickyShadow'] : 'medium';
		if ( in_array( $shadow, array( 'small', 'medium', 'large' ), true ) ) {
			$classes[] = 'dsgo-sticky-shadow-' . $shadow;
		}

		$shrink = ! isset( $attrs['dsgoStickyShrink'] ) || ! empty( $attrs['dsgoStickyShrink'] );
		if ( $shrink ) {
			$classes[] = 'dsgo-sticky-shrink-logo';
		}
		if ( ! empty( $attrs['dsgoStickyHideOnScroll'] ) ) {
			$classes[] = 'dsgo-sticky-hide-on-scroll-down';
		}
		if ( ! empty( $attrs['dsgoStickyBackground'] ) ) {
			$classes[] = 'dsgo-sticky-bg-on-scroll';
		}
		if ( ! isset( $attrs['dsgoStickySkipTopBar'] ) || ! empty( $attrs['dsgoStickySkipTopBar'] ) ) {
			$classes[] = 'dsgo-sticky-skip-top-bar';
		}

		foreach ( $classes as $class ) {
			$processor->add_class( $class );
		}

		if ( $shrink ) {
			$amount = isset( $attrs['dsgoStickyShrinkAmount'] ) && is_numeric( $attrs['dsgoStickyShrinkAmount'] )
				? (int) $attrs['dsgoStickyShrinkAmount']
				: 50;
			$processor->set_attribute( 'data-dsgo-shrink-amount', (string) max( 0, min( 90, $amount ) ) );
		}

		// This header needs the script even when the global setting is off.
		$this->per_part_used = true;
		$this->load_assets();

		return $processor->get_updated_html();
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

		// Early exit if disabled. Per-header opt-ins load the assets themselves
		// while the template part renders (see apply_template_part_options()).
		if ( ! $sticky_settings['enable'] ) {
			return;
		}

		$this->load_assets();
	}

	/**
	 * Queue the script, stylesheet and localized settings (once per request).
	 */
	private function load_assets() {
		if ( $this->assets_loaded ) {
			return;
		}

		// Get settings.
		$settings        = \DesignSetGo\Admin\Settings::get_settings();
		$sticky_settings = isset( $settings['sticky_header'] ) ? $settings['sticky_header'] : array();

		// Merge with defaults.
		$defaults        = \DesignSetGo\Admin\Settings::get_defaults();
		$sticky_settings = wp_parse_args( $sticky_settings, $defaults['sticky_header'] );

		// Enqueue JavaScript.
		$asset_file = DESIGNSETGO_PATH . 'build/utils/sticky-header.asset.php';

		if ( file_exists( $asset_file ) ) {
			$this->assets_loaded = true;
			$asset               = include $asset_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- build artifact; path resolved from plugin directory

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

		// Early exit if neither the global setting nor a per-header opt-in is on.
		if ( ! $sticky_settings['enable'] && ! $this->per_part_used ) {
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
