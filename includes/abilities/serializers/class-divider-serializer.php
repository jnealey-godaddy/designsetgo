<?php
/**
 * Serializer for designsetgo/divider.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/divider
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Divider_Serializer.
 */
class Divider_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$divider_style = isset( $attributes['dividerStyle'] ) ? $attributes['dividerStyle'] : 'solid';
		$width         = isset( $attributes['width'] ) ? $attributes['width'] : 100;
		$thickness     = isset( $attributes['thickness'] ) ? $attributes['thickness'] : 2;
		$icon_name     = isset( $attributes['iconName'] ) ? $attributes['iconName'] : '';

		$divider_class = 'wp-block-designsetgo-divider dsgo-divider dsgo-divider--' . esc_attr( $divider_style );

		$container_style = 'width:' . intval( $width ) . '%';
		$line_style      = 'height:' . intval( $thickness ) . 'px';

		if ( 'icon' === $divider_style ) {
			// Icon style with three elements.
			$inner_html  = '<div class="dsgo-divider__container" style="' . esc_attr( $container_style ) . '">';
			$inner_html .= '<div class="dsgo-divider__icon-wrapper">';
			$inner_html .= '<span class="dsgo-divider__line dsgo-divider__line--left" style="' . esc_attr( $line_style ) . '"></span>';
			$inner_html .= '<span class="dsgo-divider__icon dsgo-lazy-icon" data-icon-name="' . esc_attr( $icon_name ) . '"></span>';
			$inner_html .= '<span class="dsgo-divider__line dsgo-divider__line--right" style="' . esc_attr( $line_style ) . '"></span>';
			$inner_html .= '</div></div>';
		} else {
			// Standard divider.
			$inner_html  = '<div class="dsgo-divider__container" style="' . esc_attr( $container_style ) . '">';
			$inner_html .= '<div class="dsgo-divider__line" style="' . esc_attr( $line_style ) . '"></div>';
			$inner_html .= '</div>';
		}

		return array(
			'opening' => '<div class="' . esc_attr( $divider_class ) . '">' . $inner_html,
			'closing' => '</div>',
		);
	}
}
