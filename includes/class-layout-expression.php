<?php
/** Bounded CSS length, math and Grid track grammar.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/** Parse the supported CSS subset instead of stripping unsafe tokens. */
class Layout_Expression {
	private const UNITS = 'px|em|rem|ex|ch|vw|vh|vmin|vmax|svw|svh|lvw|lvh|dvw|dvh|cm|mm|in|pt|pc|%';

	/**
	 * Validate the supported length or track grammar.
	 *
	 * @param string $value CSS value.
	 * @param bool   $tracks Whether a Grid track list.
	 * @return bool Valid supported expression.
	 */
	public static function valid( string $value, bool $tracks = false ): bool {
		if ( preg_match( '~[^a-zA-Z0-9_\s.,%()+*/-]~', $value ) ) {
			return false;
		}
		$depth = 0;
		foreach ( str_split( $value ) as $char ) {
			$depth += '(' === $char ? 1 : ( ')' === $char ? -1 : 0 );
			if ( $depth < 0 || $depth > 8 ) {
				return false;
			}
		}
		return 0 === $depth && ( $tracks ? self::tracks( $value ) : self::length( $value ) );
	}

	/**
	 * Validate one scalar or sizing function.
	 *
	 * @param string $value Expression.
	 * @return bool Valid length.
	 */
	private static function length( string $value ): bool {
		$value = trim( $value );
		if ( '0' === $value || preg_match( '/^[+-]?(?:[0-9]*\.)?[0-9]+(?:' . self::UNITS . ')$/', $value ) ) {
			return true;
		}
		if ( ! preg_match( '/^(var|calc|min|max|clamp)\((.*)\)$/s', $value, $match ) ) {
			return false;
		}
		$args = self::split( $match[2], ',' );
		if ( 'var' === $match[1] ) {
			return count( $args ) <= 2 && preg_match( '/^--[a-zA-Z_][a-zA-Z0-9_-]*$/', $args[0] ) && ( 1 === count( $args ) || self::length( $args[1] ) );
		}
		if ( 'calc' === $match[1] ) {
			return 1 === count( $args ) && 1 === self::math( $args[0] );
		}
		$count = count( $args );
		if ( ( 'clamp' === $match[1] && 3 !== $count ) || ( 'fit-content' === $match[1] && 1 !== $count ) ) {
			return false;
		}
		foreach ( $args as $arg ) {
			if ( 1 !== self::math( $arg ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Math grammar with unit-aware products and complete operands.
	 *
	 * @param string $value Math expression.
	 * @return int -1 invalid, 0 number, 1 dimension.
	 */
	private static function math( string $value ): int {
		$value = trim( $value );
		foreach ( array( '+-', '*/' ) as $operators ) {
			$depth = 0;
			for ( $i = strlen( $value ) - 1; $i >= 0; --$i ) {
				$char   = $value[ $i ];
				$depth += ')' === $char ? 1 : ( '(' === $char ? -1 : 0 );
				if ( 0 !== $depth || false === strpos( $operators, $char ) || 0 === $i ) {
					continue;
				}
				$left = rtrim( substr( $value, 0, $i ) );
				if ( '' === $left || preg_match( '~[+*/(-]$~', $left ) ) {
					continue; // Unary sign.
				}
				if ( '+-' === $operators && ( ! ctype_space( $value[ $i - 1 ] ) || ! isset( $value[ $i + 1 ] ) || ! ctype_space( $value[ $i + 1 ] ) ) ) {
					continue; // CSS sums require spaces around binary signs.
				}
				$right = trim( substr( $value, $i + 1 ) );
				$a     = self::math( $left );
				$b     = self::math( $right );
				if ( $a < 0 || $b < 0 ) {
					return -1;
				}
				if ( '+' === $char || '-' === $char ) {
					return $a === $b ? $a : -1;
				}
				if ( '/' === $char ) {
					return 0 === $b && ! preg_match( '/^[+-]?0(?:\.0+)?$/', $right ) ? $a : -1;
				}
				return 1 === $a && 1 === $b ? -1 : max( $a, $b );
			}
		}
		if ( preg_match( '/^[+-]?(?:[0-9]*\.)?[0-9]+$/', $value ) ) {
			return 0;
		}
		if ( '(' === substr( $value, 0, 1 ) && ')' === substr( $value, -1 ) ) {
			return self::math( substr( $value, 1, -1 ) );
		}
		return self::length( $value ) ? 1 : -1;
	}

	/**
	 * Validate a sequence of row tracks.
	 *
	 * @param string $value Track list.
	 * @return bool Valid tracks.
	 */
	private static function tracks( string $value ): bool {
		foreach ( self::split( $value, ' ' ) as $track ) {
			if ( preg_match( '/^-(?:[0-9]|\.[0-9])/', $track ) ) {
				return false;
			}
			if ( preg_match( '/^fit-content\((.*)\)$/s', $track, $fit ) && self::length( $fit[1] ) && ! preg_match( '/^-(?:[0-9]|\.[0-9])/', trim( $fit[1] ) ) ) {
				continue;
			}
			if ( in_array( $track, array( 'auto', 'min-content', 'max-content' ), true ) || preg_match( '/^(?:[0-9]*\.)?[0-9]+fr$/', $track ) || self::length( $track ) ) {
				continue;
			}
			if ( ! preg_match( '/^(repeat|minmax)\((.*)\)$/s', $track, $match ) ) {
				return false;
			}
			$args = self::split( $match[2], ',' );
			if ( 2 !== count( $args ) ) {
				return false;
			}
			if ( 'repeat' === $match[1] ) {
				if ( ! preg_match( '/^[1-9][0-9]?$/', $args[0] ) || preg_match( '/\brepeat\(/', $args[1] ) || ! self::tracks( $args[1] ) ) {
					return false;
				}
			} elseif ( ! self::tracks( $args[0] ) || ! self::tracks( $args[1] ) || count( self::split( $args[0], ' ' ) ) > 1 || count( self::split( $args[1], ' ' ) ) > 1 || preg_match( '/fr$|^(?:repeat|minmax|fit-content)\(/', $args[0] ) || preg_match( '/^(?:repeat|minmax|fit-content)\(/', $args[1] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Split only at delimiters outside functions.
	 *
	 * @param string $value Expression.
	 * @param string $delimiter Comma or whitespace.
	 * @return array<string> Trimmed parts, retaining empty comma arguments.
	 */
	private static function split( string $value, string $delimiter ): array {
		$parts = array();
		$start = 0;
		$depth = 0;
		$length = strlen( $value );
		for ( $i = 0; $i < $length; ++$i ) {
			$char   = $value[ $i ];
			$depth += '(' === $char ? 1 : ( ')' === $char ? -1 : 0 );
			if ( 0 === $depth && ( ' ' === $delimiter ? ctype_space( $char ) : $char === $delimiter ) ) {
				$part = trim( substr( $value, $start, $i - $start ) );
				if ( '' !== $part || ',' === $delimiter ) {
					$parts[] = $part;
				}
				$start = $i + 1;
			}
		}
		$parts[] = trim( substr( $value, $start ) );
		return $parts;
	}
}
