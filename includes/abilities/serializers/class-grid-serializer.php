<?php
/**
 * Serializer for designsetgo/grid.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/grid
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
 * Grid_Serializer.
 */
class Grid_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$desktop_cols    = isset( $attributes['desktopColumns'] ) ? Serializer_Support::numeric_attribute( $attributes['desktopColumns'] ) : 3;
		$tablet_cols     = isset( $attributes['tabletColumns'] ) ? Serializer_Support::numeric_attribute( $attributes['tabletColumns'] ) : 2;
		$mobile_cols     = isset( $attributes['mobileColumns'] ) ? Serializer_Support::numeric_attribute( $attributes['mobileColumns'] ) : 1;
		$align_items     = isset( $attributes['alignItems'] ) ? $attributes['alignItems'] : 'stretch';
		$constrain_width = isset( $attributes['constrainWidth'] ) ? $attributes['constrainWidth'] : false;
		$content_width   = isset( $attributes['contentWidth'] ) ? $attributes['contentWidth'] : '';
		$align           = isset( $attributes['align'] ) ? $attributes['align'] : 'full';

		// Build outer classes (order: wp-block-*, alignX, dsgo-*).
		$outer_class_parts = array( 'wp-block-designsetgo-grid' );
		if ( 'full' === $align ) {
			$outer_class_parts[] = 'alignfull';
		} elseif ( 'wide' === $align ) {
			$outer_class_parts[] = 'alignwide';
		}
		$outer_class_parts[] = 'dsgo-grid';
		if ( Serializer_Support::has_overlay( $attributes ) ) {
			$outer_class_parts[] = 'dsgo-grid--has-overlay';
		}
		$outer_class_parts[] = 'dsgo-grid-cols-' . $desktop_cols;
		$outer_class_parts[] = 'dsgo-grid-cols-tablet-' . $tablet_cols;
		$outer_class_parts[] = 'dsgo-grid-cols-mobile-' . $mobile_cols;
		if ( ! empty( $attributes['matchRowHeights'] ) ) {
			$outer_class_parts[] = 'dsgo-grid--match-rows';
		}
		if ( ! $constrain_width ) {
			$outer_class_parts[] = 'dsgo-no-width-constraint';
		}

		// Attribute defaults replace the entire style object, never deep-merge.
		$style          = $attributes['style'] ?? ( Block_Schema_Loader::get_block_json( $block_name )['attributes']['style']['default'] ?? array() );
		$support_styles = Serializer_Support::get_block_support_styles( $style )['styles'];

		// Inner div styles.
		$default_gap       = 'var(--wp--preset--spacing--50)';
		$block_gap         = $style['spacing']['blockGap'] ?? null;
		$row_gap           = is_array( $block_gap ) ? ( $block_gap['top'] ?? '' ) : $block_gap;
		$column_gap        = is_array( $block_gap ) ? ( $block_gap['left'] ?? '' ) : $block_gap;
		$custom_row_gap    = $attributes['rowGap'] ?? '';
		$custom_column_gap = $attributes['columnGap'] ?? '';
		$row_gap           = Serializer_Support::spacing_gap( $row_gap ) ?? ( '' !== $custom_row_gap ? $custom_row_gap : $default_gap );
		$column_gap        = Serializer_Support::spacing_gap( $column_gap ) ?? ( '' !== $custom_column_gap ? $custom_column_gap : $default_gap );
		// Mirrors src/blocks/grid/grid-columns.js: a custom template wins,
		// then a column min width, then the repeated column count.
		$column_template = isset( $attributes['columnTemplate'] ) && is_string( $attributes['columnTemplate'] ) ? trim( $attributes['columnTemplate'] ) : '';
		$columns_css     = 'repeat(' . $desktop_cols . ', 1fr)';
		if ( '' !== $column_template ) {
			$columns_css = $column_template;
		} elseif ( ! empty( $attributes['columnMinWidth'] ) ) {
			$share       = $desktop_cols > 1 ? '(100% - ' . ( $desktop_cols - 1 ) . ' * ' . $column_gap . ') / ' . $desktop_cols : '100%';
			$columns_css = 'repeat(auto-fill, minmax(min(100%, max(' . $attributes['columnMinWidth'] . ', ' . $share . ')), 1fr))';
		}
		$inner_styles = array(
			'display:grid',
			'grid-template-columns:' . $columns_css,
			'align-items:' . esc_attr( $align_items ),
			'row-gap:' . $row_gap,
			'column-gap:' . $column_gap,
		);
		if ( $constrain_width ) {
			$max_width      = $content_width ? $content_width : 'var(--wp--style--global--content-size, 1140px)';
			$inner_styles[] = 'max-width:' . esc_attr( $max_width );
			$inner_styles[] = 'margin-left:auto';
			$inner_styles[] = 'margin-right:auto';
		}

		// save.js honours tagName; hardcoding <div> lost an author's
		// choice of <section>, <article> and so on.
		$grid_tag = ( isset( $attributes['tagName'] ) && '' !== $attributes['tagName'] )
			? preg_replace( '/[^a-z0-9]/i', '', (string) $attributes['tagName'] )
			: 'div';
		$grid_tag = '' !== $grid_tag ? $grid_tag : 'div';

		return array(
			'opening' => '<' . $grid_tag . ' class="' . esc_attr( implode( ' ', $outer_class_parts ) ) . '" style="' .
				esc_attr( implode( ';', array_merge( Serializer_Support::container_hover_styles( $attributes ), $support_styles ) ) ) .
				'"><div class="dsgo-grid__inner" style="' . esc_attr( implode( ';', $inner_styles ) ) . '">',
			'closing' => '</div></' . $grid_tag . '>',
		);
	}
}
