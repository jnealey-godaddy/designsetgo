<?php
/**
 * Chart block geometry helpers.
 *
 * Pure math. No WordPress dependencies, no output — everything here is
 * unit-testable in isolation.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_chart_scale' ) ) {
	/**
	 * Map a value from one range onto another.
	 *
	 * @param float $value   Input value.
	 * @param float $min     Input range minimum.
	 * @param float $max     Input range maximum.
	 * @param float $out_min Output range minimum.
	 * @param float $out_max Output range maximum.
	 * @return float Mapped value.
	 */
	function designsetgo_chart_scale( $value, $min, $max, $out_min, $out_max ) {
		$range = (float) $max - (float) $min;

		if ( 0.0 === $range ) {
			// A flat series has no meaningful position; centre it.
			return ( (float) $out_min + (float) $out_max ) / 2;
		}

		$ratio = ( (float) $value - (float) $min ) / $range;

		return (float) $out_min + $ratio * ( (float) $out_max - (float) $out_min );
	}
}

if ( ! function_exists( 'designsetgo_chart_bounds' ) ) {
	/**
	 * Compute the axis bounds for a series.
	 *
	 * A wholly positive series is anchored at zero so bar heights read
	 * proportionally rather than being exaggerated by a floating baseline.
	 *
	 * @param array $values Numeric values.
	 * @return array{min: float, max: float} Bounds.
	 */
	function designsetgo_chart_bounds( array $values ) {
		$numbers = array_map( 'floatval', array_filter( $values, 'is_numeric' ) );

		if ( empty( $numbers ) ) {
			return array(
				'min' => 0.0,
				'max' => 1.0,
			);
		}

		$min = min( $numbers );
		$max = max( $numbers );

		if ( $min > 0 ) {
			$min = 0.0;
		}

		if ( $min === $max ) {
			$max = $min + 1.0;
		}

		return array(
			'min' => (float) $min,
			'max' => (float) $max,
		);
	}
}

if ( ! function_exists( 'designsetgo_chart_line_points' ) ) {
	/**
	 * Build an SVG polyline `points` string.
	 *
	 * @param array $values Numeric values.
	 * @param int   $width  Viewport width.
	 * @param int   $height Viewport height.
	 * @param float $min    Axis minimum.
	 * @param float $max    Axis maximum.
	 * @return string Space-separated `x,y` pairs.
	 */
	function designsetgo_chart_line_points( array $values, $width, $height, $min, $max ) {
		$count = count( $values );

		if ( 0 === $count ) {
			return '';
		}

		$points = array();
		$step   = $count > 1 ? $width / ( $count - 1 ) : 0;

		foreach ( array_values( $values ) as $index => $value ) {
			$x = $count > 1 ? $index * $step : $width / 2;
			// SVG y grows downward, so a larger value must produce a smaller y.
			$y = $height - designsetgo_chart_scale( $value, $min, $max, 0, $height );

			$points[] = round( $x, 2 ) . ',' . round( $y, 2 );
		}

		return implode( ' ', $points );
	}
}

if ( ! function_exists( 'designsetgo_chart_arc_path' ) ) {
	/**
	 * Build an SVG donut segment path.
	 *
	 * @param float $cx        Centre x.
	 * @param float $cy        Centre y.
	 * @param float $radius    Outer radius.
	 * @param float $thickness Ring thickness.
	 * @param float $start_deg Start angle, degrees clockwise from 12 o'clock.
	 * @param float $end_deg   End angle.
	 * @return string Path `d` attribute, empty for a zero sweep.
	 */
	function designsetgo_chart_arc_path( $cx, $cy, $radius, $thickness, $start_deg, $end_deg ) {
		$sweep = (float) $end_deg - (float) $start_deg;

		if ( abs( $sweep ) < 0.01 ) {
			return '';
		}

		// Clamp: a full circle cannot be drawn as one arc.
		$sweep   = min( $sweep, 359.99 );
		$end_deg = (float) $start_deg + $sweep;
		$inner   = max( 0.0, (float) $radius - (float) $thickness );

		$point = function ( $r, $deg ) use ( $cx, $cy ) {
			$rad = deg2rad( (float) $deg - 90 );
			return array(
				round( (float) $cx + $r * cos( $rad ), 3 ),
				round( (float) $cy + $r * sin( $rad ), 3 ),
			);
		};

		list( $ox1, $oy1 ) = $point( $radius, $start_deg );
		list( $ox2, $oy2 ) = $point( $radius, $end_deg );
		list( $ix2, $iy2 ) = $point( $inner, $end_deg );
		list( $ix1, $iy1 ) = $point( $inner, $start_deg );

		$large = $sweep > 180 ? 1 : 0;

		return sprintf(
			'M %s %s A %s %s 0 %d 1 %s %s L %s %s A %s %s 0 %d 0 %s %s Z',
			$ox1,
			$oy1,
			$radius,
			$radius,
			$large,
			$ox2,
			$oy2,
			$ix2,
			$iy2,
			$inner,
			$inner,
			$large,
			$ix1,
			$iy1
		);
	}
}

