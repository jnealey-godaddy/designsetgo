<?php
/**
 * Chart block axis chrome.
 *
 * Gridlines, the zero baseline reference, and the axis labels — everything
 * drawn around the series rather than as part of it. Plot-local coordinates,
 * positioned by the translated group in `render.php`.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_chart_grid' ) ) {
	/**
	 * Render horizontal gridlines and their axis tick labels.
	 *
	 * @param array $geo Geometry context.
	 * @return string SVG fragment.
	 */
	function designsetgo_chart_grid( array $geo ) {
		$ticks = designsetgo_chart_ticks(
			$geo['min'],
			$geo['max'],
			isset( $geo['tick_count'] ) ? $geo['tick_count'] : 5
		);
		$out   = '';

		foreach ( $ticks as $tick ) {
			$y = $geo['plot_h'] - designsetgo_chart_scale(
				$tick,
				$geo['min'],
				$geo['max'],
				0,
				$geo['plot_h']
			);

			$out .= sprintf(
				'<line class="dsgo-chart__gridline" x1="0" y1="%1$s" x2="%2$s" y2="%1$s"></line>',
				esc_attr( designsetgo_chart_number( $y ) ),
				esc_attr( designsetgo_chart_number( $geo['plot_w'] ) )
			);

			$out .= sprintf(
				'<text class="dsgo-chart__tick" x="-8" y="%s" text-anchor="end" dominant-baseline="middle">%s</text>',
				esc_attr( designsetgo_chart_number( $y ) ),
				esc_html( designsetgo_chart_format_value( $tick ) )
			);
		}

		return $out;
	}
}

if ( ! function_exists( 'designsetgo_chart_zero_y' ) ) {
	/**
	 * The plot-local y of the value zero, clamped into the plot.
	 *
	 * Bars grow from this line, not from the axis minimum, so a negative value
	 * reads as a downward bar instead of a short upward one.
	 *
	 * @param array $geo Geometry context.
	 * @return float Plot-local y coordinate.
	 */
	function designsetgo_chart_zero_y( array $geo ) {
		$zero = min( max( 0.0, (float) $geo['min'] ), (float) $geo['max'] );

		return $geo['plot_h'] - designsetgo_chart_scale(
			$zero,
			$geo['min'],
			$geo['max'],
			0,
			$geo['plot_h']
		);
	}
}

if ( ! function_exists( 'designsetgo_chart_label_interval' ) ) {
	/**
	 * How many rows to skip between drawn category labels.
	 *
	 * Twelve categories of "September" overlap into an unreadable smear if all
	 * are drawn, so past a density threshold only every Nth is written — the
	 * axis still orients the reader, and `designsetgo_render_chart()` forces the
	 * legend back on when this returns more than 1, so the complete set of
	 * category names never disappears from the page.
	 *
	 * @param array $rows   Chart rows.
	 * @param float $plot_w Plot width in user units.
	 * @return int Interval, one or greater.
	 */
	function designsetgo_chart_label_interval( array $rows, $plot_w ) {
		$count = count( $rows );

		if ( $count < 2 ) {
			return 1;
		}

		$longest = 0;

		foreach ( $rows as $row ) {
			$longest = max( $longest, mb_strlen( $row['label'] ) );
		}

		if ( 0 === $longest ) {
			return 1;
		}

		// Font size is 14 user units; ~0.6em per character is a safe average
		// for proportional text, plus a space of padding between neighbours.
		$needed    = $longest * 14 * 0.6 + 8;
		$available = $plot_w / $count;

		return (int) max( 1, ceil( $needed / max( 1, $available ) ) );
	}
}

if ( ! function_exists( 'designsetgo_chart_category_labels' ) ) {
	/**
	 * Render the x-axis category labels.
	 *
	 * Category identity must not depend on the legend — the legend is optional
	 * and colour alone cannot carry it. Bar and line charts label their axis
	 * directly; the donut has no axis and keeps its legend instead.
	 *
	 * @param array  $rows Chart rows.
	 * @param array  $geo  Geometry context.
	 * @param string $type Chart type.
	 * @return string SVG fragment.
	 */
	function designsetgo_chart_category_labels( array $rows, array $geo, $type ) {
		$count = count( $rows );

		if ( 0 === $count ) {
			return '';
		}

		$out      = '';
		$slot     = $geo['plot_w'] / $count;
		$step     = $count > 1 ? $geo['plot_w'] / ( $count - 1 ) : 0;
		$y        = $geo['plot_h'] + 16;
		$interval = designsetgo_chart_label_interval( $rows, $geo['plot_w'] );

		foreach ( array_values( $rows ) as $i => $row ) {
			if ( '' === $row['label'] || 0 !== $i % $interval ) {
				continue;
			}

			if ( 'line' === $type ) {
				$x = $count > 1 ? $i * $step : $geo['plot_w'] / 2;
			} else {
				$x = $i * $slot + $slot / 2;
			}

			// Keep the outermost line labels inside the viewport.
			$anchor = 'middle';

			if ( 'line' === $type && $count > 1 && 0 === $i ) {
				$anchor = 'start';
			} elseif ( 'line' === $type && $count > 1 && $count - 1 === $i ) {
				$anchor = 'end';
			}

			$out .= sprintf(
				'<text class="dsgo-chart__category" x="%s" y="%s" text-anchor="%s">%s</text>',
				esc_attr( designsetgo_chart_number( $x ) ),
				esc_attr( designsetgo_chart_number( $y ) ),
				esc_attr( $anchor ),
				esc_html( $row['label'] )
			);
		}

		return $out;
	}
}
