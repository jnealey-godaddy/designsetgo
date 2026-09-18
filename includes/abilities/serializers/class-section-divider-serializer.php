<?php
/**
 * Serializer for designsetgo/section-divider.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/section-divider
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SectionDivider_Serializer.
 */
class SectionDivider_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/section-divider/save.js plus its utils/.
		$class_parts = array( 'wp-block-designsetgo-section-divider' );
		if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
			$class_parts[] = 'align' . $attributes['align'];
		}

		// Wrapper style: background only, and only when set.
		$wrapper_styles = array();
		if ( ! empty( $attributes['backgroundColor'] ) && is_string( $attributes['backgroundColor'] ) ) {
			$wrapper_styles[] = '--dsgo-section-divider-bg:' .
				Serializer_Support::convert_color_value_to_css_var( $attributes['backgroundColor'] );
		}

		// Shape style: each custom property is emitted only when the
		// attribute differs from the CSS-inherited default, so a default
		// divider carries no inline style at all.
		$shape_styles = array();
		if ( ! empty( $attributes['fillColor'] ) && is_string( $attributes['fillColor'] ) ) {
			$shape_styles[] = '--dsgo-section-divider-fill:' .
				Serializer_Support::convert_color_value_to_css_var( $attributes['fillColor'] );
		}
		if ( Serializer_Support::is_explicit_shape_size( $attributes['height'] ?? null ) ) {
			$shape_styles[] = '--dsgo-shape-height:' . $attributes['height'] . 'px';
		}
		if ( Serializer_Support::is_explicit_shape_size( $attributes['width'] ?? null ) ) {
			$shape_styles[] = '--dsgo-shape-width:' . $attributes['width'] . '%';
		}
		if ( ! empty( $attributes['flipX'] ) ) {
			$shape_styles[] = '--dsgo-shape-flip-x:-1';
		}
		if ( ! empty( $attributes['flipY'] ) ) {
			$shape_styles[] = '--dsgo-shape-flip-y:-1';
		}

		$shape       = isset( $attributes['shape'] ) ? (string) $attributes['shape'] : 'inherit';
		$shape_class = 'inherit' === $shape ? 'is-shape-inherit' : 'is-shape-' . $shape;

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' .
				( empty( $wrapper_styles ) ? '' : ' style="' . esc_attr( implode( ';', $wrapper_styles ) ) . '"' ) . '>' .
				'<div class="' . esc_attr( 'dsgo-section-divider__shape dsgo-shape-divider ' . $shape_class ) . '"' .
				( empty( $shape_styles ) ? '' : ' style="' . esc_attr( implode( ';', $shape_styles ) ) . '"' ) .
				' aria-hidden="true"></div>',
			'closing' => '</div>',
		);
	}
}
