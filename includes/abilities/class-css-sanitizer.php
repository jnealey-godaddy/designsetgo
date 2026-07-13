<?php
/**
 * CSS Sanitizer utility for DesignSetGo abilities.
 *
 * Provides comprehensive CSS sanitization to prevent XSS and other
 * CSS injection attacks. Extracted from Custom_CSS_Renderer for
 * reuse across abilities.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.0.0
 */

namespace DesignSetGo\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSS Sanitizer utility class.
 */
class CSS_Sanitizer {

	/**
	 * Dangerous CSS patterns that could be used for XSS attacks.
	 *
	 * @var array<string>
	 */
	private static array $dangerous_patterns = array(
		// JavaScript execution vectors.
		'/expression\s*\(/i',
		'/javascript\s*:/i',
		'/vbscript\s*:/i',
		'/data\s*:/i',

		// Mozilla-specific binding (XBL).
		'/-moz-binding\s*:/i',

		// Behavior/HTC files (IE).
		'/behavior\s*:/i',

		// Import statements that could load external resources.
		'/@import\s+/i',

		// Charset manipulation.
		'/@charset\s+/i',

		// URL functions with potentially dangerous protocols.
		'/url\s*\(\s*[\'"]?\s*(?:javascript|vbscript|data):/i',

		// CSS custom properties used for data exfiltration.
		// Only block suspicious patterns, not all custom properties.
		'/var\s*\(\s*--[^)]*url\s*\(/i',
	);

	/**
	 * Allowed URL protocols for CSS url() functions.
	 *
	 * @var array<string>
	 */
	private static array $allowed_protocols = array(
		'http',
		'https',
	);

	/**
	 * Sanitize CSS string.
	 *
	 * Removes potentially dangerous CSS patterns while preserving
	 * valid CSS for styling purposes.
	 *
	 * @param string $css CSS string to sanitize.
	 * @return string Sanitized CSS.
	 */
	public static function sanitize( string $css ): string {
		if ( empty( $css ) ) {
			return '';
		}

		// Remove null bytes and other control characters first.
		$css = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $css );

		// Decode HTML entities BEFORE stripping tags to catch encoded attacks.
		// e.g., &lt;script&gt; becomes <script> which can then be stripped.
		$css = html_entity_decode( $css, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Remove HTML tags after decoding.
		$css = wp_strip_all_tags( $css );

		// Decode again in case of double-encoding attacks, then strip again.
		$css = html_entity_decode( $css, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$css = wp_strip_all_tags( $css );

		// Decode CSS escape sequences before pattern matching, for the same
		// reason the HTML entities are decoded above: the browser resolves them
		// before applying the declaration, so `expression\28 1\29` and
		// `java\0script:` are live code that the patterns below — which match
		// literal text — would otherwise walk straight past.
		$css = \designsetgo_normalize_css_escapes( $css );

		// Remove dangerous patterns.
		foreach ( self::$dangerous_patterns as $pattern ) {
			$css = preg_replace( $pattern, '/* blocked */', $css );
		}

		// Sanitize url() functions.
		$css = self::sanitize_urls( $css );

		// Ensure balanced braces.
		$css = self::balance_braces( $css );

		return trim( $css );
	}

	/**
	 * Sanitize CSS for a specific block selector.
	 *
	 * Processes CSS that uses "selector" as a placeholder for the block's
	 * unique selector, replacing it with the actual selector.
	 *
	 * @param string $css CSS string with "selector" placeholder.
	 * @param string $selector Actual CSS selector to use.
	 * @return string Sanitized CSS with selector replaced.
	 */
	public static function sanitize_for_block( string $css, string $selector ): string {
		// First sanitize the CSS.
		$css = self::sanitize( $css );

		// Sanitize the selector (only allow valid CSS selector characters).
		$selector = preg_replace( '/[^a-zA-Z0-9_\-\.\#\[\]\=\"\'\s\:\>\+\~\*]/', '', $selector );

		// Replace "selector" placeholder with actual selector.
		$css = str_replace( 'selector', $selector, $css );

		return $css;
	}

	/**
	 * Sanitize URL functions in CSS.
	 *
	 * Ensures url() functions only use allowed protocols.
	 *
	 * @param string $css CSS string.
	 * @return string CSS with sanitized URLs.
	 */
	private static function sanitize_urls( string $css ): string {
		// Match url() functions.
		$pattern = '/url\s*\(\s*([\'"]?)([^)]+)\1\s*\)/i';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$quote = $matches[1];
				$url   = trim( $matches[2] );

				// Check if URL has a protocol.
				if ( preg_match( '/^([a-zA-Z]+):/', $url, $protocol_match ) ) {
					$protocol = strtolower( $protocol_match[1] );

					// Only allow safe protocols.
					if ( ! in_array( $protocol, self::$allowed_protocols, true ) ) {
						return '/* blocked-url */';
					}
				}

				// Sanitize the URL.
				$url = esc_url( $url );

				if ( empty( $url ) ) {
					return '/* blocked-url */';
				}

				return 'url(' . $quote . $url . $quote . ')';
			},
			$css
		);
	}

	/**
	 * Ensure CSS has balanced braces.
	 *
	 * Adds closing braces if needed to prevent CSS breaking out
	 * of its intended scope.
	 *
	 * @param string $css CSS string.
	 * @return string CSS with balanced braces.
	 */
	private static function balance_braces( string $css ): string {
		$open_count  = substr_count( $css, '{' );
		$close_count = substr_count( $css, '}' );

		// Add missing closing braces.
		if ( $open_count > $close_count ) {
			$css .= str_repeat( '}', $open_count - $close_count );
		}

		// Remove excess closing braces (trim from end).
		if ( $close_count > $open_count ) {
			$excess = $close_count - $open_count;
			for ( $i = 0; $i < $excess; $i++ ) {
				$pos = strrpos( $css, '}' );
				if ( false !== $pos ) {
					$css = substr_replace( $css, '', $pos, 1 );
				}
			}
		}

		return $css;
	}

	/**
	 * Validate that CSS is syntactically reasonable.
	 *
	 * Basic validation to catch obviously malformed CSS.
	 *
	 * @param string $css CSS string to validate.
	 * @return bool True if CSS appears valid.
	 */
	public static function is_valid( string $css ): bool {
		if ( empty( $css ) ) {
			return true; // Empty CSS is valid.
		}

		// Check for balanced braces.
		if ( substr_count( $css, '{' ) !== substr_count( $css, '}' ) ) {
			return false;
		}

		// Check for balanced parentheses.
		if ( substr_count( $css, '(' ) !== substr_count( $css, ')' ) ) {
			return false;
		}

		// Check for balanced brackets.
		if ( substr_count( $css, '[' ) !== substr_count( $css, ']' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Extract and sanitize CSS for responsive breakpoints.
	 *
	 * @param array<string, string> $css_array Array with 'desktop', 'tablet', 'mobile' keys.
	 * @return array<string, string> Sanitized CSS array.
	 */
	public static function sanitize_responsive( array $css_array ): array {
		$sanitized = array();

		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $breakpoint ) {
			if ( isset( $css_array[ $breakpoint ] ) ) {
				$sanitized[ $breakpoint ] = self::sanitize( $css_array[ $breakpoint ] );
			}
		}

		return $sanitized;
	}
}
