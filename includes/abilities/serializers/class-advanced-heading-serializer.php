<?php
/**
 * Serializer for designsetgo/advanced-heading.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/advanced-heading
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AdvancedHeading_Serializer.
 */
class AdvancedHeading_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/advanced-heading/save.js. The animated
		// headline variant is refused by find_invalid_attribute_values()
		// rather than approximated here.
		$heading_level = Serializer_Support::numeric_attribute( $attributes['level'] ?? 2, 2 );
		if ( ! in_array( (int) $heading_level, array( 1, 2, 3, 4, 5, 6 ), true ) ) {
			$heading_level = 2;
		}
		$heading_tag = 'h' . (int) $heading_level;
		$text_align  = isset( $attributes['textAlign'] ) ? (string) $attributes['textAlign'] : '';

		$class_parts   = array( 'wp-block-designsetgo-advanced-heading' );
		$heading_align = Serializer_Support::align_class( $block_name, $attributes );
		if ( '' !== $heading_align ) {
			$class_parts[] = $heading_align;
		}
		$class_parts[] = 'dsgo-advanced-heading';
		if ( '' !== $text_align ) {
			$class_parts[] = 'has-text-align-' . $text_align;
		}

		$block_gap   = $attributes['style']['spacing']['blockGap'] ?? '';
		$inner_style = ( is_string( $block_gap ) && '' !== $block_gap )
			? ' style="' . esc_attr( '--dsgo-segment-gap:' . Serializer_Support::wp_shorthand_to_css_var( $block_gap ) ) . '"'
			: '';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '">' .
				'<' . $heading_tag . ' class="dsgo-advanced-heading__inner"' . $inner_style . '>',
			'closing' => '</' . $heading_tag . '></div>',
		);
	}
}
