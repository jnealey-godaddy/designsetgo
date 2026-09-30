<?php
/**
 * Conservative HTML-v pattern / PCRE compatibility boundary.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces only the shared syntax, leaving the authored browser pattern intact.
 *
 * Supported: ASCII literals/ranges, basic groups (including non-capturing),
 * alternation, anchors, ordinary/lazy quantifiers and explicitly mapped escapes.
 * Whitespace, ASCII word/digit classes and dot use ECMAScript semantics, not
 * the host PCRE defaults. Unknown syntax is browser-only: v set operations,
 * property escapes, backreferences, lookarounds, class complements, PCRE-only
 * constructs and invalid-v classes must never acquire a different PHP meaning.
 * This is a narrow admission gate, not a JavaScript regex parser. Required,
 * type, length and range validation remain independent of custom patterns.
 */
class Form_Pattern_Compatibility {

	/** ECMAScript WhiteSpace + LineTerminator characters (not Unicode NEL). */
	private const SPACE = '\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

	/**
	 * Whether a value satisfies a pattern the backend can safely enforce.
	 *
	 * @param string $value   Submitted UTF-8 value.
	 * @param string $pattern Authored HTML pattern.
	 * @return bool
	 */
	public static function matches( $value, $pattern ) {
		if ( 1 !== preg_match( '//u', $value ) ) {
			return false;
		}
		$shared = self::shared_pattern( $pattern );
		if ( null === $shared ) {
			return true;
		}
		$regex = "\x01^(?:" . $shared . ")$\x01uD";
		set_error_handler( '__return_true' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Scoped to authored pattern compilation/matching.
		try {
			$compiles = false !== preg_match( $regex, '' );
			$result   = $compiles ? preg_match( $regex, $value ) : false;
		} finally {
			restore_error_handler();
		}
		// Invalid authored patterns are ignored; runtime failures are rejected.
		return ! $compiles || 1 === $result;
	}

	/**
	 * Admit a deliberately limited shared syntax, expanding differing escapes.
	 *
	 * @param string $pattern Authored pattern.
	 * @return string|null PCRE pattern, or null for browser-only validation.
	 */
	private static function shared_pattern( $pattern ) {
		$output     = '';
		$in_class   = false;
		$quantifier = false;
		$length     = strlen( $pattern );
		for ( $i = 0; $i < $length; ++$i ) {
			$char = $pattern[ $i ];
			if ( '\\' === $char ) {
				if ( ++$i >= $length ) {
					return null;
				}
				$escape = self::shared_escape( $pattern[ $i ], $in_class );
				if ( null === $escape ) {
					return null;
				}
				$output    .= $escape;
				$quantifier = false;
				continue;
			}
			if ( $in_class ) {
				if ( ']' === $char ) {
					$in_class = false;
				} elseif ( self::is_alphanumeric( $char ) && $i + 2 < $length && '-' === $pattern[ $i + 1 ] && self::is_alphanumeric( $pattern[ $i + 2 ] ) ) {
					$output .= substr( $pattern, $i, 3 );
					$i      += 2;
					continue;
				} elseif ( ! self::is_alphanumeric( $char ) && '_' !== $char && ' ' !== $char ) {
					// No nested sets, set operators, or unescaped class punctuation.
					return null;
				}
			} elseif ( '[' === $char ) {
				$in_class = true;
				$output  .= '[';
				if ( $i + 1 < $length && '^' === $pattern[ $i + 1 ] ) {
					$output .= '^';
					++$i;
				}
				$quantifier = false;
				continue;
			} elseif ( '(' === $char && $i + 1 < $length && '*' === $pattern[ $i + 1 ] ) {
				return null; // PCRE control verbs are not HTML-v groups.
			} elseif ( '(' === $char && $i + 1 < $length && '?' === $pattern[ $i + 1 ] ) {
				if ( $i + 2 >= $length || ':' !== $pattern[ $i + 2 ] ) {
					return null;
				}
				$output    .= '(?:';
				$i         += 2;
				$quantifier = false;
				continue;
			} elseif ( '{' === $char ) {
				if ( ! preg_match( '/\A\{[0-9]+(?:,[0-9]*)?\}/', substr( $pattern, $i ), $match ) ) {
					return null;
				}
				$output    .= $match[0];
				$i         += strlen( $match[0] ) - 1;
				$quantifier = true;
				continue;
			} elseif ( '+' === $char && $quantifier ) {
				return null; // PCRE possessive quantifiers are invalid in HTML-v.
			} elseif ( '.' === $char ) {
				$char = '[^\x{000A}\x{000D}\x{2028}\x{2029}]';
			} elseif ( ord( $char ) < 32 || ord( $char ) > 126 || ']' === $char || '}' === $char ) {
				return null;
			}
			$output    .= $char;
			$quantifier = ! $in_class && false !== strpos( '*+?', $char );
		}
		return $in_class ? null : $output;
	}

	/**
	 * Map only escapes with known equivalent HTML-v semantics.
	 *
	 * @param string $char     Character following a backslash.
	 * @param bool   $in_class Whether inside a character class.
	 * @return string|null Shared PCRE expression, or unsupported.
	 */
	private static function shared_escape( $char, $in_class ) {
		$classes = array(
			'd' => '0-9',
			'w' => 'A-Za-z0-9_',
			's' => self::SPACE,
		);
		$lower   = strtolower( $char );
		if ( isset( $classes[ $lower ] ) ) {
			if ( $char !== $lower ) {
				return $in_class ? null : '[^' . $classes[ $lower ] . ']';
			}
			return $in_class ? $classes[ $char ] : '[' . $classes[ $char ] . ']';
		}
		$controls = array(
			'n' => '\x{000A}',
			'r' => '\x{000D}',
			't' => '\x{0009}',
			'f' => '\x{000C}',
			'v' => '\x{000B}',
		);
		if ( isset( $controls[ $char ] ) ) {
			return $controls[ $char ];
		}
		$punctuation = '^$\\.*+?()[]{}|/' . ( $in_class ? '-' : '' );
		return false !== strpos( $punctuation, $char ) ? '\\' . $char : null;
	}

	/**
	 * Recognize ASCII range endpoints without locale-dependent ctype rules.
	 *
	 * @param string $char Pattern character.
	 * @return bool
	 */
	private static function is_alphanumeric( $char ) {
		return 1 === preg_match( '/\A[A-Za-z0-9]\z/', $char );
	}
}
