<?php
/**
 * Serializer for designsetgo/sticky-sections.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/sticky-sections
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * StickySections_Serializer.
 */
class StickySections_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/sticky-sections/save.js.
		$sticky_offset = ( isset( $attributes['stickyOffset'] ) && '' !== $attributes['stickyOffset'] )
			? (string) $attributes['stickyOffset']
			: '0px';

		$class_parts = array( 'wp-block-designsetgo-sticky-sections' );
		if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
			$class_parts[] = 'align' . $attributes['align'];
		}
		$class_parts[] = 'dsgo-sticky-sections';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' .
				esc_attr( '--dsgo-sticky-offset:' . $sticky_offset ) . '">',
			'closing' => '</div>',
		);
	}
}
