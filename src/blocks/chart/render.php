<?php
/**
 * Chart block server render.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/chart-geometry.php';
require_once __DIR__ . '/chart-data.php';
require_once __DIR__ . '/chart-colors.php';
require_once __DIR__ . '/chart-series.php';
require_once __DIR__ . '/chart-axis.php';

if ( ! function_exists( 'designsetgo_chart_value_format' ) ) {
	/**
	 * Read the value-formatting attributes into the shape the formatter takes.
	 *
	 * Kept separate from the geometry so the data table, which has no
	 * geometry context, can build the same bag from the same attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @return array Format options for designsetgo_chart_format_value().
	 */
	function designsetgo_chart_value_format( array $attributes ) {
		return array(
			'prefix' => isset( $attributes['valuePrefix'] ) ? (string) $attributes['valuePrefix'] : '',
			'suffix' => isset( $attributes['valueSuffix'] ) ? (string) $attributes['valueSuffix'] : '',
			'group'  => ! empty( $attributes['groupThousands'] ),
		);
	}
}

if ( ! function_exists( 'designsetgo_chart_geometry' ) ) {
	/**
	 * Build the geometry context for a chart.
	 *
	 * The plot is inset from the viewport to leave room for axis tick labels
	 * on the left and value labels above the tallest bar or point.
	 *
	 * @param array  $attributes Block attributes.
	 * @param array  $rows       Chart rows.
	 * @param bool   $has_grid   Whether the grid is drawn.
	 * @param string $type       Chart type.
	 * @return array Geometry context.
	 */
	function designsetgo_chart_geometry( array $attributes, array $rows, $has_grid, $type = 'bar' ) {
		$height      = max( 80, min( 800, (int) ( isset( $attributes['height'] ) ? $attributes['height'] : 240 ) ) );
		$show_values = ! isset( $attributes['showValues'] ) || (bool) $attributes['showValues'];
		$pad_top     = $show_values ? 18 : 0;
		$bounds      = designsetgo_chart_bounds( wp_list_pluck( $rows, 'value' ) );
		$tick_count  = 5;
		$format      = designsetgo_chart_value_format( $attributes );

		// Reserve room under the plot for the x-axis category labels, but only
		// when there is something to write there.
		$has_categories = 'donut' !== $type
			&& (bool) array_filter( wp_list_pluck( $rows, 'label' ), 'strlen' );
		$pad_bottom     = $has_categories ? 22 : 0;

		// Round the axis outwards so bars and gridlines land on round numbers.
		// Applied whether or not the grid is drawn, so toggling it never
		// rescales the series.
		if ( 'donut' !== $type ) {
			$nice       = designsetgo_chart_nice_bounds( $bounds['min'], $bounds['max'], $tick_count );
			$bounds     = $nice;
			$tick_count = $nice['count'];
		}

		// Size the left gutter to the labels that will actually be drawn.
		// Formatting can widen a tick well past the old fixed 44 ("1000000"
		// becomes "$1,000,000"), and the canvas does not clip, so a gutter
		// that is too narrow paints the axis outside the block.
		$pad_left = 0;

		if ( $has_grid ) {
			$labels = array();

			foreach ( designsetgo_chart_ticks( $bounds['min'], $bounds['max'], $tick_count ) as $tick ) {
				$labels[] = designsetgo_chart_format_value( $tick, $format );
			}

			$pad_left = designsetgo_chart_tick_gutter( $labels );
		}

		return array(
			'width'       => 600,
			'height'      => $height,
			'pad_left'    => $pad_left,
			'pad_top'     => $pad_top,
			'pad_bottom'  => $pad_bottom,
			'plot_w'      => 600 - $pad_left,
			'plot_h'      => max( 1, $height - $pad_top - $pad_bottom ),
			'min'         => $bounds['min'],
			'max'         => $bounds['max'],
			'tick_count'  => $tick_count,
			'show_values' => $show_values,
			'format'      => $format,
		);
	}
}

