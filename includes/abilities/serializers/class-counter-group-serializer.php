<?php
/**
 * Serializer for designsetgo/counter-group.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/counter-group
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CounterGroup_Serializer.
 */
class CounterGroup_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// This block's own attribute names are columns/columnsTablet/
		// columnsMobile. Reading the Grid block's names meant the author's
		// column counts never reached the markup.
		$desktop_cols = isset( $attributes['columns'] ) ? Serializer_Support::numeric_attribute( $attributes['columns'] ) : 3;
		$tablet_cols  = isset( $attributes['columnsTablet'] ) ? Serializer_Support::numeric_attribute( $attributes['columnsTablet'] ) : 2;
		$mobile_cols  = isset( $attributes['columnsMobile'] ) ? Serializer_Support::numeric_attribute( $attributes['columnsMobile'] ) : 1;
		// `gap` is a STRING attribute defaulting to '32px', and save.js
		// writes it through untouched. intval() + 'px' happened to
		// reproduce the default exactly, which is why the defaults
		// fixture passed, but turned '2rem' into '2px'.
		$gap          = isset( $attributes['gap'] ) && is_string( $attributes['gap'] ) && '' !== $attributes['gap']
			? $attributes['gap']
			: '32px';
		$duration     = isset( $attributes['animationDuration'] ) ? floatval( $attributes['animationDuration'] ) : 2;
		$delay        = isset( $attributes['animationDelay'] ) ? floatval( $attributes['animationDelay'] ) : 0;
		$easing       = isset( $attributes['animationEasing'] ) ? $attributes['animationEasing'] : 'easeOutQuad';
		$use_grouping = isset( $attributes['useGrouping'] ) ? $attributes['useGrouping'] : true;
		$separator    = isset( $attributes['separator'] ) ? $attributes['separator'] : ',';
		$decimal      = isset( $attributes['decimal'] ) ? $attributes['decimal'] : '.';
		// save.js reads `alignContent`. This read `alignment`, which no
		// version of the block has ever declared, so the fallback fired
		// every time and every group serialized as --align-center.
		$align        = isset( $attributes['alignContent'] ) && is_string( $attributes['alignContent'] ) && '' !== $attributes['alignContent']
			? $attributes['alignContent']
			: 'center';

		$outer_style = 'align-self:stretch;--dsgo-counter-columns-desktop:' . (string) $desktop_cols . ';--dsgo-counter-columns-tablet:' . (string) $tablet_cols . ';--dsgo-counter-columns-mobile:' . (string) $mobile_cols . ';--dsgo-counter-gap:' . $gap;

		// Child Counter blocks inherit this through CSS. save.js DOES
		// convert here, unlike the Counter block's own hoverColor.
		if ( ! empty( $attributes['hoverColor'] ) && is_string( $attributes['hoverColor'] ) ) {
			$outer_style .= ';--dsgo-counter-hover-color:' . Serializer_Support::convert_color_value_to_css_var( $attributes['hoverColor'] );
		}

		$data_attrs  = ' data-animation-duration="' . esc_attr( (string) $duration ) . '"';
		$data_attrs .= ' data-animation-delay="' . esc_attr( (string) $delay ) . '"';
		$data_attrs .= ' data-animation-easing="' . esc_attr( $easing ) . '"';
		$data_attrs .= ' data-use-grouping="' . ( $use_grouping ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-separator="' . esc_attr( $separator ) . '"';
		$data_attrs .= ' data-decimal="' . esc_attr( $decimal ) . '"';

		return array(
			'opening' => '<div class="wp-block-designsetgo-counter-group dsgo-counter-group" style="' . esc_attr( $outer_style ) . '"' . $data_attrs . '><div class="dsgo-counter-group__inner dsgo-counter-group__inner--align-' . esc_attr( $align ) . '">',
			'closing' => '</div></div>',
		);
	}
}
