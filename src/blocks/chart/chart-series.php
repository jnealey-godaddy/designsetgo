<?php
/**
 * Chart block series renderers.
 *
 * Each function returns SVG fragment markup. Bar and line fragments are drawn
 * in plot-local coordinates and positioned by the translated group in
 * `render.php`; the donut is drawn against the full viewport.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_chart_number' ) ) {
	/**
	 * Round a coordinate for compact, stable markup.
	 *
	 * @param float $value Coordinate.
	 * @return string Attribute-ready number.
	 */
	function designsetgo_chart_number( $value ) {
		return (string) round( (float) $value, 2 );
	}
}

if ( ! function_exists( 'designsetgo_chart_value_label' ) ) {
	/**
	 * Render a single value label.
	 *
	 * @param float  $x        Centre x.
	 * @param float  $y        Baseline y.
	 * @param string $text     Display text.
	 * @param string $anchor   Text anchor.
	 * @param string $baseline Optional dominant-baseline.
	 * @return string Text element markup.
	 */
	function designsetgo_chart_value_label( $x, $y, $text, $anchor = 'middle', $baseline = '' ) {
		return sprintf(
			'<text class="dsgo-chart__value" x="%s" y="%s" text-anchor="%s"%s>%s</text>',
			esc_attr( designsetgo_chart_number( $x ) ),
			esc_attr( designsetgo_chart_number( $y ) ),
			esc_attr( $anchor ),
			'' === $baseline ? '' : ' dominant-baseline="' . esc_attr( $baseline ) . '"',
			esc_html( $text )
		);
	}
}

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

if ( ! function_exists( 'designsetgo_chart_bars' ) ) {
	/**
	 * Render a bar series.
	 *
	 * @param array $rows   Chart rows.
	 * @param array $colors Series colours.
	 * @param array $geo    Geometry context.
	 * @return string SVG fragment.
	 */
	function designsetgo_chart_bars( array $rows, array $colors, array $geo ) {
		$slot = $geo['plot_w'] / count( $rows );
		$gap  = $slot * 0.2;
		$out  = '';

		foreach ( array_values( $rows ) as $i => $row ) {
			$bar_h = max(
				0,
				designsetgo_chart_scale( $row['value'], $geo['min'], $geo['max'], 0, $geo['plot_h'] )
			);
			$top   = $geo['plot_h'] - $bar_h;

			$out .= sprintf(
				'<rect class="dsgo-chart__bar" x="%s" y="%s" width="%s" height="%s" fill="%s"></rect>',
				esc_attr( designsetgo_chart_number( $i * $slot + $gap / 2 ) ),
				esc_attr( designsetgo_chart_number( $top ) ),
				esc_attr( designsetgo_chart_number( $slot - $gap ) ),
				esc_attr( designsetgo_chart_number( $bar_h ) ),
				esc_attr( $colors[ $i ] )
			);

			if ( $geo['show_values'] ) {
				$out .= designsetgo_chart_value_label(
					$i * $slot + $slot / 2,
					$top - 6,
					designsetgo_chart_format_value( $row['value'] )
				);
			}
		}

		return $out;
	}
}

if ( ! function_exists( 'designsetgo_chart_line' ) ) {
	/**
	 * Render a line series.
	 *
	 * @param array $rows   Chart rows.
	 * @param array $colors Series colours.
	 * @param array $geo    Geometry context.
	 * @return string SVG fragment.
	 */
	function designsetgo_chart_line( array $rows, array $colors, array $geo ) {
		$values = wp_list_pluck( $rows, 'value' );
		$count  = count( $values );

		$out = sprintf(
			'<polyline class="dsgo-chart__line" points="%s" fill="none" stroke="%s" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></polyline>',
			esc_attr(
				designsetgo_chart_line_points(
					$values,
					$geo['plot_w'],
					$geo['plot_h'],
					$geo['min'],
					$geo['max']
				)
			),
			esc_attr( $colors[0] )
		);

		$step = $count > 1 ? $geo['plot_w'] / ( $count - 1 ) : 0;

		foreach ( array_values( $values ) as $i => $value ) {
			$x = $count > 1 ? $i * $step : $geo['plot_w'] / 2;
			$y = $geo['plot_h'] - designsetgo_chart_scale(
				$value,
				$geo['min'],
				$geo['max'],
				0,
				$geo['plot_h']
			);

			$out .= sprintf(
				'<circle class="dsgo-chart__point" cx="%s" cy="%s" r="3" fill="%s"></circle>',
				esc_attr( designsetgo_chart_number( $x ) ),
				esc_attr( designsetgo_chart_number( $y ) ),
				esc_attr( $colors[0] )
			);

			if ( $geo['show_values'] ) {
				// Pull the end labels inwards so they do not overhang the axis.
				$anchor = 'middle';

				if ( $count > 1 && 0 === $i ) {
					$anchor = 'start';
				} elseif ( $count > 1 && $count - 1 === $i ) {
					$anchor = 'end';
				}

				$out .= designsetgo_chart_value_label(
					$x,
					$y - 8,
					designsetgo_chart_format_value( $value ),
					$anchor
				);
			}
		}

		return $out;
	}
}

if ( ! function_exists( 'designsetgo_chart_donut' ) ) {
	/**
	 * Render a donut series.
	 *
	 * @param array $rows   Chart rows.
	 * @param array $colors Series colours.
	 * @param array $geo    Geometry context.
	 * @return string SVG fragment.
	 */
	function designsetgo_chart_donut( array $rows, array $colors, array $geo ) {
		$values = array_map( 'abs', wp_list_pluck( $rows, 'value' ) );
		$total  = array_sum( $values );

		// Labels sit outside the ring so they read against the page background
		// rather than against whatever colour the author gave the slice — dark
		// text on a mid-tone fill is unreadable and cannot be predicted here.
		$label_room = $geo['show_values'] ? 26 : 0;
		$radius     = min( $geo['width'], $geo['height'] ) / 2 - 4 - $label_room;
		$cx         = $geo['width'] / 2;
		$cy         = $geo['height'] / 2;
		$cursor     = 0.0;
		$out        = '';

		foreach ( array_values( $rows ) as $i => $row ) {
			$share = $total > 0 ? abs( $row['value'] ) / $total : 0.0;
			$sweep = $share * 360;
			$path  = designsetgo_chart_arc_path( $cx, $cy, $radius, $radius * 0.4, $cursor, $cursor + $sweep );
			$mid   = $cursor + $sweep / 2;
			$cursor += $sweep;

			if ( '' === $path ) {
				continue;
			}

			$out .= sprintf(
				'<path class="dsgo-chart__slice" d="%s" fill="%s"></path>',
				esc_attr( $path ),
				esc_attr( $colors[ $i ] )
			);

			if ( $geo['show_values'] ) {
				$rad  = deg2rad( $mid - 90 );
				$edge = $radius + 10;
				$dx   = cos( $rad );

				// Push the text away from the ring on whichever side it sits.
				$anchor = 'middle';

				if ( $dx > 0.25 ) {
					$anchor = 'start';
				} elseif ( $dx < -0.25 ) {
					$anchor = 'end';
				}

				$out .= designsetgo_chart_value_label(
					$cx + $edge * $dx,
					$cy + $edge * sin( $rad ),
					designsetgo_chart_format_value( $share * 100 ) . '%',
					$anchor,
					'middle'
				);
			}
		}

		return $out;
	}
}
