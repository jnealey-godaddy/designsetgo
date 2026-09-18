<?php
/**
 * Serializer for designsetgo/accordion.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/accordion
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accordion_Serializer.
 */
class Accordion_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$allow_multiple = isset( $attributes['allowMultipleOpen'] ) ? $attributes['allowMultipleOpen'] : false;
		$icon_style     = isset( $attributes['iconStyle'] ) ? $attributes['iconStyle'] : 'chevron';
		$icon_position  = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'right';
		$border_between = isset( $attributes['borderBetween'] ) ? $attributes['borderBetween'] : true;
		$item_gap       = isset( $attributes['itemGap'] ) ? $attributes['itemGap'] : '0.5rem';
		$open_bg        = isset( $attributes['openBackgroundColor'] ) ? $attributes['openBackgroundColor'] : '';
		$open_text      = isset( $attributes['openTextColor'] ) ? $attributes['openTextColor'] : '';
		// save.js uses `hoverBackgroundColor || openBackgroundColor`, so an
		// EMPTY hover colour falls back to the open colour. isset() is
		// the wrong test: apply_block_json_defaults() has already filled
		// the attribute with its '' default by this point, so isset() is
		// always true and the fallback never fired. Setting only
		// openBackgroundColor left the mirror writing an empty hover
		// declaration, which the style cleaner then dropped, while
		// save() wrote the inherited colour.
		$hover_bg       = ! empty( $attributes['hoverBackgroundColor'] ) ? $attributes['hoverBackgroundColor'] : $open_bg;
		$hover_text     = ! empty( $attributes['hoverTextColor'] ) ? $attributes['hoverTextColor'] : $open_text;
		$border_color   = isset( $attributes['borderBetweenColor'] ) ? $attributes['borderBetweenColor'] : '';

		// Build modifier classes (must match save.js).
		$accordion_classes = array( 'dsgo-accordion' );
		if ( $allow_multiple ) {
			$accordion_classes[] = 'dsgo-accordion--multiple';
		}
		if ( 'left' === $icon_position ) {
			$accordion_classes[] = 'dsgo-accordion--icon-left';
		} elseif ( 'right' === $icon_position ) {
			$accordion_classes[] = 'dsgo-accordion--icon-right';
		}
		if ( 'none' === $icon_style ) {
			$accordion_classes[] = 'dsgo-accordion--no-icon';
		}
		if ( $border_between ) {
			$accordion_classes[] = 'dsgo-accordion--border-between';
		}

		// Build CSS custom properties style (must match save.js).
		$style_parts = array(
			'--dsgo-accordion-open-bg:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $open_bg ) ),
			'--dsgo-accordion-open-text:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $open_text ) ),
			'--dsgo-accordion-hover-bg:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $hover_bg ) ),
			'--dsgo-accordion-hover-text:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $hover_text ) ),
			'--dsgo-accordion-gap:' . esc_attr( $item_gap ),
		);
		if ( $border_color ) {
			$style_parts[] = '--dsgo-accordion-border-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $border_color ) );
		}
		$custom_style = implode( ';', $style_parts );

		$full_class = 'wp-block-designsetgo-accordion ' . implode( ' ', $accordion_classes );

		return array(
			'opening' => '<div class="' . esc_attr( $full_class ) . '" style="' . esc_attr( $custom_style ) . '" data-allow-multiple="' . ( $allow_multiple ? 'true' : 'false' ) . '" data-icon-style="' . esc_attr( $icon_style ) . '"><div class="dsgo-accordion__items">',
			'closing' => '</div></div>',
		);
	}
}
