<?php
/**
 * Scroll Marquee Styles
 *
 * The Scrolling Gallery block's images have no forced border-radius default
 * (see designsetgo/scroll-marquee's native border support). When a marquee
 * doesn't set its own radius, this generates a low-specificity CSS rule from
 * the site's Global Styles core/image border radius so the marquee inherits
 * whatever an author configured for Image blocks in the Site Editor. Any
 * explicit radius set on the marquee itself is inline (via block supports)
 * and always wins over this rule.
 *
 * @package DesignSetGo
 * @since 2.4.0
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scroll Marquee Styles handler.
 */
class Scroll_Marquee_Styles {

	/**
	 * Cached CSS output.
	 *
	 * @var string|null
	 */
	private $cached_css = null;

	/**
	 * Whether frontend CSS has been injected for this request.
	 *
	 * @var bool
	 */
	private $frontend_injected = false;

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'render_block_designsetgo/scroll-marquee', array( $this, 'maybe_inject_frontend' ) );
		add_action( 'enqueue_block_assets', array( $this, 'inject_editor' ) );
	}

	/**
	 * Inject the inherited border-radius CSS when a scroll-marquee block is rendered.
	 *
	 * @param string $block_content The block content.
	 * @return string The unmodified block content.
	 */
	public function maybe_inject_frontend( $block_content ) {
		if ( is_admin() || $this->frontend_injected ) {
			return $block_content;
		}

		$css = $this->get_css();
		if ( empty( $css ) ) {
			$this->frontend_injected = true;
			return $block_content;
		}

		if ( wp_style_is( 'designsetgo-frontend', 'registered' ) ) {
			wp_add_inline_style( 'designsetgo-frontend', $css );
		} else {
			wp_register_style( 'designsetgo-scroll-marquee-global-styles', false, array(), DESIGNSETGO_VERSION );
			wp_enqueue_style( 'designsetgo-scroll-marquee-global-styles' );
			wp_add_inline_style( 'designsetgo-scroll-marquee-global-styles', $css );
		}

		$this->frontend_injected = true;
		return $block_content;
	}

	/**
	 * Inject the inherited border-radius CSS in the block editor.
	 *
	 * Uses enqueue_block_assets which fires in both editor and frontend.
	 * The is_admin() check limits this to the editor context only.
	 */
	public function inject_editor() {
		if ( ! is_admin() ) {
			return;
		}

		$css = $this->get_css();
		if ( empty( $css ) ) {
			return;
		}

		if ( wp_style_is( 'designsetgo-scroll-marquee-style', 'enqueued' ) ) {
			wp_add_inline_style( 'designsetgo-scroll-marquee-style', $css );
		} else {
			wp_register_style( 'designsetgo-scroll-marquee-global-styles-editor', false, array(), DESIGNSETGO_VERSION );
			wp_enqueue_style( 'designsetgo-scroll-marquee-global-styles-editor' );
			wp_add_inline_style( 'designsetgo-scroll-marquee-global-styles-editor', $css );
		}
	}

	/**
	 * Get the generated CSS, with caching.
	 *
	 * @return string Generated CSS or empty string.
	 */
	private function get_css() {
		if ( null !== $this->cached_css ) {
			return $this->cached_css;
		}

		$this->cached_css = $this->generate_css();
		return $this->cached_css;
	}

	/**
	 * Generate the inherited border-radius CSS from Global Styles core/image.
	 *
	 * @return string Generated CSS or empty string.
	 */
	private function generate_css() {
		$block_styles = wp_get_global_styles( array(), array( 'block_name' => 'core/image' ) );
		if ( ! is_array( $block_styles ) || empty( $block_styles['border']['radius'] ) ) {
			return '';
		}

		$declarations = $this->extract_radius_declarations( $block_styles['border']['radius'] );
		if ( empty( $declarations ) ) {
			return '';
		}

		$rule = implode( ";\n\t", $declarations );

		return ".dsgo-scroll-marquee__image {\n\t" . $rule . ";\n}\n";
	}

	/**
	 * Build border-radius CSS declarations from a Global Styles radius value.
	 *
	 * @param string|array $radius Radius value: shorthand string or per-corner array.
	 * @return string[] Array of "property:value" strings.
	 */
	private function extract_radius_declarations( $radius ) {
		$declarations = array();

		if ( is_string( $radius ) ) {
			$declarations[] = 'border-radius:' . $this->sanitize_css_value( $radius );
			return $declarations;
		}

		if ( is_array( $radius ) ) {
			$corners = array(
				'topLeft'     => 'border-top-left-radius',
				'topRight'    => 'border-top-right-radius',
				'bottomLeft'  => 'border-bottom-left-radius',
				'bottomRight' => 'border-bottom-right-radius',
			);
			foreach ( $corners as $key => $prop ) {
				if ( ! empty( $radius[ $key ] ) ) {
					$declarations[] = $prop . ':' . $this->sanitize_css_value( $radius[ $key ] );
				}
			}
		}

		return $declarations;
	}

	/**
	 * Sanitize a CSS value from Global Styles.
	 *
	 * Uses safecss_filter_attr() to strip dangerous CSS content (expressions,
	 * data URIs, JavaScript protocols) while preserving safe CSS functions
	 * like var(), clamp(), etc.
	 *
	 * @param string $value Raw CSS value.
	 * @return string Sanitized value.
	 */
	private function sanitize_css_value( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		$value = trim( $value );

		// Build a temporary declaration for safecss_filter_attr(), which
		// expects a full "property:value" string.
		$declaration = 'color:' . $value;
		$sanitized   = safecss_filter_attr( $declaration );

		// Extract the value portion after the first colon.
		$parts = explode( ':', $sanitized, 2 );
		if ( count( $parts ) === 2 ) {
			return trim( rtrim( $parts[1], ';' ) );
		}

		// If the declaration was stripped entirely, treat as unsafe.
		return '';
	}
}
