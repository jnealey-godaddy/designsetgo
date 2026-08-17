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

if ( ! function_exists( 'designsetgo_chart_format_value' ) ) {
	/**
	 * Format a value for display, dropping meaningless trailing decimals.
	 *
	 * @param float $value Value.
	 * @return string Display string.
	 */
	function designsetgo_chart_format_value( $value ) {
		$formatted = number_format( (float) $value, 2, '.', '' );

		if ( false !== strpos( $formatted, '.' ) ) {
			$formatted = rtrim( rtrim( $formatted, '0' ), '.' );
		}

		return '' === $formatted ? '0' : $formatted;
	}
}
