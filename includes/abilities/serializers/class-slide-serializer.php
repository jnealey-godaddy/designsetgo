<?php
/**
 * Serializer for designsetgo/slide.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/slide
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slide_Serializer.
 */
class Slide_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$background_image    = isset( $attributes['backgroundImage'] ) ? $attributes['backgroundImage'] : array();
		$background_size     = isset( $attributes['backgroundSize'] ) ? $attributes['backgroundSize'] : 'cover';
		$background_position = isset( $attributes['backgroundPosition'] ) ? $attributes['backgroundPosition'] : 'center center';
		$background_repeat   = isset( $attributes['backgroundRepeat'] ) ? $attributes['backgroundRepeat'] : 'no-repeat';
		$overlay_color       = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '';
		$overlay_opacity     = isset( $attributes['overlayOpacity'] ) ? floatval( $attributes['overlayOpacity'] ) : 80;
		$content_v_align     = isset( $attributes['contentVerticalAlign'] ) ? $attributes['contentVerticalAlign'] : 'center';
		$content_h_align     = isset( $attributes['contentHorizontalAlign'] ) ? $attributes['contentHorizontalAlign'] : 'center';
		$min_height          = isset( $attributes['minHeight'] ) ? $attributes['minHeight'] : '';
		$bg_url              = isset( $background_image['url'] ) ? $background_image['url'] : '';

		// Build classes.
		$class_parts = array( 'wp-block-designsetgo-slide', 'dsgo-slide' );
		if ( $bg_url ) {
			$class_parts[] = 'dsgo-slide--has-background';
		}
		if ( $overlay_color ) {
			$class_parts[] = 'dsgo-slide--has-overlay';
		}

		// Build style.
		$style_parts = array();
		if ( $bg_url ) {
			$style_parts[] = 'background-image:url(' . esc_url( $bg_url ) . ')';
			$style_parts[] = 'background-size:' . esc_attr( $background_size );
			$style_parts[] = 'background-position:' . esc_attr( $background_position );
			$style_parts[] = 'background-repeat:' . esc_attr( $background_repeat );
		}
		if ( $overlay_color ) {
			$style_parts[] = '--dsgo-slide-overlay-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $overlay_color ) );
			$style_parts[] = '--dsgo-slide-overlay-opacity:' . esc_attr( (string) ( $overlay_opacity / 100 ) );
		}
		$style_parts[] = '--dsgo-slide-content-vertical-align:' . esc_attr( $content_v_align );
		$style_parts[] = '--dsgo-slide-content-horizontal-align:' . esc_attr( $content_h_align );
		if ( $min_height ) {
			$style_parts[] = 'min-height:' . esc_attr( $min_height );
		}
		$style = implode( ';', $style_parts );

		// Overlay HTML.
		$overlay_html = '';
		if ( $overlay_color ) {
			$overlay_style = 'background-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $overlay_color ) ) . ';opacity:' . esc_attr( (string) ( $overlay_opacity / 100 ) );
			$overlay_html  = '<div class="dsgo-slide__overlay" style="' . esc_attr( $overlay_style ) . '"></div>';
		}

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '" role="group" aria-roledescription="slide">' . $overlay_html . '<div class="dsgo-slide__content">',
			'closing' => '</div></div>',
		);
	}
}
