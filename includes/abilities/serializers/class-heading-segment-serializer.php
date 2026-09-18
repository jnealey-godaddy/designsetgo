<?php
/**
 * Serializer for designsetgo/heading-segment.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/heading-segment
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Heading_Segment_Serializer.
 */
class Heading_Segment_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/heading-segment/save.js. Only the "normal"
		// role is generated: the animated role serializes a JSON word
		// list and an inline highlight SVG, which get_serialization_gap()
		// refuses rather than approximate.
		$segment_text = isset( $attributes['content'] ) && is_string( $attributes['content'] ) && '' !== trim( $attributes['content'] )
			? $attributes['content']
			: ( isset( $attributes['normalContent'] ) && is_string( $attributes['normalContent'] ) ? $attributes['normalContent'] : '' );

		// save() returns null for a non-animated segment with no text,
		// which WordPress serializes as a self-closing comment with no
		// markup at all. Emitting empty spans instead made every
		// text-less segment invalid.
		if ( '' === trim( $segment_text ) ) {
			return array(
				'opening' => '',
				'closing' => '',
			);
		}

		return array(
			'opening' => '<span class="wp-block-designsetgo-heading-segment dsgo-heading-segment">' .
				'<span class="dsgo-heading-segment__text">' . wp_kses_post( $segment_text ),
			'closing' => '</span></span>',
		);
	}
}
