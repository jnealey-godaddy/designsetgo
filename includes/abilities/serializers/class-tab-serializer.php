<?php
/**
 * Serializer for designsetgo/tab.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/tab
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tab_Serializer.
 */
class Tab_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$unique_id     = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 );
		$title         = isset( $attributes['title'] ) ? $attributes['title'] : 'Tab';
		$anchor        = isset( $attributes['anchor'] ) ? $attributes['anchor'] : '';
		$icon          = isset( $attributes['icon'] ) ? $attributes['icon'] : '';
		$icon_position = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'none';

		// Build panel ID.
		$panel_id = 'panel-' . ( $anchor ? esc_attr( $anchor ) : esc_attr( $unique_id ) );

		// Build aria-label.
		$aria_label = $title ? $title : 'Tab ' . $unique_id;

		// Data attributes for icon.
		$icon_data = '';
		if ( $icon && $icon_position && 'none' !== $icon_position ) {
			$safe_icon     = strtolower( preg_replace( '/[^a-z0-9\-]/i', '', $icon ) );
			$safe_position = in_array( $icon_position, array( 'left', 'right' ), true ) ? $icon_position : 'left';
			$icon_data     = ' data-icon="' . esc_attr( $safe_icon ) . '" data-icon-position="' . esc_attr( $safe_position ) . '"';
		}

		// Same list-building reason as scroll-accordion above.
		$tab_classes = array( 'wp-block-designsetgo-tab' );
		$tab_align   = Serializer_Support::align_class( $block_name, $attributes );
		if ( '' !== $tab_align ) {
			$tab_classes[] = $tab_align;
		}
		$tab_classes[] = 'dsgo-tab';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $tab_classes ) ) . '" role="tabpanel" aria-labelledby="tab-' . esc_attr( $unique_id ) . '" aria-label="' . esc_attr( $aria_label ) . '" id="' . esc_attr( $panel_id ) . '" hidden' . $icon_data . '><div class="dsgo-tab__content">',
			'closing' => '</div></div>',
		);
	}
}
