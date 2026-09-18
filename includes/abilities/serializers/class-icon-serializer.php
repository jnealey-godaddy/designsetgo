<?php
/**
 * Serializer for designsetgo/icon.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/icon
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icon_Serializer.
 */
class Icon_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Dead branch: Icon is a dynamic block (render.php), so
		// is_dynamic_block() keeps this switch from ever being
		// reached for it (see convert_to_block_array() above) — the
		// block always serializes to a bare comment and is rendered
		// server-side. Kept in sync with the current markup anyway
		// (rather than deleted) in case that gate is ever revisited;
		// same treatment as the Pill case below.
		$icon_name    = isset( $attributes['icon'] ) ? $attributes['icon'] : ( isset( $attributes['iconName'] ) ? $attributes['iconName'] : 'star' );
		$icon_style   = isset( $attributes['iconStyle'] ) ? $attributes['iconStyle'] : 'filled';
		$stroke_width = isset( $attributes['strokeWidth'] ) ? $attributes['strokeWidth'] : '1.5';
		$icon_size    = isset( $attributes['iconSize'] ) ? Serializer_Support::numeric_attribute( $attributes['iconSize'] ) : ( isset( $attributes['size'] ) ? intval( $attributes['size'] ) : 48 );
		$aria_label   = isset( $attributes['ariaLabel'] ) ? $attributes['ariaLabel'] : ucwords( str_replace( '-', ' ', $icon_name ) );

		// `align` was removed when `justification` replaced it.
		$justification = isset( $attributes['justification'] )
			? $attributes['justification']
			: ( isset( $attributes['align'] ) ? $attributes['align'] : 'center' );
		if ( ! in_array( $justification, array( 'left', 'center', 'right' ), true ) ) {
			$justification = 'center';
		}

		$wrapper_style = 'width:' . $icon_size . 'px;height:' . $icon_size . 'px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit';

		$inner_html  = '<div class="dsgo-icon__wrapper dsgo-lazy-icon" style="' . esc_attr( $wrapper_style ) . '"';
		$inner_html .= ' data-icon-name="' . esc_attr( $icon_name ) . '"';
		$inner_html .= ' data-icon-style="' . esc_attr( $icon_style ) . '"';
		$inner_html .= ' data-icon-stroke-width="' . esc_attr( $stroke_width ) . '"';
		$inner_html .= ' role="img" aria-label="' . esc_attr( $aria_label ) . '"></div>';

		$wrapper_class = 'wp-block-designsetgo-icon dsgo-icon dsgo-justify dsgo-justify--' . $justification;

		return array(
			'opening' => '<div class="' . esc_attr( $wrapper_class ) . '">' . $inner_html,
			'closing' => '</div>',
		);
	}
}
