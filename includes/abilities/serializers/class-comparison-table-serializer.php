<?php
/**
 * Serializer for designsetgo/comparison-table.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/comparison-table
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ComparisonTable_Serializer.
 */
class ComparisonTable_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/comparison-table/save.js. The block holds no
		// inner blocks: the whole table is built from the columns and
		// rows attributes.
		$table_columns   = ( isset( $attributes['columns'] ) && is_array( $attributes['columns'] ) ) ? $attributes['columns'] : array();
		$table_rows      = ( isset( $attributes['rows'] ) && is_array( $attributes['rows'] ) ) ? $attributes['rows'] : array();
		$alternating     = ! empty( $attributes['alternatingRows'] );
		$responsive_mode = isset( $attributes['responsiveMode'] ) ? (string) $attributes['responsiveMode'] : 'scroll';
		$show_ctas       = ! empty( $attributes['showCtaButtons'] );
		$cta_style       = isset( $attributes['ctaStyle'] ) ? (string) $attributes['ctaStyle'] : 'filled';

		$class_parts = array( 'wp-block-designsetgo-comparison-table' );
		if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
			$class_parts[] = 'align' . $attributes['align'];
		}
		$class_parts[] = 'dsgo-comparison-table';
		if ( $alternating ) {
			$class_parts[] = 'dsgo-comparison-table--alternating';
		}
		if ( 'stack' === $responsive_mode ) {
			$class_parts[] = 'dsgo-comparison-table--responsive-stack';
		}
		if ( 'scroll' === $responsive_mode ) {
			$class_parts[] = 'dsgo-comparison-table--responsive-scroll';
		}

		$table_styles     = array();
		$table_color_vars = array(
			'featuredColumnColor'   => '--dsgo-comparison-featured-color',
			'headerBackgroundColor' => '--dsgo-comparison-header-bg',
			'headerTextColor'       => '--dsgo-comparison-header-text',
		);
		foreach ( $table_color_vars as $attribute_name => $custom_property ) {
			$colour = isset( $attributes[ $attribute_name ] ) ? (string) $attributes[ $attribute_name ] : '';
			if ( '' !== $colour ) {
				$table_styles[] = $custom_property . ':' . Serializer_Support::convert_color_value_to_css_var( $colour );
			}
		}

		// Header row.
		$header_cells = '<th class="dsgo-comparison-table__header-cell dsgo-comparison-table__header-cell--label"></th>';
		foreach ( $table_columns as $column ) {
			$col_featured  = ! empty( $column['featured'] );
			$col_name      = isset( $column['name'] ) ? (string) $column['name'] : '';
			$col_link      = isset( $column['link'] ) ? (string) $column['link'] : '';
			$col_link_text = isset( $column['linkText'] ) ? (string) $column['linkText'] : '';

			$header_cells .= '<th class="' . esc_attr(
				'dsgo-comparison-table__header-cell' . ( $col_featured ? ' dsgo-comparison-table__header-cell--featured' : '' )
			) . '">';

			if ( $col_featured ) {
				$header_cells .= '<span class="dsgo-comparison-table__featured-badge">' . esc_html__( 'Popular', 'designsetgo' ) . '</span>';
			}

			$header_cells .= '<span class="dsgo-comparison-table__column-name">' . wp_kses_post( $col_name ) . '</span>';

			if ( $show_ctas && '' !== $col_link ) {
				$header_cells .= '<a href="' . esc_url( $col_link ) . '" class="' .
					esc_attr( 'dsgo-comparison-table__cta dsgo-comparison-table__cta--' . $cta_style ) .
					'" rel="noopener noreferrer">' .
					esc_html( '' !== $col_link_text ? $col_link_text : __( 'Get Started', 'designsetgo' ) ) .
					'</a>';
			} elseif ( $show_ctas && '' !== $col_link_text ) {
				$header_cells .= '<span class="' .
					esc_attr( 'dsgo-comparison-table__cta dsgo-comparison-table__cta--' . $cta_style ) . '">' .
					esc_html( $col_link_text ) . '</span>';
			}

			$header_cells .= '</th>';
		}

		// Body rows.
		$body_rows = '';
		foreach ( $table_rows as $row ) {
			$row_label   = isset( $row['label'] ) ? (string) $row['label'] : '';
			$row_tooltip = isset( $row['tooltip'] ) ? (string) $row['tooltip'] : '';
			$row_cells   = ( isset( $row['cells'] ) && is_array( $row['cells'] ) ) ? $row['cells'] : array();

			$body_rows .= '<tr class="dsgo-comparison-table__row">' .
				'<td class="dsgo-comparison-table__cell dsgo-comparison-table__cell--label">' .
				'<div class="dsgo-comparison-table__label-wrapper">' .
				'<span class="dsgo-comparison-table__row-label">' . wp_kses_post( $row_label ) . '</span>';

			if ( '' !== $row_tooltip ) {
				$body_rows .= '<span class="dsgo-comparison-table__tooltip-trigger" data-tooltip="' . esc_attr( $row_tooltip ) .
					'" aria-label="' . esc_attr( $row_tooltip ) . '" role="button" tabindex="0">?</span>';
			}

			$body_rows .= '</div></td>';

			foreach ( $row_cells as $cell_index => $cell ) {
				$cell_type     = isset( $cell['type'] ) ? (string) $cell['type'] : 'text';
				$cell_value    = isset( $cell['value'] ) ? (string) $cell['value'] : '';
				$cell_column   = $table_columns[ $cell_index ] ?? array();
				$cell_featured = ! empty( $cell_column['featured'] );
				$cell_label    = isset( $cell_column['name'] ) ? (string) $cell_column['name'] : '';

				$body_rows .= '<td class="' . esc_attr(
					'dsgo-comparison-table__cell' . ( $cell_featured ? ' dsgo-comparison-table__cell--featured' : '' )
				) . '" data-label="' . esc_attr( $cell_label ) . '">' .
					'<div class="dsgo-comparison-table__cell-content">';

				if ( 'check' === $cell_type ) {
					$body_rows .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none"' .
						' stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"' .
						' class="dsgo-comparison-table__icon dsgo-comparison-table__icon--check" aria-label="Yes" role="img">' .
						'<polyline points="20 6 9 17 4 12"></polyline></svg>';
				} elseif ( 'cross' === $cell_type ) {
					$body_rows .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none"' .
						' stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"' .
						' class="dsgo-comparison-table__icon dsgo-comparison-table__icon--cross" aria-label="No" role="img">' .
						'<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
				} elseif ( 'text' === $cell_type ) {
					$body_rows .= '<span class="dsgo-comparison-table__cell-text">' . wp_kses_post( $cell_value ) . '</span>';
				}

				$body_rows .= '</div></td>';
			}

			$body_rows .= '</tr>';
		}

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' .
				( empty( $table_styles ) ? '' : ' style="' . esc_attr( implode( ';', $table_styles ) ) . '"' ) . '>' .
				'<div class="dsgo-comparison-table__wrapper">' .
				'<table class="dsgo-comparison-table__table">' .
				'<thead class="dsgo-comparison-table__header"><tr>' . $header_cells . '</tr></thead>' .
				'<tbody class="dsgo-comparison-table__body">' . $body_rows . '</tbody>' .
				'</table></div>',
			'closing' => '</div>',
		);
	}
}
