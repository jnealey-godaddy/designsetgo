<?php
/**
 * Serializer for designsetgo/scroll-marquee.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/scroll-marquee
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scroll_Marquee_Serializer.
 */
class Scroll_Marquee_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$rows         = isset( $attributes['rows'] ) ? $attributes['rows'] : array();
		$scroll_speed = isset( $attributes['scrollSpeed'] ) ? floatval( $attributes['scrollSpeed'] ) : 0.5;
		$image_height = isset( $attributes['imageHeight'] ) ? $attributes['imageHeight'] : '200px';
		// block.json defaults imageWidth to 'auto', not '300px'.
		$image_width = isset( $attributes['imageWidth'] ) ? $attributes['imageWidth'] : 'auto';
		$gap         = isset( $attributes['gap'] ) ? $attributes['gap'] : '20px';
		$row_gap     = isset( $attributes['rowGap'] ) ? $attributes['rowGap'] : '20px';
		$object_fit  = isset( $attributes['objectFit'] ) ? $attributes['objectFit'] : 'cover';

		// Build style. save.js writes object-fit here; the border-radius
		// custom property this used to emit was removed from save.js, so
		// every marquee serialized a declaration save() never writes.
		$style_parts = array(
			'--dsgo-marquee-gap:' . esc_attr( $gap ),
			'--dsgo-marquee-row-gap:' . esc_attr( $row_gap ),
			'--dsgo-marquee-image-height:' . esc_attr( $image_height ),
			'--dsgo-marquee-image-width:' . esc_attr( $image_width ),
			'--dsgo-marquee-object-fit:' . esc_attr( $object_fit ),
		);
		$style       = implode( ';', $style_parts );

		// Build rows HTML.
		$rows_html = '';
		foreach ( $rows as $row ) {
			$direction = isset( $row['direction'] ) ? $row['direction'] : 'left';
			$images    = isset( $row['images'] ) ? $row['images'] : array();

			$rows_html .= '<div class="dsgo-scroll-marquee__row" data-direction="' . esc_attr( $direction ) . '">';
			$rows_html .= '<div class="dsgo-scroll-marquee__track">';

			// Render images 6 times for seamless infinite scroll.
			for ( $i = 0; $i < 6; $i++ ) {
				$rows_html .= '<div class="dsgo-scroll-marquee__track-segment">';
				foreach ( $images as $image ) {
					$img_url    = isset( $image['url'] ) ? $image['url'] : '';
					$img_alt    = isset( $image['alt'] ) ? $image['alt'] : '';
					$rows_html .= '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $img_alt ) . '" class="dsgo-scroll-marquee__image" loading="lazy"/>';
				}
				$rows_html .= '</div>';
			}

			$rows_html .= '</div></div>';
		}

		return array(
			'opening' => '<div class="wp-block-designsetgo-scroll-marquee dsgo-scroll-marquee" data-scroll-speed="' . esc_attr( (string) $scroll_speed ) . '" style="' . esc_attr( $style ) . '">' . $rows_html,
			'closing' => '</div>',
		);
	}
}
