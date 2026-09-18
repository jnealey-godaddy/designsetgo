<?php
/**
 * Serializer for designsetgo/timeline.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/timeline
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Timeline_Serializer.
 */
class Timeline_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/timeline/save.js.
		$line_color         = isset( $attributes['lineColor'] ) ? (string) $attributes['lineColor'] : '';
		$line_thickness     = Serializer_Support::numeric_attribute( $attributes['lineThickness'] ?? 2, 2 );
		$connector_style    = isset( $attributes['connectorStyle'] ) ? (string) $attributes['connectorStyle'] : 'solid';
		$marker_style       = isset( $attributes['markerStyle'] ) ? (string) $attributes['markerStyle'] : 'circle';
		$marker_size        = Serializer_Support::numeric_attribute( $attributes['markerSize'] ?? 16, 16 );
		$marker_color       = isset( $attributes['markerColor'] ) ? (string) $attributes['markerColor'] : '';
		$marker_border      = isset( $attributes['markerBorderColor'] ) ? (string) $attributes['markerBorderColor'] : '';
		$item_spacing       = isset( $attributes['itemSpacing'] ) ? (string) $attributes['itemSpacing'] : '2rem';
		$animate_on_scroll  = ! empty( $attributes['animateOnScroll'] );
		$animation_duration = Serializer_Support::numeric_attribute( $attributes['animationDuration'] ?? 600, 600 );
		$stagger_delay      = Serializer_Support::numeric_attribute( $attributes['staggerDelay'] ?? 100, 100 );
		$orientation        = isset( $attributes['orientation'] ) ? (string) $attributes['orientation'] : 'vertical';
		$timeline_layout    = isset( $attributes['layout'] ) ? $attributes['layout'] : 'alternating';

		// save.js writes every custom property unconditionally, falling
		// back to the same literals used here.
		$timeline_styles = array(
			'--dsgo-timeline-line-color:' . ( '' !== $line_color ? $line_color : 'var(--wp--preset--color--contrast, #e5e7eb)' ),
			'--dsgo-timeline-line-thickness:' . $line_thickness . 'px',
			'--dsgo-timeline-connector-style:' . $connector_style,
			'--dsgo-timeline-marker-size:' . $marker_size . 'px',
			'--dsgo-timeline-marker-color:' . ( '' !== $marker_color ? $marker_color : 'var(--wp--preset--color--primary, #2563eb)' ),
			'--dsgo-timeline-marker-border-color:' . ( '' !== $marker_border ? $marker_border : ( '' !== $marker_color ? $marker_color : 'var(--wp--preset--color--primary, #2563eb)' ) ),
			'--dsgo-timeline-item-spacing:' . $item_spacing,
			'--dsgo-timeline-animation-duration:' . $animation_duration . 'ms',
		);

		$class_parts = array( 'wp-block-designsetgo-timeline' );
		if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
			$class_parts[] = 'align' . $attributes['align'];
		}
		$class_parts[] = 'dsgo-timeline';
		if ( '' !== $orientation ) {
			$class_parts[] = 'dsgo-timeline--' . $orientation;
		}
		if ( is_string( $timeline_layout ) && '' !== $timeline_layout ) {
			$class_parts[] = 'dsgo-timeline--layout-' . $timeline_layout;
		}
		if ( '' !== $marker_style ) {
			$class_parts[] = 'dsgo-timeline--marker-' . $marker_style;
		}
		if ( $animate_on_scroll ) {
			$class_parts[] = 'dsgo-timeline--animate';
		}

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( implode( ';', $timeline_styles ) ) . '"' .
				' data-animate="' . ( $animate_on_scroll ? 'true' : 'false' ) . '"' .
				' data-animation-duration="' . esc_attr( (string) $animation_duration ) . '"' .
				' data-stagger-delay="' . esc_attr( (string) $stagger_delay ) . '">' .
				'<div class="dsgo-timeline__line" aria-hidden="true"></div>' .
				'<div class="dsgo-timeline__items">',
			'closing' => '</div></div>',
		);
	}
}
