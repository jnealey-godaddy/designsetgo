<?php
/**
 * Serializer for designsetgo/row.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/row
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

use DesignSetGo\Abilities\Block_Schema_Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Row_Serializer.
 */
class Row_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// save.js renders `<TagName>` from `tagName || 'div'`. The mirror
		// hardcoded div, so a row saved as a <section> failed validation
		// on its tag name alone.
		$row_tag         = isset( $attributes['tagName'] ) && is_string( $attributes['tagName'] ) && '' !== $attributes['tagName']
			? $attributes['tagName']
			: 'div';
		$constrain_width = isset( $attributes['constrainWidth'] ) ? $attributes['constrainWidth'] : false;
		$content_width   = isset( $attributes['contentWidth'] ) ? $attributes['contentWidth'] : '';
		$mobile_stack    = isset( $attributes['mobileStack'] ) ? $attributes['mobileStack'] : false;
		$align           = isset( $attributes['align'] ) ? $attributes['align'] : 'full';
		$layout          = isset( $attributes['layout'] ) ? $attributes['layout'] : array();
		$justify_content = isset( $layout['justifyContent'] ) ? $layout['justifyContent'] : 'left';
		$flex_wrap       = isset( $layout['flexWrap'] ) ? $layout['flexWrap'] : 'nowrap';

		// Build outer classes (order: wp-block-*, alignX, dsgo-*).
		$outer_class_parts = array( 'wp-block-designsetgo-row' );
		if ( 'full' === $align ) {
			$outer_class_parts[] = 'alignfull';
		} elseif ( 'wide' === $align ) {
			$outer_class_parts[] = 'alignwide';
		}
		$outer_class_parts[] = 'dsgo-flex';
		if ( Serializer_Support::has_overlay( $attributes ) ) {
			$outer_class_parts[] = 'dsgo-flex--has-overlay';
		}
		if ( $mobile_stack ) {
			$outer_class_parts[] = 'dsgo-flex--mobile-stack';
		}
		if ( ! $constrain_width ) {
			$outer_class_parts[] = 'dsgo-no-width-constraint';
		}

		// Attribute defaults replace the entire style object, never deep-merge.
		$style          = $attributes['style'] ?? ( Block_Schema_Loader::get_block_json( $block_name )['attributes']['style']['default'] ?? array() );
		$support_styles = Serializer_Support::get_block_support_styles( $style )['styles'];

		// Inner div styles with gap.
		$inner_styles       = array(
			'display:flex',
			'justify-content:' . esc_attr( $justify_content ),
			'flex-wrap:' . esc_attr( $flex_wrap ),
		);
		$vertical_alignment = $layout['verticalAlignment'] ?? '';
		$align_map          = array(
			'top'           => 'flex-start',
			'center'        => 'center',
			'bottom'        => 'flex-end',
			'stretch'       => 'stretch',
			'space-between' => 'space-between',
		);
		if ( isset( $align_map[ $vertical_alignment ] ) ) {
			$inner_styles[] = 'align-items:' . $align_map[ $vertical_alignment ];
		}
		$gap = Serializer_Support::spacing_gap( $style['spacing']['blockGap'] ?? null );
		if ( is_string( $gap ) && '' !== $gap ) {
			$inner_styles[] = 'gap:' . $gap;
		}
		if ( $constrain_width ) {
			$max_width      = $content_width ? $content_width : 'var(--wp--style--global--content-size, 1140px)';
			$inner_styles[] = 'max-width:' . esc_attr( $max_width );
			$inner_styles[] = 'margin-left:auto';
			$inner_styles[] = 'margin-right:auto';
		}

		return array(
			'opening' => '<' . $row_tag . ' class="' . esc_attr( implode( ' ', $outer_class_parts ) ) . '" style="' .
				esc_attr( implode( ';', array_merge( Serializer_Support::container_hover_styles( $attributes ), $support_styles ) ) ) .
				'"><div class="dsgo-flex__inner" style="' . esc_attr( implode( ';', $inner_styles ) ) . '">',
			'closing' => '</div></' . $row_tag . '>',
		);
	}
}
