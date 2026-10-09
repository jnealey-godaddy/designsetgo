<?php
/** Typed CSS value validation for layout composition.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/** Reject invalid declarations before any CSS is compiled. */
class Layout_Values {

	/**
	 * Validate a value against the shared property type.
	 *
	 * @param mixed $value Authored value.
	 * @param array $property Property definition.
	 * @return bool Whether the value is valid.
	 */
	public static function valid( $value, array $property ): bool {
		$type = $property['type'];
		if ( 'integer' === $type || 'number' === $type ) {
			return ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value )
				&& abs( $value ) <= 9999 && abs( round( $value * 10000 ) - $value * 10000 ) <= 0.0000001 && ( 'integer' === $type ? floor( $value ) === (float) $value : $value >= 0 );
		}
		if ( ! is_string( $value ) || strlen( $value ) > 512 || preg_match( '/[^\x09\x0A\x0D\x20-\x7E]/', $value ) || '' === trim( $value ) ) {
			return false;
		}
		$value = trim( $value );
		if ( 'enum' === $type ) {
			return in_array( $value, $property['values'], true );
		}
		if ( 'ratio' === $type ) {
			return 'auto' === $value || ( preg_match( '~^([0-9]*\.?[0-9]+)(?:\s*/\s*([0-9]*\.?[0-9]+))?$~', $value, $matches ) && (float) $matches[1] > 0 && ( ! isset( $matches[2] ) || (float) $matches[2] > 0 ) );
		}
		if ( 'identifier' === $type ) {
			return (bool) preg_match( '/^(?:auto|[a-zA-Z_][a-zA-Z0-9_-]*)$/', $value ) && ! in_array( strtolower( $value ), array( 'span', 'initial', 'inherit', 'unset', 'revert', 'revert-layer' ), true );
		}
		if ( 'line' === $type ) {
			return self::line( $value );
		}
		if ( 'areas' === $type ) {
			return self::areas( $value );
		}
		$keywords = array(
			'size'   => array( 'auto', 'min-content', 'max-content', 'fit-content', 'stretch' ),
			'offset' => array( 'auto' ),
			'gap'    => array( 'normal' ),
			'tracks' => array( 'none', 'auto', 'min-content', 'max-content' ),
		);
		if ( 'size' === $type && in_array( $property['css'], array( 'max-width', 'max-height' ), true ) ) {
			$keywords['size'] = array( 'none', 'min-content', 'max-content', 'fit-content', 'stretch' );
		}
		if ( 'offset' !== $type && preg_match( '/^-(?:[0-9]|\.[0-9])/', $value ) ) {
			return false;
		}
		if ( in_array( $value, $keywords[ $type ] ?? array(), true ) ) {
			return true;
		}
		return Layout_Expression::valid( $value, 'tracks' === $type );
	}

	/**
	 * Validate one or two grid lines.
	 *
	 * @param string $value Grid placement.
	 * @return bool Valid line expression.
	 */
	private static function line( string $value ): bool {
		$parts = explode( '/', $value );
		if ( count( $parts ) > 2 ) {
			return false;
		}
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( ! preg_match( '/^(?:auto|-?[1-9][0-9]{0,2}|[a-zA-Z_][a-zA-Z0-9_-]*|span\s+[1-9][0-9]{0,2})$/', $part ) || in_array( strtolower( $part ), array( 'span', 'initial', 'inherit', 'unset', 'revert', 'revert-layer' ), true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Each named area must form a rectangle in an equal-width matrix.
	 *
	 * @param string $value Quoted rows.
	 * @return bool Valid area matrix.
	 */
	private static function areas( string $value ): bool {
		if ( 'none' === $value ) {
			return true;
		}
		if ( ! preg_match( '/^(?:"[a-zA-Z0-9_.\t -]+"\s*)+$/', $value ) ) {
			return false;
		}
		preg_match_all( '/"([^"]+)"/', $value, $matches );
		$rows   = array_map( static fn( $row ) => preg_split( '/\s+/', trim( $row ) ), $matches[1] );
		$width  = count( $rows[0] );
		$bounds = array();
		foreach ( $rows as $y => $row ) {
			if ( count( $row ) !== $width ) {
				return false;
			}
			foreach ( $row as $x => $name ) {
				if ( preg_match( '/^\.+$/', $name ) ) {
					continue;
				}
				if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $name ) || in_array( strtolower( $name ), array( 'none', 'span', 'auto', 'initial', 'inherit', 'unset', 'revert', 'revert-layer' ), true ) ) {
					return false;
				}
				$b               = $bounds[ $name ] ?? array( $x, $x, $y, $y, 0 );
				$bounds[ $name ] = array( min( $x, $b[0] ), max( $x, $b[1] ), min( $y, $b[2] ), max( $y, $b[3] ), $b[4] + 1 );
			}
		}
		foreach ( $bounds as $b ) {
			if ( ( $b[1] - $b[0] + 1 ) * ( $b[3] - $b[2] + 1 ) !== $b[4] ) {
				return false;
			}
		}
		return true;
	}
}
