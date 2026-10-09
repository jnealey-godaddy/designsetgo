<?php
/**
 * Saved and rendered custom CSS selectors must match the JavaScript save filter.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared custom CSS hash and root-class synchronization. */
class Custom_CSS_Support {
	/**
	 * Hash UTF-16 code units with JavaScript's signed 32-bit semantics.
	 *
	 * @param string $value CSS plus block name.
	 * @return string Base-36 hash used by the editor.
	 */
	public static function hash_code( string $value ): string {
		$hash   = 0;
		$utf16  = self::to_utf16( $value );
		$length = strlen( $utf16 );

		for ( $i = 0; $i + 1 < $length; $i += 2 ) {
			$char = ord( $utf16[ $i ] ) | ( ord( $utf16[ $i + 1 ] ) << 8 );

			$hash = ( $hash << 5 ) - $hash + $char;

			// Truncate to 32 bits, then reinterpret as signed.
			$hash = $hash & 0xFFFFFFFF;
			if ( $hash & 0x80000000 ) {
				$hash -= 0x100000000;
			}
		}

		return base_convert( (string) abs( $hash ), 10, 36 );
	}

	/**
	 * Convert UTF-8 CSS to code units without requiring optional mbstring.
	 *
	 * @param string $value UTF-8 string.
	 * @return string Little-endian UTF-16 bytes.
	 */
	private static function to_utf16( string $value ): string {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			return (string) mb_convert_encoding( $value, 'UTF-16LE', 'UTF-8' );
		}
		$characters = preg_split( '//u', $value, -1, PREG_SPLIT_NO_EMPTY );
		if ( false === $characters ) {
			return '';
		}
		$utf16 = '';
		foreach ( $characters as $character ) {
			$length     = strlen( $character );
			$code_point = ord( $character[0] ) & ( 1 === $length ? 0x7F : 0x7F >> $length );
			for ( $i = 1; $i < $length; ++$i ) {
				$code_point = ( $code_point << 6 ) | ( ord( $character[ $i ] ) & 0x3F );
			}
			if ( $code_point > 0xFFFF ) {
				$code_point -= 0x10000;
				$utf16     .= pack( 'v2', 0xD800 | ( $code_point >> 10 ), 0xDC00 | ( $code_point & 0x3FF ) );
			} else {
				$utf16 .= pack( 'v', $code_point );
			}
		}
		return $utf16;
	}

	/**
	 * Replace generated CSS classes on the first element, preserving other classes.
	 *
	 * @param string $html Root markup or first innerContent fragment.
	 * @param string $class_name New class, or empty to clear.
	 * @param bool   $replace_existing Replace saved classes; false preserves rendered child classes.
	 * @return string Updated markup.
	 */
	public static function apply_class( string $html, string $class_name, bool $replace_existing = true ): string {
		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag() ) {
			return $html;
		}
		$classes = preg_split( '/\s+/', (string) $processor->get_attribute( 'class' ) );
		foreach ( $classes as $class ) {
			if ( $replace_existing && preg_match( '/^dsgo-custom-css-[a-z0-9]+$/', $class ) ) {
				$processor->remove_class( $class );
			}
		}
		if ( '' !== $class_name ) {
			$processor->add_class( $class_name );
		}
		return $processor->get_updated_html();
	}
}
