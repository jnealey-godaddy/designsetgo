<?php
/**
 * Serializer for designsetgo/query, designsetgo/query-results.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/query
 *   - designsetgo/query-results
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query_Serializer.
 */
class Query_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/query/save.js and query-results/save.js.
		// Both are dynamic — render.php owns the frontend HTML — but
		// their save() still emits a wrapper div so WordPress persists
		// the per-item template blocks inside it. Stored markup without
		// that wrapper is invalid in the editor.
		$query_slug    = str_replace( 'designsetgo/', '', $block_name );
		$query_classes = 'wp-block-designsetgo-' . $query_slug;
		$query_align   = Serializer_Support::align_class( $block_name, $attributes );
		if ( '' !== $query_align ) {
			$query_classes .= ' ' . $query_align;
		}

		return array(
			'opening' => '<div class="' . esc_attr( $query_classes ) . '">',
			'closing' => '</div>',
		);
	}
}
