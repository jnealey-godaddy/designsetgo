<?php
/**
 * Custom CSS Renderer Class
 *
 * Handles rendering custom CSS for blocks on the frontend.
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
 * Custom CSS Renderer Class - Handles custom CSS output
 *
 * This class collects custom CSS from blocks during rendering and outputs
 * it in the footer. It provides several filter hooks for developers to
 * customize the CSS processing and output.
 *
 * Available Filter Hooks:
 *
 * 1. designsetgo/custom_css_block
 *    - Filters individual block CSS during collection (before processing)
 *    - Parameters: $custom_css, $block_name, $block
 *    - Return null/empty to skip the block's CSS entirely
 *    - Use case: Modify CSS per block, add preprocessing, conditional CSS
 *
 * 2. designsetgo/custom_css_class_name
 *    - Filters the generated CSS class name
 *    - Parameters: $class_name, $hash, $block_name, $custom_css
 *    - Use case: Custom class naming schemes, integrate with existing systems
 *
 * 3. designsetgo/custom_css_sanitize
 *    - Filters CSS after security sanitization
 *    - Parameters: $css, $original_css
 *    - Use case: Additional sanitization, allowlist specific CSS features
 *    - WARNING: Ensure security is maintained when using this filter
 *
 * 4. designsetgo/custom_css_processed
 *    - Filters processed CSS for each block (after sanitization)
 *    - Parameters: $sanitized_css, $class_name, $block_name, $hash
 *    - Use case: Minification, vendor prefixing, post-processing
 *
 * 5. designsetgo/custom_css_output
 *    - Filters complete CSS output before rendering
 *    - Parameters: $output_css, $custom_css_data
 *    - Use case: Global minification, external file generation, caching
 *    - Return empty string to prevent inline output
 *
 * Example Usage:
 *
 * ```php
 * // Minify all CSS output
 * add_filter( 'designsetgo/custom_css_output', function( $css ) {
 *     return preg_replace( '/\s+/', ' ', $css );
 * } );
 *
 * // Skip CSS for specific blocks
 * add_filter( 'designsetgo/custom_css_block', function( $css, $block_name ) {
 *     if ( 'core/paragraph' === $block_name ) {
 *         return null; // Skip this block
 *     }
 *     return $css;
 * }, 10, 2 );
 *
 * // Custom class naming
 * add_filter( 'designsetgo/custom_css_class_name', function( $class_name, $hash ) {
 *     return 'my-custom-prefix-' . $hash;
 * }, 10, 2 );
 * ```
 *
 * @since 1.0.0
 * @since 1.2.0 Added filter hooks for CSS customization.
 */
