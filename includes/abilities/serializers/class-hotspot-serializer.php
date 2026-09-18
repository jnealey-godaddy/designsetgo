<?php
/**
 * Serializer for designsetgo/hotspot.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/hotspot
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hotspot_Serializer.
 */
class Hotspot_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/hotspot/save.js.
		$hotspot_image_url = isset( $attributes['imageUrl'] ) ? (string) $attributes['imageUrl'] : '';
		$hotspot_image_alt = isset( $attributes['imageAlt'] ) ? (string) $attributes['imageAlt'] : '';
		$hotspot_trigger   = isset( $attributes['trigger'] ) ? (string) $attributes['trigger'] : 'click';
		$tooltip_position  = isset( $attributes['tooltipPosition'] ) ? (string) $attributes['tooltipPosition'] : 'top';
		$tooltip_width     = Serializer_Support::numeric_attribute( $attributes['tooltipWidth'] ?? 240, 240 );
		$hotspot_animation = isset( $attributes['animation'] ) ? (string) $attributes['animation'] : 'pulse';
		$sequence_duration = Serializer_Support::numeric_attribute( $attributes['sequenceDuration'] ?? 0, 0 );

		$hotspot_styles = array(
			'--dsgo-hotspot-tooltip-width:' . $tooltip_width . 'px',
			'--dsgo-hotspot-sequence-duration:' . $sequence_duration . 'ms',
		);

		// Each colour is emitted only when it passes the same allowlist
		// getSafeHotspotColor() applies, so an unrecognised value drops
		// out of both paths identically.
		$hotspot_color_vars = array(
			'markerColor'            => '--dsgo-hotspot-marker-color',
			'markerBackgroundColor'  => '--dsgo-hotspot-marker-background',
			'tooltipBackgroundColor' => '--dsgo-hotspot-tooltip-background',
			'tooltipTextColor'       => '--dsgo-hotspot-tooltip-color',
		);
		foreach ( $hotspot_color_vars as $attribute_name => $custom_property ) {
			$safe = Serializer_Support::safe_hotspot_color( $attributes[ $attribute_name ] ?? '' );
			if ( '' !== $safe ) {
				$hotspot_styles[] = $custom_property . ':' . Serializer_Support::convert_color_value_to_css_var( $safe );
			}
		}

		$class_parts = array( 'wp-block-designsetgo-hotspot' );
		if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
			$class_parts[] = 'align' . $attributes['align'];
		}
		$class_parts[] = 'dsgo-hotspot';
		$class_parts[] = 'dsgo-hotspot--position-' . $tooltip_position;
		$class_parts[] = 'dsgo-hotspot--animation-' . $hotspot_animation;

		$hotspot_image_html = '' !== $hotspot_image_url
			? '<img class="dsgo-hotspot__image" src="' . esc_url( $hotspot_image_url ) . '" alt="' . esc_attr( $hotspot_image_alt ) . '"/>'
			: '<div class="dsgo-hotspot__image dsgo-hotspot__image--empty"></div>';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( implode( ';', $hotspot_styles ) ) . '"' .
				' data-dsgo-hotspot="true"' .
				' data-dsgo-hotspot-trigger="' . esc_attr( $hotspot_trigger ) . '"' .
				' data-dsgo-hotspot-position="' . esc_attr( $tooltip_position ) . '"' .
				' data-dsgo-hotspot-animation="' . esc_attr( $hotspot_animation ) . '">' .
				'<div class="dsgo-hotspot__image-wrap">' . $hotspot_image_html .
				'<div class="dsgo-hotspot__items">',
			'closing' => '</div></div></div>',
		);
	}
}