if ( ! function_exists( 'designsetgo_chart_ticks' ) ) {
	/**
	 * Evenly spaced axis tick values spanning the bounds inclusively.
	 *
	 * @param float $min   Axis minimum.
	 * @param float $max   Axis maximum.
	 * @param int   $count Number of ticks wanted.
	 * @return array List of floats.
	 */
	function designsetgo_chart_ticks( $min, $max, $count ) {
		$min   = (float) $min;
		$max   = (float) $max;
		$count = (int) $count;

		if ( $count < 2 ) {
			return array( $min, $max );
		}

		$step  = ( $max - $min ) / ( $count - 1 );
		$ticks = array();

		for ( $i = 0; $i < $count; $i++ ) {
			// Repeated addition of a fractional step accumulates binary noise
			// (0.1 * 2 => 0.20000000000000004), so settle each tick.
			$ticks[] = round( $min + $step * $i, 10 );
		}

		return $ticks;
	}
}

if ( ! function_exists( 'designsetgo_chart_nice_step' ) ) {
	/**
	 * Round a raw step up to the nearest "nice" interval.
	 *
	 * Readers parse 0/10/20/30 instantly and 0/8.13/16.25 not at all, so the
	 * step is snapped to 1, 2, 2.5, or 5 times a power of ten.
	 *
	 * @param float $raw Unrounded step.
	 * @return float Nice step, always greater than zero.
	 */
	function designsetgo_chart_nice_step( $raw ) {
		$raw = abs( (float) $raw );

		if ( $raw <= 0.0 ) {
			return 1.0;
		}

		$magnitude  = pow( 10, floor( log10( $raw ) ) );
		$normalised = $raw / $magnitude;

		foreach ( array( 1.0, 2.0, 2.5, 5.0, 10.0 ) as $candidate ) {
			if ( $normalised <= $candidate ) {
				return $candidate * $magnitude;
			}
		}

		return 10.0 * $magnitude;
	}
}

if ( ! function_exists( 'designsetgo_chart_nice_bounds' ) ) {
	/**
	 * Widen bounds outwards so every axis tick is a round number.
	 *
	 * @param float $min    Data minimum.
	 * @param float $max    Data maximum.
	 * @param int   $target Preferred number of ticks.
	 * @return array{min: float, max: float, count: int} Nice bounds.
	 */
	function designsetgo_chart_nice_bounds( $min, $max, $target = 5 ) {
		$min    = (float) $min;
		$max    = (float) $max;
		$target = max( 2, (int) $target );
		$range  = $max - $min;

		if ( $range <= 0.0 ) {
			$range = abs( $max ) > 0 ? abs( $max ) : 1.0;
		}

		$step = designsetgo_chart_nice_step( $range / ( $target - 1 ) );
		$low  = floor( $min / $step ) * $step;
		$high = ceil( $max / $step ) * $step;

		if ( $high === $low ) {
			$high = $low + $step;
		}

		return array(
			'min'   => (float) $low,
			'max'   => (float) $high,
			'count' => (int) round( ( $high - $low ) / $step ) + 1,
		);
	}
}

