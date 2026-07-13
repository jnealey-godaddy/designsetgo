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
	 * Maximum encoding layers peeled before a value is rejected as unconverged.
	 *
	 * Real CSS carries zero or one layer of entity/escape encoding. Anything
	 * needing more is layered deliberately, so the ceiling is generous for
	 * legitimate input and still finite against an attacker choosing the depth.
	 *
	 * @var int
	 */
	private const MAX_DECODE_PASSES = 10;

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

		/*
		 * Peel off ALL encoding layers before any tag-stripping or pattern
		 * matching. There are two independent ones — HTML entities and CSS escape
		 * sequences — and each can hide the other, in either order:
		 *
		 *   `&#92;75rl(...)`   an entity hides a CSS escape that hides `url(`
		 *   `\3c script\3e`    a CSS escape hides a tag from wp_strip_all_tags()
		 *
		 * So no fixed sequence of "decode entities, then decode escapes" is
		 * enough: whichever runs last exposes a layer the other already walked
		 * past. Decode to a fixed point instead, then strip and match on text with
		 * no encoding left to hide behind.
		 *
		 * html_entity_decode() resolves exactly ONE layer of entity nesting per
		 * call, so an attacker can choose the depth:
		 *
		 *   &amp;amp;amp;amp;amp;#106;avascript:alert(1)
		 *
		 * takes six passes to reach `javascript:`. Any cap can therefore be
		 * outrun. What matters is what happens when it IS outrun: returning the
		 * half-decoded string would hand `&#106;avascript:` to a caller that puts
		 * it in an HTML attribute, where the browser finishes the decode for the
		 * attacker. So non-convergence is a REJECTION, not a best effort — the
		 * cap is high enough that no legitimate CSS reaches it (real CSS has zero
		 * or one layer), and anything that does is layered on purpose.
		 */
		$passes = 0;
		do {
			$previous = $css;
			$css      = html_entity_decode( $css, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$css      = \designsetgo_normalize_css_escapes( $css );
			++$passes;
		} while ( $css !== $previous && $passes < self::MAX_DECODE_PASSES );

		if ( $css !== $previous ) {
			return '/* blocked */';
		}

		// Only now can tag-stripping see every tag: a CSS-escaped `\3c script\3e`
		// has been decoded to a real `<script>` above, so this removes it. Before
		// the decode loop it would have sailed straight through.
		$css = wp_strip_all_tags( $css );

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
