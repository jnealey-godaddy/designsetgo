<?php
/**
 * Chart block data resolution.
 *
 * Turns block attributes into a clean list of rows, resolves the series
 * colours, and renders the accessible data table.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_chart_rows' ) ) {
	/**
	 * Resolve the chart's rows from attributes or post meta.
	 *
	 * @param array         $attributes Block attributes.
	 * @param WP_Block|null $block      Block instance, for post context.
	 * @return array List of array{label: string, value: float}.
	 */
	function designsetgo_chart_rows( array $attributes, $block = null ) {
		$raw = isset( $attributes['data'] ) && is_array( $attributes['data'] )
			? $attributes['data']
			: array();

		$source = isset( $attributes['dataSource'] ) ? $attributes['dataSource'] : 'manual';

		if ( 'meta' === $source && ! empty( $attributes['metaKey'] ) ) {
			$raw = designsetgo_chart_meta_rows( (string) $attributes['metaKey'], $block );
		}

		$rows = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['value'] ) || ! is_numeric( $row['value'] ) ) {
				continue;
			}

			$rows[] = array(
				'label' => isset( $row['label'] ) ? (string) $row['label'] : '',
				'value' => (float) $row['value'],
			);
		}

		return $rows;
	}
}

if ( ! function_exists( 'designsetgo_chart_meta_rows' ) ) {
	/**
	 * Read a row array out of a post meta field.
	 *
	 * The field may hold either a JSON string or an already-decoded array.
	 * Protected meta is never readable — it is not authored for display.
	 *
	 * @param string        $meta_key Meta key.
	 * @param WP_Block|null $block    Block instance, for post context.
	 * @return array Raw rows.
	 */
	function designsetgo_chart_meta_rows( $meta_key, $block = null ) {
		if ( is_protected_meta( $meta_key, 'post' ) ) {
			return array();
		}

		$post_id = isset( $block->context['postId'] ) ? $block->context['postId'] : get_the_ID();

		if ( ! $post_id ) {
			return array();
		}

		$stored = get_post_meta( $post_id, $meta_key, true );

		if ( is_array( $stored ) ) {
			return $stored;
		}

		if ( is_string( $stored ) && '' !== $stored ) {
			$decoded = json_decode( $stored, true );

			return is_array( $decoded ) ? $decoded : array();
		}

		return array();
	}
}

if ( ! function_exists( 'designsetgo_chart_safe_color' ) ) {
	/**
	 * Reject a colour string that could smuggle behaviour into an attribute.
	 *
	 * Charts accept raw CSS colours so authors can use `var()` references, so
	 * the value cannot simply be run through `sanitize_hex_color()`.
	 *
	 * @param string $color Candidate colour.
	 * @return string The colour, or an empty string when unsafe.
	 */
	function designsetgo_chart_safe_color( $color ) {
		$color = trim( sanitize_text_field( (string) $color ) );

		if ( '' === $color ) {
			return '';
		}

		$lower = strtolower( $color );

		foreach ( array( 'javascript:', 'expression(', 'url(', '<', '@import' ) as $needle ) {
			if ( false !== strpos( $lower, $needle ) ) {
				return '';
			}
		}

		return $color;
	}
}

if ( ! function_exists( 'designsetgo_chart_palette' ) ) {
	/**
	 * Resolve the series colours.
	 *
	 * Falls back to theme.json custom properties so charts inherit the site
	 * palette rather than hard-coding brand colours.
	 *
	 * @param array $attributes Block attributes.
	 * @param int   $count      Number of series needed.
	 * @return array List of CSS colour strings.
	 */
	function designsetgo_chart_palette( array $attributes, $count ) {
		$palette = array();

		if ( isset( $attributes['palette'] ) && is_array( $attributes['palette'] ) ) {
			$palette = array_values(
				array_filter( array_map( 'designsetgo_chart_safe_color', $attributes['palette'] ) )
			);
		}

		if ( empty( $palette ) ) {
			$palette = array(
				'var(--wp--preset--color--primary, #3858e9)',
				'var(--wp--preset--color--secondary, #4ab866)',
				'var(--wp--preset--color--tertiary, #f0b849)',
				'var(--wp--preset--color--accent, #d94f4f)',
			);
		}

		$out = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$out[] = $palette[ $i % count( $palette ) ];
		}

		return $out;
	}
}

if ( ! function_exists( 'designsetgo_chart_data_table' ) ) {
	/**
	 * Render the visually-hidden data table.
	 *
	 * The SVG is aria-hidden, so this table is the accessible representation
	 * of the chart. It is always emitted.
	 *
	 * @param array  $rows  Chart rows.
	 * @param string $label Chart label.
	 * @return string Table markup.
	 */
	function designsetgo_chart_data_table( array $rows, $label ) {
		$out = '<table class="screen-reader-text">';

		if ( '' !== $label ) {
			$out .= '<caption>' . esc_html( $label ) . '</caption>';
		}

		$out .= '<thead><tr><th scope="col">' . esc_html__( 'Label', 'designsetgo' )
			. '</th><th scope="col">' . esc_html__( 'Value', 'designsetgo' )
			. '</th></tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$out .= '<tr><th scope="row">' . esc_html( $row['label'] ) . '</th><td>'
				. esc_html( designsetgo_chart_format_value( $row['value'] ) ) . '</td></tr>';
		}

		return $out . '</tbody></table>';
	}
}

if ( ! function_exists( 'designsetgo_chart_legend' ) ) {
	/**
	 * Render the legend.
	 *
	 * Colour is never the only channel identifying a series — the legend pairs
	 * each swatch with its label, and the data table repeats both.
	 *
	 * @param array $rows   Chart rows.
	 * @param array $colors Series colours.
	 * @return string Legend markup.
	 */
	function designsetgo_chart_legend( array $rows, array $colors ) {
		$out = '<ul class="dsgo-chart__legend">';

		foreach ( array_values( $rows ) as $i => $row ) {
			$out .= sprintf(
				'<li class="dsgo-chart__legend-item"><span class="dsgo-chart__swatch" style="background:%s" aria-hidden="true"></span>%s</li>',
				esc_attr( $colors[ $i ] ),
				esc_html( $row['label'] )
			);
		}

		return $out . '</ul>';
	}
}
