<?php
/**
 * Serializer for designsetgo/scroll-slides.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/scroll-slides
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scroll_Slides_Serializer.
 */
class Scroll_Slides_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/scroll-slides/save.js.
		$min_height       = ( isset( $attributes['minHeight'] ) && '' !== $attributes['minHeight'] ) ? (string) $attributes['minHeight'] : '100vh';
		$max_height       = isset( $attributes['maxHeight'] ) ? (string) $attributes['maxHeight'] : '';
		$constrain_width  = ! isset( $attributes['constrainWidth'] ) || $attributes['constrainWidth'];
		$content_width    = isset( $attributes['contentWidth'] ) ? (string) $attributes['contentWidth'] : '';
		$overlay_color    = isset( $attributes['overlayColor'] ) ? (string) $attributes['overlayColor'] : '';
		$nav_color        = isset( $attributes['navColor'] ) ? (string) $attributes['navColor'] : '';
		$nav_active_color = isset( $attributes['navActiveColor'] ) ? (string) $attributes['navActiveColor'] : '';

		$class_parts = array( 'wp-block-designsetgo-scroll-slides' );
		if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
			$class_parts[] = 'align' . $attributes['align'];
		}
		$class_parts[] = 'dsgo-scroll-slides';
		if ( '' !== $overlay_color ) {
			$class_parts[] = 'dsgo-scroll-slides--has-overlay';
		}
		if ( ! $constrain_width ) {
			$class_parts[] = 'dsgo-scroll-slides--no-width-constraint';
		}
		if ( '' !== $nav_color || '' !== $nav_active_color ) {
			$class_parts[] = 'dsgo-scroll-slides--has-nav-color';
		}

		$slides_styles = array();
		if ( '' !== $overlay_color ) {
			$slides_styles[] = '--dsgo-overlay-color:' . Serializer_Support::convert_color_value_to_css_var( $overlay_color );
			// Mirrors overlayOpacityFraction(): clamp to 0-100, default
			// 80, then divide. PHP would print 0.8 as "0.8" like JS does.
			$opacity         = isset( $attributes['overlayOpacity'] ) && is_numeric( $attributes['overlayOpacity'] )
				? min( 100, max( 0, (float) $attributes['overlayOpacity'] ) )
				: 80;
			$slides_styles[] = '--dsgo-overlay-opacity:' . Serializer_Support::format_js_number( $opacity / 100 );
		}
		if ( '' !== $nav_color ) {
			$slides_styles[] = '--dsgo-nav-color:' . Serializer_Support::convert_color_value_to_css_var( $nav_color );
		}
		if ( '' !== $nav_active_color ) {
			$slides_styles[] = '--dsgo-nav-active-color:' . Serializer_Support::convert_color_value_to_css_var( $nav_active_color );
		}

		$inner_styles = array();
		if ( $constrain_width ) {
			$inner_styles[] = 'max-width:' . ( '' !== $content_width ? $content_width : 'var(--wp--style--global--content-size, 1140px)' );
			$inner_styles[] = 'margin-left:auto';
			$inner_styles[] = 'margin-right:auto';
		}

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' .
				' data-dsgo-min-height="' . esc_attr( $min_height ) . '"' .
				( '' !== $max_height ? ' data-dsgo-max-height="' . esc_attr( $max_height ) . '"' : '' ) .
				( empty( $slides_styles ) ? '' : ' style="' . esc_attr( implode( ';', $slides_styles ) ) . '"' ) . '>' .
				'<div class="dsgo-scroll-slides__inner"' .
				( empty( $inner_styles ) ? '' : ' style="' . esc_attr( implode( ';', $inner_styles ) ) . '"' ) . '>' .
				'<div class="dsgo-scroll-slides__panels">',
			'closing' => '</div></div></div>',
		);
	}
}