class Custom_CSS_Renderer {
	/**
	 * Collected custom CSS from all blocks on the page.
	 * Format: [ hash => [ 'css' => string, 'class' => string, 'block' => string ] ]
	 *
	 * @var array
	 */
	private $custom_css = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'render_block', array( $this, 'collect_custom_css' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'render_custom_css' ), 999 );
	}

	/**
	 * Collect custom CSS from blocks as they render.
	 *
	 * @param string $block_content Block content.
	 * @param array  $block         Block data.
	 * @return string Block content.
	 */
	public function collect_custom_css( $block_content, $block ) {
		// Check if block has custom CSS attribute and a valid block name.
		if ( isset( $block['attrs']['dsgoCustomCSS'] ) && ! empty( $block['attrs']['dsgoCustomCSS'] ) && isset( $block['blockName'] ) ) {
			$custom_css = $block['attrs']['dsgoCustomCSS'];

			/**
			 * Filters individual block CSS during collection.
			 *
			 * Allows modification of CSS before it's processed and stored.
			 *
			 * @since 1.2.0
			 *
			 * @param string $custom_css The raw CSS from the block attribute.
			 * @param string $block_name The full block name (e.g., 'core/paragraph').
			 * @param array  $block      The complete block data array.
			 *
			 * @return string|null Modified CSS string, or null to skip this block's CSS entirely.
			 */
			$custom_css = apply_filters( 'designsetgo/custom_css_block', $custom_css, $block['blockName'], $block );

			// Allow filter to skip CSS by returning null or empty string.
			if ( empty( $custom_css ) ) {
				return $block_content;
			}

			// Generate hash for this CSS (same algorithm as JavaScript).
			$hash = $this->hash_code( $custom_css . $block['blockName'] );

			/**
			 * Filters the CSS class name generated for custom CSS.
			 *
			 * Allows customization of how class names are generated.
			 *
			 * @since 1.2.0
			 *
			 * @param string $class_name The generated class name (e.g., 'dsgo-custom-css-abc123').
			 * @param string $hash       The hash generated from CSS content and block name.
			 * @param string $block_name The full block name (e.g., 'core/paragraph').
			 * @param string $custom_css The CSS content being processed.
			 *
			 * @return string Modified class name.
			 */
			$class_name = apply_filters( 'designsetgo/custom_css_class_name', 'dsgo-custom-css-' . $hash, $hash, $block['blockName'], $custom_css );

			// Store CSS with hash as key to avoid duplicates.
			$this->custom_css[ $hash ] = array(
				'css'   => $custom_css,
				'class' => $class_name,
				'block' => $block['blockName'],
			);
		}

		return $block_content;
	}

	/**
	 * Render all collected custom CSS in the footer.
	 */
	public function render_custom_css() {
		if ( empty( $this->custom_css ) ) {
			return;
		}

		$output_css = '';

		foreach ( $this->custom_css as $hash => $data ) {
			$css        = $data['css'];
			$class_name = $data['class'];
			$block_name = $data['block'];

			// Replace "selector" with actual class name.
			$processed_css = $this->replace_selector( $css, $class_name );

			// Sanitize CSS (remove <script> tags and other dangerous content).
			$sanitized_css = $this->sanitize_css( $processed_css );

			/**
			 * Filters the processed CSS for a single block before adding to output.
			 *
			 * This runs after selector replacement and sanitization, allowing final
			 * modifications like minification, vendor prefixing, or custom processing.
			 *
			 * @since 1.2.0
			 *
			 * @param string $sanitized_css The processed and sanitized CSS.
			 * @param string $class_name    The CSS class name being used.
			 * @param string $block_name    The full block name (e.g., 'core/paragraph').
			 * @param string $hash          The hash identifier for this CSS block.
			 *
			 * @return string Modified CSS string.
			 */
			$sanitized_css = apply_filters( 'designsetgo/custom_css_processed', $sanitized_css, $class_name, $block_name, $hash );

			$output_css .= $sanitized_css . "\n";
		}

		/**
		 * Filters the complete CSS output before rendering.
		 *
		 * Allows modification of all collected CSS before it's output to the page.
		 * Useful for minification, external file generation, or integration with
		 * custom CSS systems.
		 *
		 * @since 1.2.0
		 *
		 * @param string $output_css     The complete CSS string to be output.
		 * @param array  $custom_css_data All collected CSS data (hash => [css, class, block]).
		 *
		 * @return string Modified CSS output.
		 */
		$output_css = apply_filters( 'designsetgo/custom_css_output', $output_css, $this->custom_css );

		// Only output if we have CSS after filtering.
		if ( ! empty( $output_css ) ) {
			echo "\n<!-- DesignSetGo Custom CSS -->\n";
			echo '<style id="designsetgo-custom-css">' . "\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS sanitized by sanitize_css() which strips script/event vectors.
			echo $output_css;
			echo '</style>' . "\n";
		}
	}

	/**
	 * Replace "selector" keyword with actual CSS class.
	 * Supports all variations: selector, selector:hover, selector h3, etc.
	 *
	 * @param string $css        CSS code.
	 * @param string $class_name Actual CSS class to use.
	 * @return string Processed CSS with selector replaced.
	 */
	private function replace_selector( $css, $class_name ) {
		if ( empty( $css ) ) {
			return '';
		}

		// Replace "selector" with the actual class (word boundary aware).
		return preg_replace( '/\bselector\b/', '.' . $class_name, $css );
	}

	/**
	 * Sanitize CSS to remove potentially dangerous content.
	 *
	 * Enhanced security version that blocks:
	 * - Script tags and HTML
	 * - Event handlers
	 * - JavaScript protocols
	 * - Data URIs (prevent data URI attacks)
	 * - Browser-specific XSS vectors
	 * - HTML entities (bypass attempts)
	 *
	 * @param string $css CSS code.
	 * @return string Sanitized CSS.
	 */
	private function sanitize_css( $css ) {
		$original_css = $css;

		// Normalize CSS escape sequences BEFORE any pattern matching. CSS
		// permits arbitrary unicode escapes and null bytes inside tokens,
		// e.g. `java\0script:` or `java\000073cript:`, which would bypass
		// regex strips like `/javascript:/i` if left in place. Decode them
		// to the real characters so downstream checks see the actual text
		// (and legitimate content like `content: "\2192";` is preserved).
		$css = str_replace( "\0", '', $css );
		// CSS unicode escape: backslash + 1..6 hex digits, optional trailing
		// whitespace. Decode to the referenced codepoint so pattern matching
		// sees the actual character.
		$css = preg_replace_callback(
			'/\\\\([0-9a-fA-F]{1,6})\s?/',
			static function ( $match ) {
				$codepoint = hexdec( $match[1] );
				// Reject null and out-of-range codepoints.
				if ( 0 === $codepoint || $codepoint > 0x10FFFF ) {
					return '';
				}
				return mb_chr( $codepoint, 'UTF-8' );
			},
			$css
		);
		// Any remaining backslash escapes of ASCII printable chars.
		$css = preg_replace( '/\\\\([\x20-\x7E])/', '$1', $css );

		// Remove script tags and all HTML tags.
		$css = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $css );
		$css = preg_replace( '/<[^>]+>/i', '', $css );

		// Remove event handlers (onclick, onload, etc.). Match both quoted
		// (`onclick="alert(1)"`) and unquoted (`onclick=alert(1)`) forms.
		$css = preg_replace( '/on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s;{}]+)/is', '', $css );

		// Remove dangerous protocols.
		$css = preg_replace( '/javascript:/i', '', $css );
		$css = preg_replace( '/vbscript:/i', '', $css );

		// Remove data: protocol in url() functions (prevents data URI attacks).
		$css = preg_replace( '/url\s*\(\s*["\']?data:/i', 'url(', $css );

		// Remove -moz-binding (XBL) and -moz-document.
		$css = preg_replace( '/-moz-binding\s*:/i', '', $css );
		$css = preg_replace( '/@-moz-document/i', '', $css );

		// Remove behavior (IE specific).
		$css = preg_replace( '/behavior\s*:/i', '', $css );

		// Remove @import with non-http(s) URLs.
		$css = preg_replace( '/@import\s+url\s*\(\s*["\']?(?!https?:).*?["\']?\s*\)/i', '', $css );
		$css = preg_replace( '/@import\s+["\'](?!https?:).*?["\']/i', '', $css );

		// Remove expression() (IE specific).
		$css = preg_replace( '/expression\s*\(/i', '', $css );

		// Remove HTML entities that could be used to bypass filters.
		$css = preg_replace( '/&#[xX]?[0-9a-fA-F]+;/i', '', $css );

		// Additional WordPress-recommended sanitization.
		$css = wp_strip_all_tags( $css );

		/**
		 * Filters the sanitized CSS output.
		 *
		 * Allows adding custom sanitization rules or modifying the default sanitization.
		 * IMPORTANT: Use with caution - ensure any custom sanitization maintains security.
		 *
		 * @since 1.2.0
		 *
		 * @param string $css          The sanitized CSS after default security processing.
		 * @param string $original_css The original CSS before sanitization.
		 *
		 * @return string Further sanitized or modified CSS.
		 */
		$css = apply_filters( 'designsetgo/custom_css_sanitize', $css, $original_css );

		// Final defense pass: a filter callback could reintroduce a dangerous
		// pattern (accidentally or by a compromised plugin). Re-run the
		// highest-severity strips on the filtered output so no amount of
		// filter misuse can smuggle script tags or javascript: protocols
		// back in.
		$css = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $css );
		$css = preg_replace( '/<[^>]+>/i', '', $css );
		$css = preg_replace( '/javascript:/i', '', $css );
		$css = preg_replace( '/vbscript:/i', '', $css );
		$css = preg_replace( '/expression\s*\(/i', '', $css );
		$css = preg_replace( '/on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s;{}]+)/is', '', $css );

		return $css;
	}

	/**
	 * Simple hash function to generate consistent IDs (matches JavaScript version).
	 *
	 * @param string $str String to hash.
	 * @return string Hash string.
	 */
	private function hash_code( $str ) {
		$hash = 0;
		$len  = strlen( $str );

		for ( $i = 0; $i < $len; $i++ ) {
			$char = ord( $str[ $i ] );
			$hash = ( ( $hash << 5 ) - $hash ) + $char;
			$hash = $hash & 0xFFFFFFFF; // Convert to 32bit integer.
		}

		return base_convert( (string) abs( $hash ), 10, 36 );
	}
}
