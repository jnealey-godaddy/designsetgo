<?php
/**
 * Tests for the chart block geometry helpers.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * Tests for the pure geometry math behind the chart block.
 *
 * @group chart
 */
class Chart_Geometry_Test extends WP_UnitTestCase {

	/**
	 * Load the helpers under test.
	 *
	 * They live beside the block rather than in `includes/`, so nothing has
	 * pulled them in by the time the suite runs.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once DESIGNSETGO_PATH . 'src/blocks/chart/chart-geometry.php';
	}

	/**
	 * The midpoint of the input range maps to the midpoint of the output range.
	 */
	public function test_scale_maps_the_midpoint() {
		$this->assertSame( 50.0, designsetgo_chart_scale( 5, 0, 10, 0, 100 ) );
	}

	/**
	 * Both endpoints map exactly, with no drift.
	 */
	public function test_scale_maps_the_endpoints() {
		$this->assertSame( 0.0, designsetgo_chart_scale( 0, 0, 10, 0, 100 ) );
		$this->assertSame( 100.0, designsetgo_chart_scale( 10, 0, 10, 0, 100 ) );
	}

	/**
	 * A zero-width input range centres rather than dividing by zero.
	 */
	public function test_scale_survives_a_zero_range() {
		$this->assertSame( 50.0, designsetgo_chart_scale( 5, 5, 5, 0, 100 ) );
	}

	/**
	 * A wholly positive series is anchored at zero so bars read proportionally.
	 */
	public function test_bounds_pads_a_positive_series_down_to_zero() {
		$bounds = designsetgo_chart_bounds( array( 3, 7, 5 ) );
		$this->assertSame( 0.0, $bounds['min'] );
		$this->assertSame( 7.0, $bounds['max'] );
	}

	/**
	 * A negative minimum is preserved rather than clamped to zero.
	 */
	public function test_bounds_keeps_a_negative_minimum() {
		$bounds = designsetgo_chart_bounds( array( -3, 7 ) );
		$this->assertSame( -3.0, $bounds['min'] );
		$this->assertSame( 7.0, $bounds['max'] );
	}

	/**
	 * An empty series still yields a usable, non-degenerate range.
	 */
	public function test_bounds_of_an_empty_series_is_zero_to_one() {
		$bounds = designsetgo_chart_bounds( array() );
		$this->assertSame( 0.0, $bounds['min'] );
		$this->assertSame( 1.0, $bounds['max'] );
	}

	/**
	 * Every value produces exactly one coordinate pair.
	 */
	public function test_line_points_returns_one_pair_per_value() {
		$points = designsetgo_chart_line_points( array( 0, 5, 10 ), 100, 50, 0, 10 );
		$this->assertCount( 3, explode( ' ', $points ) );
	}

	/**
	 * The largest value sits at the top of the viewport, meaning y = 0.
	 */
	public function test_line_points_inverts_the_y_axis() {
		$points = designsetgo_chart_line_points( array( 0, 10 ), 100, 50, 0, 10 );
		$pairs  = explode( ' ', $points );
		$last   = explode( ',', $pairs[1] );
		$this->assertSame( '0', $last[1] );
	}

	/**
	 * A lone value is centred horizontally rather than pinned to the left edge.
	 */
	public function test_line_points_of_a_single_value_is_centred() {
		$points = designsetgo_chart_line_points( array( 5 ), 100, 50, 0, 10 );
		$this->assertStringStartsWith( '50,', $points );
	}

	/**
	 * An arc is a well-formed path with a move and an arc command.
	 */
	public function test_arc_path_starts_with_a_move_command() {
		$path = designsetgo_chart_arc_path( 50, 50, 40, 10, 0, 90 );
		$this->assertStringStartsWith( 'M ', $path );
		$this->assertStringContainsString( 'A ', $path );
	}

	/**
	 * A zero sweep draws nothing rather than a degenerate path.
	 */
	public function test_arc_path_of_a_zero_sweep_is_empty() {
		$this->assertSame( '', designsetgo_chart_arc_path( 50, 50, 40, 10, 30, 30 ) );
	}

	/**
	 * Ticks include both bounds so the axis is fully labelled.
	 */
	public function test_ticks_span_the_bounds_inclusively() {
		$ticks = designsetgo_chart_ticks( 0, 10, 5 );
		$this->assertSame( 0.0, $ticks[0] );
		$this->assertSame( 10.0, end( $ticks ) );
	}

	/**
	 * The caller gets exactly as many ticks as it asked for.
	 */
	public function test_ticks_returns_the_requested_count() {
		$this->assertCount( 5, designsetgo_chart_ticks( 0, 10, 5 ) );
		$this->assertCount( 3, designsetgo_chart_ticks( -4, 4, 3 ) );
	}

	/**
	 * Tick spacing is uniform across the range.
	 */
	public function test_ticks_are_evenly_spaced() {
		$ticks = designsetgo_chart_ticks( 0, 10, 3 );
		$this->assertSame( array( 0.0, 5.0, 10.0 ), $ticks );
	}

	/**
	 * Asking for fewer than two ticks falls back to the bare bounds.
	 */
	public function test_ticks_of_a_degenerate_count_returns_the_bounds() {
		$this->assertSame( array( 0.0, 10.0 ), designsetgo_chart_ticks( 0, 10, 1 ) );
	}

	/**
	 * A flat range yields repeated ticks rather than a division by zero.
	 */
	public function test_ticks_of_a_zero_range_does_not_divide_by_zero() {
		$ticks = designsetgo_chart_ticks( 5, 5, 4 );
		$this->assertSame( array( 5.0, 5.0, 5.0, 5.0 ), $ticks );
	}

	/**
	 * Whole numbers display without a pointless ".00".
	 */
	public function test_format_value_drops_a_trailing_zero_decimal() {
		$this->assertSame( '10', designsetgo_chart_format_value( 10.0 ) );
		$this->assertSame( '10.5', designsetgo_chart_format_value( 10.5 ) );
	}

	/**
	 * Long decimals are rounded to two places for legibility.
	 */
	public function test_format_value_rounds_to_two_decimals() {
		$this->assertSame( '3.33', designsetgo_chart_format_value( 10 / 3 ) );
	}

	/**
	 * Zero formats as "0", not an empty string.
	 */
	public function test_format_value_of_zero_is_not_empty() {
		$this->assertSame( '0', designsetgo_chart_format_value( 0 ) );
	}
}
