<?php
/**
 * Serializer for designsetgo/timeline-item.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/timeline-item
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TimelineItem_Serializer.
 */
class TimelineItem_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/timeline-item/save.js.
		//
		// That save() reads marker styling from parent context, but
		// WordPress passes NO context to getSaveElement(), so the stored
		// markup always uses the fallbacks: a circle marker at 16px in
		// the primary preset colour. Reading the parent's attributes here
		// would produce markup save() never writes.
		$item_date       = isset( $attributes['date'] ) ? (string) $attributes['date'] : '';
		$item_title      = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
		$item_image_url  = isset( $attributes['imageUrl'] ) ? (string) $attributes['imageUrl'] : '';
		$item_is_active  = ! empty( $attributes['isActive'] );
		$item_link_url   = isset( $attributes['linkUrl'] ) ? (string) $attributes['linkUrl'] : '';
		$item_link_targ  = isset( $attributes['linkTarget'] ) ? (string) $attributes['linkTarget'] : '_self';
		$item_marker_col = isset( $attributes['customMarkerColor'] ) ? (string) $attributes['customMarkerColor'] : '';

		$item_safe_url = Serializer_Support::safe_hotspot_url( $item_link_url );

		$effective_marker = '' !== $item_marker_col
			? $item_marker_col
			: 'var(--wp--preset--color--primary, #2563eb)';

		$class_parts = array( 'wp-block-designsetgo-timeline-item', 'dsgo-timeline-item' );
		if ( $item_is_active ) {
			$class_parts[] = 'dsgo-timeline-item--active';
		}
		if ( '' !== $item_image_url ) {
			$class_parts[] = 'dsgo-timeline-item--has-image';
		}
		if ( '' !== $item_safe_url ) {
			$class_parts[] = 'dsgo-timeline-item--has-link';
		}

		$item_style = '' !== $item_marker_col
			? ' style="' . esc_attr( '--dsgo-timeline-item-marker-color:' . Serializer_Support::convert_color_value_to_css_var( $item_marker_col ) ) . '"'
			: '';

		// Marker: an image when set, otherwise the default circle SVG.
		if ( '' !== $item_image_url ) {
			$marker_inner = '<img src="' . esc_url( $item_image_url ) . '" alt="" class="dsgo-timeline-item__marker-image"' .
				' style="' . esc_attr( 'width:16px;height:16px;border-radius:50%;object-fit:cover' ) . '"/>';
		} else {
			$marker_inner = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' .
				'<circle cx="12" cy="12" r="10" fill="' . esc_attr( $effective_marker ) . '"' .
				' stroke="' . esc_attr( $effective_marker ) . '" stroke-width="2"></circle></svg>';
		}

		$marker_html = '<div class="dsgo-timeline-item__marker" aria-hidden="true">' . $marker_inner . '</div>';

		$date_html  = '' !== $item_date
			? '<span class="dsgo-timeline-item__date">' . wp_kses_post( $item_date ) . '</span>'
			: '';
		$title_html = '' !== $item_title
			? '<h3 class="dsgo-timeline-item__title">' . wp_kses_post( $item_title ) . '</h3>'
			: '';

		if ( '' !== $item_safe_url ) {
			$link_rel   = '_blank' === $item_link_targ ? ' rel="noopener noreferrer"' : '';
			$open_link  = '<a href="' . esc_url( $item_safe_url ) . '" target="' . esc_attr( $item_link_targ ) . '"' . $link_rel .
				' class="dsgo-timeline-item__link">';
			$close_link = '</a>';
		} else {
			$open_link  = '';
			$close_link = '';
		}

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $item_style . '>' .
				$marker_html . $open_link .
				'<div class="dsgo-timeline-item__wrapper">' . $date_html . $title_html .
				'<div class="dsgo-timeline-item__content">',
			'closing' => '</div></div>' . $close_link . '</div>',
		);
	}
}
