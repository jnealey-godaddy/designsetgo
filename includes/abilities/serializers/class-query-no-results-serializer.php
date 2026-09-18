<?php
/**
 * Serializer for designsetgo/query-no-results.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/query-no-results
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query_No_Results_Serializer.
 */
class Query_No_Results_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/query-no-results/save.js.
		return array(
			'opening' => '<div class="wp-block-designsetgo-query-no-results dsgo-query-no-results">',
			'closing' => '</div>',
		);
	}
}
