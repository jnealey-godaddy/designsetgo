<?php
/**
 * Serializer for designsetgo/icon-list.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/icon-list
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icon_List_Serializer.
 */
class Icon_List_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$layout    = isset( $attributes['layout'] ) ? $attributes['layout'] : 'vertical';
		$gap       = isset( $attributes['gap'] ) ? $attributes['gap'] : '24px';
		$columns   = isset( $attributes['columns'] ) ? Serializer_Support::numeric_attribute( $attributes['columns'] ) : 2;
		$alignment = isset( $attributes['alignment'] ) ? $attributes['alignment'] : 'left';

		// Calculate alignment values.
		$align_items     = '';
		$justify_content = '';
		$flex_direction  = '';

		if ( 'vertical' === $layout ) {
			$flex_direction = 'column';
			if ( 'center' === $alignment ) {
				$align_items = 'center';
			} elseif ( 'right' === $alignment ) {
				$align_items = 'flex-end';
			} else {
				$align_items = 'flex-start';
			}
		} elseif ( 'horizontal' === $layout ) {
			$flex_direction = 'row';
			if ( 'center' === $alignment ) {
				$justify_content = 'center';
			} elseif ( 'right' === $alignment ) {
				$justify_content = 'flex-end';
			} else {
				$justify_content = 'flex-start';
			}
		}

		// Build container styles.
		$container_style_parts = array();
		if ( 'grid' === $layout ) {
			$container_style_parts[] = 'display:grid';
			$container_style_parts[] = 'grid-template-columns:repeat(' . $columns . ', 1fr)';
		} else {
			$container_style_parts[] = 'display:flex';
			$container_style_parts[] = 'flex-direction:' . $flex_direction;
		}
		$container_style_parts[] = 'gap:' . esc_attr( $gap );
		if ( $align_items ) {
			$container_style_parts[] = 'align-items:' . $align_items;
		}
		if ( $justify_content ) {
			$container_style_parts[] = 'justify-content:' . $justify_content;
		}
		$container_style_parts[] = 'width:100%';
		$container_style         = implode( ';', $container_style_parts );

		$outer_class = 'wp-block-designsetgo-icon-list dsgo-icon-list dsgo-icon-list--' . esc_attr( $layout );

		// getSaveElement() passes no block context to a static save(), so
		// a child icon-list-item cannot read the parent's iconStyle - the
		// PARENT publishes it here for the frontend injector. Emitted only
		// when iconStyle is explicitly set, which is what keeps pre-existing
		// content byte-identical and deprecation-free.
		//
		// Note the dsgo- prefix: the parent writes data-dsgo-icon-style,
		// while an icon-list-ITEM writes data-icon-style. They are
		// different attributes on different elements.
		//
		// Omitting it did not make the block invalid, which is why this
		// survived: the markup still matched an OLDER deprecation, so the
		// editor migrated it silently on open and dirtied the post. Only
		// the matrix's console.info check caught it.
		$list_icon_attrs = '';
		$list_icon_style = isset( $attributes['iconStyle'] ) && in_array( $attributes['iconStyle'], array( 'filled', 'outlined' ), true )
			? (string) $attributes['iconStyle']
			: '';
		if ( '' !== $list_icon_style ) {
			$list_icon_attrs = ' data-dsgo-icon-style="' . esc_attr( $list_icon_style ) . '"';

			if ( 'outlined' === $list_icon_style && ! empty( $attributes['strokeWidth'] ) && is_numeric( $attributes['strokeWidth'] ) ) {
				$list_icon_attrs .= ' data-dsgo-icon-stroke-width="' . esc_attr( Serializer_Support::format_js_number( (float) $attributes['strokeWidth'] ) ) . '"';
			}
		}

		return array(
			'opening' => '<div class="' . esc_attr( $outer_class ) . '" style="width:100%"' . $list_icon_attrs . '><div class="dsgo-icon-list__items" style="' . esc_attr( $container_style ) . '">',
			'closing' => '</div></div>',
		);
	}
}
