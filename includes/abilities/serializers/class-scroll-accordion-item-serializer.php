<?php
/**
 * Serializer for designsetgo/scroll-accordion-item.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/scroll-accordion-item
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scroll_Accordion_Item_Serializer.
 */
class Scroll_Accordion_Item_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$overlay_color = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '';

		// Build classes.
		$class_parts          = array( 'wp-block-designsetgo-scroll-accordion-item', 'dsgo-scroll-accordion-item' );
		$accordion_item_align = Serializer_Support::align_class( $block_name, $attributes );
		if ( '' !== $accordion_item_align ) {
			$class_parts[] = $accordion_item_align;
		}
		if ( $overlay_color ) {
			$class_parts[] = 'dsgo-scroll-accordion-item--has-overlay';
		}

		// Build style.
		$style = '';
		if ( $overlay_color ) {
			$style = '--dsgo-overlay-color:' . Serializer_Support::convert_color_value_to_css_var( (string) $overlay_color ) . ';--dsgo-overlay-opacity:' . Serializer_Support::overlay_opacity( $attributes );
		}

		$style_attr = $style ? ' style="' . esc_attr( $style ) . '"' : '';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $style_attr . '>',
			'closing' => '</div>',
		);
	}
}
