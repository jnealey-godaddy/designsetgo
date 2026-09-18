<?php
/**
 * Serializer for designsetgo/hotspot-item.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/hotspot-item
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hotspot_Item_Serializer.
 */
class Hotspot_Item_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/hotspot-item/save.js.
		$item_unique_id = isset( $attributes['uniqueId'] ) && '' !== $attributes['uniqueId']
			? (string) $attributes['uniqueId']
			: 'item';
		$marker_id      = 'dsgo-hotspot-marker-' . $item_unique_id;
		$tooltip_id     = 'dsgo-hotspot-tooltip-' . $item_unique_id;

		$item_x        = Serializer_Support::clamp_hotspot_coordinate( $attributes['x'] ?? 50 );
		$item_y        = Serializer_Support::clamp_hotspot_coordinate( $attributes['y'] ?? 50 );
		$origin_x      = isset( $attributes['originX'] ) ? (string) $attributes['originX'] : 'center';
		$origin_y      = isset( $attributes['originY'] ) ? (string) $attributes['originY'] : 'center';
		$item_label    = isset( $attributes['label'] ) ? (string) $attributes['label'] : '+';
		$item_icon     = isset( $attributes['icon'] ) ? (string) $attributes['icon'] : '';
		$item_tooltip  = isset( $attributes['tooltip'] ) ? (string) $attributes['tooltip'] : 'Add a description';
		$item_position = isset( $attributes['tooltipPosition'] ) ? (string) $attributes['tooltipPosition'] : 'inherit';
		$item_trigger  = isset( $attributes['trigger'] ) ? (string) $attributes['trigger'] : 'inherit';
		$item_anim     = isset( $attributes['animation'] ) ? (string) $attributes['animation'] : 'inherit';
		$item_order    = Serializer_Support::numeric_attribute( $attributes['sequenceOrder'] ?? 0, 0 );
		$safe_url      = Serializer_Support::safe_hotspot_url( $attributes['url'] ?? '' );

		$item_styles = array(
			'--dsgo-hotspot-x:' . $item_x . '%',
			'--dsgo-hotspot-y:' . $item_y . '%',
		);
		// save.js writes the width only for a real number, so an unset
		// width must not appear at all.
		if ( isset( $attributes['tooltipWidth'] ) && is_numeric( $attributes['tooltipWidth'] ) ) {
			$item_styles[] = '--dsgo-hotspot-tooltip-width:' . Serializer_Support::numeric_attribute( $attributes['tooltipWidth'] ) . 'px';
		}
		$item_styles[] = '--dsgo-hotspot-sequence-order:' . $item_order;
		$item_styles[] = '--dsgo-hotspot-origin-x:' . $origin_x;
		$item_styles[] = '--dsgo-hotspot-origin-y:' . $origin_y;

		$class_parts = array(
			'wp-block-designsetgo-hotspot-item',
			'dsgo-hotspot-item',
			'dsgo-hotspot-item--position-' . $item_position,
			'dsgo-hotspot-item--animation-' . $item_anim,
			'dsgo-hotspot-item--origin-x-' . $origin_x,
			'dsgo-hotspot-item--origin-y-' . $origin_y,
		);

		$is_linked = '' !== $safe_url;

		// Attribute order here matches save.js so the emitted markup is
		// byte-comparable; the validator is order-insensitive, but the
		// conditions are not interchangeable.
		$marker_attrs = ' class="dsgo-hotspot-item__marker" id="' . esc_attr( $marker_id ) . '"';
		if ( ! $is_linked && 'click' === $item_trigger ) {
			$marker_attrs .= ' aria-expanded="false" aria-controls="' . esc_attr( $tooltip_id ) . '"';
		}
		if ( $is_linked || 'hover' === $item_trigger ) {
			$marker_attrs .= ' aria-describedby="' . esc_attr( $tooltip_id ) . '"';
		}
		// save.js labels the marker only when its visible content is not
		// meaningful text.
		if ( '' !== $item_icon || '' === $item_label || '+' === $item_label ) {
			$marker_attrs .= ' aria-label="' . esc_attr__( 'Hotspot', 'designsetgo' ) . '"';
		}
		$marker_attrs .= ' data-dsgo-hotspot-marker="true"';

		$marker_content = '' !== $item_icon ? $item_icon : ( '' !== $item_label ? $item_label : '+' );

		$marker_html = $is_linked
			? '<a' . $marker_attrs . ' href="' . esc_url( $safe_url ) . '">' . esc_html( $marker_content ) . '</a>'
			: '<button' . $marker_attrs . ' type="button">' . esc_html( $marker_content ) . '</button>';

		$item_data_trigger = ( 'inherit' === $item_trigger )
			? ''
			: ' data-dsgo-hotspot-trigger="' . esc_attr( $item_trigger ) . '"';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( implode( ';', $item_styles ) ) . '"' .
				' data-dsgo-hotspot-item="true"' . $item_data_trigger . '>' .
				$marker_html .
				'<div class="dsgo-hotspot-item__tooltip" id="' . esc_attr( $tooltip_id ) . '" role="tooltip"' .
				' data-dsgo-hotspot-tooltip="true" hidden aria-hidden="true">' .
				'<span>' . wp_kses_post( $item_tooltip ) . '</span></div>',
			'closing' => '</div>',
		);
	}
}
