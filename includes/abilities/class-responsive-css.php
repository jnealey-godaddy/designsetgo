<?php
/**
 * Compile responsive ability inputs into the editor's single custom CSS field.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserve managed breakpoint sections on partial updates. */
class Responsive_CSS {
	/**
	 * Merge supplied rules; unmarked editor-authored CSS remains the base.
	 *
	 * Comment boundaries let subsequent calls replace a breakpoint without
	 * parsing arbitrary CSS. If an author removes them in the editor, that CSS
	 * becomes part of the base and is only replaced by a desktop update.
	 *
	 * @param string               $existing Current canonical custom CSS.
	 * @param array<string, mixed> $input Validated ability CSS inputs.
	 * @return string Sanitized canonical CSS.
	 */
	public static function merge( string $existing, array $input ): string {
		if ( isset( $input['enabled'] ) && false === $input['enabled'] ) {
			return '';
		}
		$parts = array(
			'tablet' => '',
			'mobile' => '',
		);
		$base  = preg_replace_callback(
			'~/\* dsgo-css:(tablet|mobile):start \*/.*?/\* dsgo-css:\1:end \*/~s',
			static function ( $match ) use ( &$parts ) {
				$parts[ $match[1] ] = $match[0];
				return '';
			},
			$existing
		);
		if ( array_key_exists( 'desktop', $input ) ) {
			$base = self::sanitize_input( $input['desktop'] );
		}
		$breakpoints = Generation_Contract::describe()['breakpoints'];
		foreach ( array(
			'tablet' => $breakpoints['tabletMax'],
			'mobile' => $breakpoints['mobileMax'],
		) as $device => $width ) {
			if ( ! array_key_exists( $device, $input ) ) {
				continue;
			}
			$css              = self::sanitize_input( $input[ $device ] );
			$parts[ $device ] = '' === $css ? '' : "/* dsgo-css:$device:start */\n@media (max-width: {$width}px) {\n$css\n}\n/* dsgo-css:$device:end */";
		}
		return CSS_Sanitizer::sanitize( implode( "\n", array_filter( array_merge( array( trim( $base ) ), array_values( $parts ) ), static fn( $part ) => '' !== $part ) ) );
	}

	/**
	 * Remove reserved boundaries from incoming CSS so authors cannot nest them.
	 *
	 * @param string $css Incoming CSS.
	 * @return string Sanitized rules.
	 */
	private static function sanitize_input( string $css ): string {
		return CSS_Sanitizer::sanitize( preg_replace( '~/\* dsgo-css:(tablet|mobile):(start|end) \*/~', '', $css ) );
	}
}
