<?php
/**
 * Serializer for designsetgo/scroll-slide.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/scroll-slide
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ScrollSlide_Serializer.
 */
class ScrollSlide_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/scroll-slide/save.js. A hybrid block: it has
		// a render.php AND a save.js, so the stored markup must carry the
		// wrapper the frontend looks for.
		$nav_heading = isset( $attributes['navHeading'] ) ? (string) $attributes['navHeading'] : '';

		return array(
			'opening' => '<div class="wp-block-designsetgo-scroll-slide dsgo-scroll-slide"' .
				' data-dsgo-nav-heading="' . esc_attr( $nav_heading ) . '">',
			'closing' => '</div>',
		);
	}
}
