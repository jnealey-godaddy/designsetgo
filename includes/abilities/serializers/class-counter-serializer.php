<?php
/**
 * Serializer for designsetgo/counter.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/counter
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counter_Serializer.
 */
class Counter_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$unique_id    = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : wp_unique_id( 'counter-' );
		$start_value  = isset( $attributes['startValue'] ) ? floatval( $attributes['startValue'] ) : 0;
		$end_value    = isset( $attributes['endValue'] ) ? floatval( $attributes['endValue'] ) : 100;
		$decimals     = isset( $attributes['decimals'] ) ? Serializer_Support::numeric_attribute( $attributes['decimals'] ) : 0;
		$prefix       = isset( $attributes['prefix'] ) ? $attributes['prefix'] : '';
		$suffix       = isset( $attributes['suffix'] ) ? $attributes['suffix'] : '';
		$label        = isset( $attributes['label'] ) ? $attributes['label'] : '';
		$duration     = isset( $attributes['duration'] ) ? floatval( $attributes['duration'] ) : 2;
		$delay        = isset( $attributes['delay'] ) ? floatval( $attributes['delay'] ) : 0;
		$easing       = isset( $attributes['easing'] ) ? $attributes['easing'] : 'easeOutQuad';
		$use_grouping = isset( $attributes['useGrouping'] ) ? $attributes['useGrouping'] : true;
		$separator    = isset( $attributes['separator'] ) ? $attributes['separator'] : ',';
		$decimal      = isset( $attributes['decimal'] ) ? $attributes['decimal'] : '.';

		$data_attrs  = ' data-start-value="' . esc_attr( (string) $start_value ) . '"';
		$data_attrs .= ' data-end-value="' . esc_attr( (string) $end_value ) . '"';
		$data_attrs .= ' data-decimals="' . esc_attr( (string) $decimals ) . '"';
		$data_attrs .= ' data-prefix="' . esc_attr( $prefix ) . '"';
		$data_attrs .= ' data-suffix="' . esc_attr( $suffix ) . '"';
		$data_attrs .= ' data-duration="' . esc_attr( (string) $duration ) . '"';
		$data_attrs .= ' data-delay="' . esc_attr( (string) $delay ) . '"';
		$data_attrs .= ' data-easing="' . esc_attr( $easing ) . '"';
		$data_attrs .= ' data-use-grouping="' . ( $use_grouping ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-separator="' . esc_attr( $separator ) . '"';
		$data_attrs .= ' data-decimal="' . esc_attr( $decimal ) . '"';

		// save.js writes `icon-${iconPosition}`; this was hardcoded to
		// icon-top, so every non-default position failed validation.
		$icon_position = isset( $attributes['iconPosition'] ) ? (string) $attributes['iconPosition'] : 'top';

		// save.js applies hoverColor RAW - it does not call
		// convertColorToCSSVar() the way most blocks do - so the mirror
		// must not convert it either. Writing `var(--wp--preset--...)`
		// here would be more correct CSS and still wrong: the mirror's
		// job is to reproduce save(), not to improve on it. That save()
		// stores an unparseable value for a preset colour is a real bug
		// in the block, but fixing it changes stored markup and so needs
		// a deprecation.
		$counter_style = 'text-align:center';
		if ( ! empty( $attributes['hoverColor'] ) && is_string( $attributes['hoverColor'] ) ) {
			$counter_style .= ';--dsgo-counter-hover-color:' . $attributes['hoverColor'];
		}

		$inner_html  = '<div class="dsgo-counter__content icon-' . esc_attr( $icon_position ) . '">';
		$inner_html .= '<div class="dsgo-counter__number">';
		$inner_html .= '<span class="dsgo-counter__value">' . esc_html( (string) $start_value ) . '</span>';
		$inner_html .= '</div></div>';
		if ( $label ) {
			$inner_html .= '<div class="dsgo-counter__label">' . esc_html( $label ) . '</div>';
		}

		return array(
			'opening' => '<div class="wp-block-designsetgo-counter dsgo-counter" id="' . esc_attr( $unique_id ) . '" style="' . esc_attr( $counter_style ) . '"' . $data_attrs . '>' . $inner_html,
			'closing' => '</div>',
		);
	}
}
