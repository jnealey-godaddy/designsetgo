<?php
/** Responsive block layout contract and compiler.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/** Shared validated composition for the editor and generation API. */
class Layout_Support {
	/**
	 * Load the data-only JS/PHP contract.
	 *
	 * @return array Contract.
	 */
	public static function contract(): array {
		static $contract = null;
		if ( null === $contract ) {
			$contract = json_decode( file_get_contents( __DIR__ . '/data/layout-support.json' ), true );
		}
		return $contract;
	}

	/**
	 * Strictly validate and canonicalize optional device overrides.
	 *
	 * @param string $name Block name.
	 * @param mixed  $layout Authored layout.
	 * @return array|\WP_Error Normalized layout or validation error.
	 */
	public static function sanitize( string $name, $layout ) {
		$contract = self::contract();
		$role     = $contract['blocks'][ $name ]['role'] ?? null;
		if ( null === $role || ! is_array( $layout ) ) {
			return self::invalid();
		}
		$normalized = array();
		foreach ( $layout as $device => $values ) {
			if ( ! isset( $contract['devices'][ $device ] ) || ! is_array( $values ) ) {
				return self::invalid();
			}
			foreach ( $values as $key => $value ) {
				$property = $contract['properties'][ $key ] ?? null;
				if ( null === $property || ! self::applies( $role, $property['scope'] ) || ! Layout_Values::valid( $value, $property ) ) {
					return self::invalid();
				}
			}
		}
		foreach ( $contract['devices'] as $device => $width ) {
			foreach ( $contract['properties'] as $key => $property ) {
				if ( isset( $layout[ $device ][ $key ] ) ) {
					$value                           = $layout[ $device ][ $key ];
					$normalized[ $device ][ $key ] = is_string( $value ) ? trim( $value ) : ( 0 == $value ? 0 : round( $value, 4 ) );
				}
			}
		}
		return $normalized;
	}

	/**
	 * Whether a property belongs to this wrapper type.
	 *
	 * @param string $role Block role.
	 * @param string $scope Property scope.
	 * @return bool Whether applicable.
	 */
	public static function applies( string $role, string $scope ): bool {
		return 'all' === $scope || $role === $scope || ( 'container' === $scope && 'item' !== $role );
	}

	/**
	 * Typed REST-discovery schema. Per-role validation also runs at insertion.
	 *
	 * @return array Attribute schema.
	 */
	public static function schema(): array {
		$properties = array();
		foreach ( self::contract()['properties'] as $key => $property ) {
			$type               = $property['type'];
			$properties[ $key ] = array( 'type' => in_array( $type, array( 'integer', 'number' ), true ) ? $type : 'string' );
			if ( 'enum' === $type ) {
				$properties[ $key ]['enum'] = $property['values'];
			}
		}
		$device = array(
			'type' => 'object',
			'additionalProperties' => false,
			'properties' => $properties,
		);
		return array(
			'type' => 'object',
			'additionalProperties' => false,
			'properties' => array_fill_keys( array_keys( self::contract()['devices'] ), $device ),
		);
	}

	/**
	 * Stable JS-compatible selector, with no class for unchanged content.
	 *
	 * @param string $name Block name.
	 * @param mixed  $layout Layout settings.
	 * @return string Optional class.
	 */
	public static function class_name( string $name, $layout ): string {
		$layout = self::sanitize( $name, $layout );
		return is_wp_error( $layout ) || empty( $layout ) ? '' : 'dsgo-layout-' . substr( hash( 'sha256', $name . wp_json_encode( $layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ), 0, 32 );
	}

	/**
	 * Compile scoped cascading declarations. No raw selector input is accepted.
	 *
	 * @param string $name Block name.
	 * @param mixed  $layout Layout settings.
	 * @return string Validated CSS.
	 */
	public static function compile_css( string $name, $layout ): string {
		$class = self::class_name( $name, $layout );
		if ( '' === $class ) {
			return '';
		}
		$layout   = self::sanitize( $name, $layout );
		$contract = self::contract();
		$selector = '.' . $class . '.' . $class;
		$inner    = $contract['blocks'][ $name ]['inner'];
		$css      = '';
		foreach ( $layout as $device => $values ) {
			$rules = array();
			foreach ( $values as $key => $value ) {
				$property = $contract['properties'][ $key ];
				$target   = $selector . ( 'inner' === $property['target'] ? ' > ' . $inner : '' );
				// Align Rows owns placement, including grid-area's row/column shorthand.
				if ( in_array( $key, array( 'gridColumn', 'gridRow', 'gridArea' ), true ) ) {
					$target .= ':not(.dsgo-grid--match-rows > .dsgo-grid__inner > *)';
				}
				$rules[ $target ] = ( $rules[ $target ] ?? '' ) . $property['css'] . ':' . $value . '!important;';
			}
			$declarations = '';
			foreach ( $rules as $target => $rule ) {
				$declarations .= $target . '{' . rtrim( $rule, ';' ) . '}';
			}
			$width = $contract['devices'][ $device ];
			$css  .= $width ? '@media (max-width:' . $width . 'px){' . $declarations . '}' : $declarations;
		}
		return $css;
	}

	/**
	 * Consistent validation error.
	 *
	 * @return \WP_Error Layout error.
	 */
	private static function invalid(): \WP_Error {
		return new \WP_Error( 'invalid_layout', __( 'Layout overrides contain an invalid device, property or CSS value.', 'designsetgo' ) );
	}
}
