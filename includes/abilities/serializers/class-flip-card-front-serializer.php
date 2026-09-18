<?php
/**
 * Serializer for designsetgo/flip-card-front.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/flip-card-front
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flip_Card_Front_Serializer.
 */
class Flip_Card_Front_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		return array(
			'opening' => '<div class="wp-block-designsetgo-flip-card-front dsgo-flip-card__face dsgo-flip-card__front">',
			'closing' => '</div>',
		);
	}
}
