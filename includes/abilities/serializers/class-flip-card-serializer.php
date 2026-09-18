<?php
/**
 * Serializer for designsetgo/flip-card.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/flip-card
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flip_Card_Serializer.
 */
class Flip_Card_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$flip_trigger   = isset( $attributes['flipTrigger'] ) ? $attributes['flipTrigger'] : 'hover';
		$flip_effect    = isset( $attributes['flipEffect'] ) ? $attributes['flipEffect'] : 'flip';
		$flip_direction = isset( $attributes['flipDirection'] ) ? $attributes['flipDirection'] : 'horizontal';
		$flip_duration  = isset( $attributes['flipDuration'] ) ? $attributes['flipDuration'] : '0.6s';

		$outer_class = 'wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--' . esc_attr( $flip_trigger ) . ' dsgo-flip-card--effect-' . esc_attr( $flip_effect ) . ' dsgo-flip-card--' . esc_attr( $flip_direction );
		// `width:100%` is no longer serialized — style.scss owns it (see
		// save.js). Emitting it here would produce markup save() never
		// generates, so the block would fail validation on first open.
		$outer_style = '--dsgo-flip-duration:' . esc_attr( $flip_duration );
		$data_attrs  = ' data-flip-trigger="' . esc_attr( $flip_trigger ) . '" data-flip-effect="' . esc_attr( $flip_effect ) . '" data-flip-direction="' . esc_attr( $flip_direction ) . '"';

		return array(
			'opening' => '<div class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $outer_style ) . '"' . $data_attrs . '><div class="dsgo-flip-card__container">',
			'closing' => '</div></div>',
		);
	}
}