if ( ! function_exists( 'designsetgo_render_chart' ) ) {
	/**
	 * Render the chart block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner content, unused.
	 * @param WP_Block $block      Block instance.
	 * @return string Rendered markup.
	 */
	function designsetgo_render_chart( $attributes, $content = '', $block = null ) {
		$attributes = (array) $attributes;
		$total      = 0;
		$rows       = designsetgo_chart_rows( $attributes, $block, $total );

		if ( empty( $rows ) ) {
			return '';
		}

		$type = isset( $attributes['chartType'] ) ? $attributes['chartType'] : 'bar';

		if ( ! in_array( $type, array( 'bar', 'line', 'donut' ), true ) ) {
			$type = 'bar';
		}

		// A donut has no axes, so it never draws a grid.
		$has_grid = 'donut' !== $type
			&& ( ! isset( $attributes['showGrid'] ) || (bool) $attributes['showGrid'] );

		$colors = designsetgo_chart_palette( $attributes, count( $rows ) );

		if ( 'donut' === $type ) {
			$filtered = designsetgo_chart_donut_rows( $rows, $colors );
			$rows     = $filtered['rows'];
			$colors   = $filtered['colors'];

			if ( empty( $rows ) ) {
				return '';
			}
		}

		$geo = designsetgo_chart_geometry( $attributes, $rows, $has_grid, $type );

		// True when the axis carries every category name, which is what lets
		// the legend be optional in the first place.
		$axis_names_all = false;

		if ( 'donut' === $type ) {
			$svg = designsetgo_chart_donut( $rows, $colors, $geo );
		} else {
			$plot = $has_grid ? designsetgo_chart_grid( $geo ) : '';

			$plot .= 'line' === $type
				? designsetgo_chart_line( $rows, $colors, $geo )
				: designsetgo_chart_bars( $rows, $colors, $geo );

			$plot .= designsetgo_chart_category_labels( $rows, $geo, $type );

			$axis_names_all = 1 === designsetgo_chart_label_interval( $rows, $geo['plot_w'] );

			$svg = sprintf(
				'<g class="dsgo-chart__plot" transform="translate(%s, %s)">%s</g>',
				esc_attr( (string) $geo['pad_left'] ),
				esc_attr( (string) $geo['pad_top'] ),
				$plot // Every interpolation is escaped by the series renderers.
			);
		}

		// The legend may only be turned off when something else on the page
		// still names every category. A donut has no axis at all, and a dense
		// axis drops names to stay readable — in both cases the legend is the
		// only remaining sighted route from a mark to its category.
		$show_legend = ! $axis_names_all
			|| ! isset( $attributes['showLegend'] )
			|| (bool) $attributes['showLegend'];

		$legend = $show_legend ? designsetgo_chart_legend( $rows, $colors ) : '';
		$label  = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';

		// Only the cap is worth disclosing. Rows also fall out for being
		// non-numeric, or negative on a donut, and reporting those as a
		// shortfall would cry wolf on charts that are behaving exactly as
		// documented.
		$disclosed = $total > designsetgo_chart_max_rows() ? $total : 0;

		$wrapper = get_block_wrapper_attributes(
			array( 'class' => 'dsgo-chart dsgo-chart--' . $type )
		);

		return sprintf(
			'<figure %1$s><svg class="dsgo-chart__canvas" viewBox="0 0 %2$d %3$d" role="presentation" aria-hidden="true" focusable="false">%4$s</svg>%5$s%6$s</figure>',
			$wrapper, // Escaped by get_block_wrapper_attributes().
			(int) $geo['width'],
			(int) $geo['height'],
			$svg, // Every interpolation above is individually escaped.
			$legend,
			designsetgo_chart_data_table( $rows, $label, $disclosed, designsetgo_chart_value_format( $attributes ) )
		);
	}
}

echo designsetgo_render_chart( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All interpolations escaped inside the renderer.