if ( ! function_exists( 'designsetgo_chart_text_width' ) ) {
	/**
	 * Estimate how wide a string paints, in SVG user units.
	 *
	 * The SVG is built on the server, so there is nothing to measure against
	 * -- the width has to be predicted from the characters. Digits, letters
	 * and currency symbols are treated as one width and separators as a
	 * narrower one, which is the only distinction that matters for axis
	 * labels.
	 *
	 * Calibrated against the rendered output at font-size 14: "$0" measures
	 * 16.2 user units and "$2,000,000" measures 71.9. The ratios below round
	 * slightly high on purpose. Over-reserving costs a few units of plot
	 * width; under-reserving paints the label outside the block.
	 *
	 * @param string $text      Text to measure.
	 * @param float  $font_size Font size in user units. Matches the 14 the
	 *                          stylesheet sets on .dsgo-chart__tick.
	 * @return float Estimated width in user units.
	 */
	function designsetgo_chart_text_width( $text, $font_size = 14 ) {
		$narrow = array( ',', '.', ' ', "'", ':', '|', 'i', 'l', 'I', 'j', 't', 'f', 'r' );
		$width  = 0.0;

		foreach ( preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
			$width += in_array( $char, $narrow, true ) ? 0.30 : 0.60;
		}

		return $width * (float) $font_size;
	}
}

if ( ! function_exists( 'designsetgo_chart_tick_gutter' ) ) {
	/**
	 * Width to reserve left of the plot for the y-axis tick labels.
	 *
	 * The labels are drawn 8 units left of the plot edge, anchored at their
	 * end, so the gutter has to cover the widest label plus that gap. A fixed
	 * 44 used to be assumed, which was enough for a bare "1000" and not for a
	 * formatted "$2,000,000" -- and because .dsgo-chart__canvas is
	 * `overflow: visible`, the excess was painted outside the block rather
	 * than clipped.
	 *
	 * Clamped at both ends: never below the historic 44, so charts with short
	 * labels keep the proportions they have always had, and never above 240,
	 * so an outlandish prefix cannot squeeze the plot out of existence.
	 *
	 * @param array $labels Formatted tick labels.
	 * @return int Gutter width in user units.
	 */
	function designsetgo_chart_tick_gutter( array $labels ) {
		$widest = 0.0;

		foreach ( $labels as $label ) {
			$widest = max( $widest, designsetgo_chart_text_width( $label ) );
		}

		// 8 for the gap the label is offset by, 4 so it never sits flush.
		return (int) min( 240, max( 44, (int) ceil( $widest ) + 12 ) );
	}
}

if ( ! function_exists( 'designsetgo_chart_format_value' ) ) {
	/**
	 * Format a value for display, dropping meaningless trailing decimals.
	 *
	 * The optional format options come from the block's valuePrefix,
	 * valueSuffix and groupThousands attributes. They are threaded through
	 * `$geo['format']` for the SVG call sites and passed directly to the
	 * data table. Omitting them entirely reproduces the original output, so
	 * a caller that does not format (the donut's share-of-total label) can
	 * keep calling with one argument.
	 *
	 * The prefix sits outside the minus sign, because currency is written
	 * "-$42", never "$-42".
	 *
	 * @param float $value  Value.
	 * @param array $format Optional. prefix (string), suffix (string),
	 *                      group (bool) to insert thousands separators.
	 * @return string Display string.
	 */
	function designsetgo_chart_format_value( $value, array $format = array() ) {
		$value  = (float) $value;
		$prefix = isset( $format['prefix'] ) ? (string) $format['prefix'] : '';
		$suffix = isset( $format['suffix'] ) ? (string) $format['suffix'] : '';
		$group  = ! empty( $format['group'] );

		// Work on the magnitude so the sign can be placed outside the prefix.
		$rounded   = round( abs( $value ), 2 );
		$formatted = number_format( $rounded, 2, '.', '' );

		if ( false !== strpos( $formatted, '.' ) ) {
			$formatted = rtrim( rtrim( $formatted, '0' ), '.' );
		}

		if ( '' === $formatted ) {
			$formatted = '0';
		}

		if ( $group ) {
			// Re-format with the locale separators, keeping exactly the
			// decimals that survived the trim above.
			$dot       = strpos( $formatted, '.' );
			$decimals  = false === $dot ? 0 : strlen( $formatted ) - $dot - 1;
			$formatted = number_format_i18n( $rounded, $decimals );
		}

		// A value that rounds away to zero is not negative.
		$sign = ( $value < 0 && $rounded > 0 ) ? '-' : '';

		return $sign . $prefix . $formatted . $suffix;
	}
}
