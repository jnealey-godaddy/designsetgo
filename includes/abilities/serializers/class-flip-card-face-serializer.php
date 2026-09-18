<?php
/**
 * Serializer for designsetgo/flip-card-face.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/flip-card-face
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FlipCardFace_Serializer.
 */
class FlipCardFace_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$side = isset( $attributes['side'] ) && 'back' === $attributes['side'] ? 'back' : 'front';
		return array(
			'opening' => '<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__' . esc_attr( $side ) . '">',
			'closing' => '</div>',
		);

			// Legacy — consolidated into designsetgo/flip-card-face in 2.0.51.
			// Kept so the inserter ability can still emit existing content
			// until it is transformed to the new block.
	}
}
