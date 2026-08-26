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
	 * With no format options the helper behaves exactly as it always has.
	 *
	 * Every existing call site passes one argument, so a regression here
	 * silently changes every chart already published.
	 */
	public function test_format_value_without_options_is_unchanged() {
		$this->assertSame( '10', designsetgo_chart_format_value( 10.0, array() ) );
		$this->assertSame( '3.33', designsetgo_chart_format_value( 10 / 3, array() ) );
		$this->assertSame( '1234567', designsetgo_chart_format_value( 1234567, array() ) );
	}

	/**
	 * A prefix leads the number and a suffix trails it.
	 */
	public function test_format_value_applies_prefix_and_suffix() {
		$this->assertSame( '$42', designsetgo_chart_format_value( 42, array( 'prefix' => '$' ) ) );
		$this->assertSame( '42%', designsetgo_chart_format_value( 42, array( 'suffix' => '%' ) ) );
		$this->assertSame(
			'$42 USD',
			designsetgo_chart_format_value( 42, array( 'prefix' => '$', 'suffix' => ' USD' ) )
		);
	}

	/**
	 * The prefix sits outside the minus sign, the way currency is written.
	 *
	 * "-$42" is correct; "$-42" is not.
	 */
	public function test_format_value_places_a_prefix_outside_the_minus_sign() {
		$this->assertSame( '-$42', designsetgo_chart_format_value( -42, array( 'prefix' => '$' ) ) );
	}

	/**
	 * Grouping inserts the locale thousands separator.
	 */
	public function test_format_value_groups_thousands_when_asked() {
		$this->assertSame(
			'1,234,567',
			designsetgo_chart_format_value( 1234567, array( 'group' => true ) )
		);
		$this->assertSame(
			'$1,234,567',
			designsetgo_chart_format_value( 1234567, array( 'group' => true, 'prefix' => '$' ) )
		);
	}

	/**
	 * Grouping keeps the trailing-zero trimming the ungrouped path does.
	 */
	public function test_format_value_grouping_still_trims_trailing_zeros() {
		$this->assertSame( '1,000', designsetgo_chart_format_value( 1000.0, array( 'group' => true ) ) );
		$this->assertSame( '1,000.5', designsetgo_chart_format_value( 1000.5, array( 'group' => true ) ) );
	}

	/**
	 * Grouping a negative keeps the sign ahead of the separated digits.
	 */
	public function test_format_value_groups_a_negative() {
		$this->assertSame(
			'-1,234',
			designsetgo_chart_format_value( -1234, array( 'group' => true ) )
		);
	}

	/**
	 * Zero still renders, with affixes attached.
	 */
	public function test_format_value_of_zero_keeps_its_affixes() {
		$this->assertSame( '$0', designsetgo_chart_format_value( 0, array( 'prefix' => '$' ) ) );
	}

	/**
	 * Text width grows with the string and treats commas as narrow.
	 *
	 * The estimate is calibrated against the browser: at font-size 14,
	 * "$0" measures 16.2 user units and "$2,000,000" measures 71.9.
	 */
	public function test_text_width_estimates_the_measured_widths() {
		$narrow = designsetgo_chart_text_width( '$0' );
		$wide   = designsetgo_chart_text_width( '$2,000,000' );

		// Within a reasonable margin of the measured values, and never under
		// -- under-reserving is what puts labels outside the container.
		$this->assertGreaterThanOrEqual( 16.2, $narrow );
		$this->assertLessThan( 22.0, $narrow );

		$this->assertGreaterThanOrEqual( 71.9, $wide );
		$this->assertLessThan( 85.0, $wide );
	}

	/**
	 * A comma takes less room than a digit.
	 */
	public function test_text_width_treats_separators_as_narrow() {
		$this->assertLessThan(
			designsetgo_chart_text_width( '11' ),
			designsetgo_chart_text_width( ',,' )
		);
	}

	/**
	 * The gutter never drops below the previous fixed value.
	 *
	 * Short labels kept the old 44-unit gutter, so unchanged charts keep
	 * their proportions.
	 */
	public function test_tick_gutter_keeps_the_historic_minimum() {
		$this->assertSame( 44, designsetgo_chart_tick_gutter( array( '0', '5', '10' ) ) );
	}

	/**
	 * A wide label widens the gutter enough to hold it.
	 *
	 * This is the regression: a fixed 44-unit gutter left "$2,000,000"
	 * hanging 35 units outside the chart's own box.
	 */
	public function test_tick_gutter_grows_for_a_wide_label() {
		$labels = array( '$0', '$1,000,000', '$2,000,000', '$3,000,000' );
		$gutter = designsetgo_chart_tick_gutter( $labels );

		$this->assertGreaterThan( 44, $gutter );

		// The label is drawn 8 units left of the plot edge, so the gutter has
		// to cover the widest label plus that gap.
		$this->assertGreaterThanOrEqual(
			designsetgo_chart_text_width( '$3,000,000' ) + 8,
			$gutter
		);
	}

	/**
	 * An absurd affix cannot eat the whole plot.
	 */
	public function test_tick_gutter_is_capped() {
		$gutter = designsetgo_chart_tick_gutter(
			array( str_repeat( 'M', 200 ) )
		);

		$this->assertLessThanOrEqual( 240, $gutter );
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
	 * The axis is rounded out to numbers a reader recognises.
	 */
	public function test_nice_bounds_rounds_the_axis_outwards() {
		$nice = designsetgo_chart_nice_bounds( 0, 32.5, 5 );
		$this->assertSame( 0.0, $nice['min'] );
		$this->assertSame( 40.0, $nice['max'] );
		$this->assertSame( 5, $nice['count'] );
	}

	/**
	 * Nice bounds always contain the original range.
	 */
	public function test_nice_bounds_never_clips_the_data() {
		$nice = designsetgo_chart_nice_bounds( -3, 24, 5 );
		$this->assertLessThanOrEqual( -3.0, $nice['min'] );
		$this->assertGreaterThanOrEqual( 24.0, $nice['max'] );
	}

	/**
	 * Every tick across nice bounds is itself a round number.
	 */
	public function test_nice_bounds_produce_round_ticks() {
		$nice  = designsetgo_chart_nice_bounds( 0, 32.5, 5 );
		$ticks = designsetgo_chart_ticks( $nice['min'], $nice['max'], $nice['count'] );
		$this->assertSame( array( 0.0, 10.0, 20.0, 30.0, 40.0 ), $ticks );
	}

	/**
	 * A tiny range still yields sensible sub-unit ticks.
	 */
	public function test_nice_bounds_handle_a_fractional_range() {
		$nice  = designsetgo_chart_nice_bounds( 0, 0.42, 5 );
		$ticks = designsetgo_chart_ticks( $nice['min'], $nice['max'], $nice['count'] );

		foreach ( $ticks as $tick ) {
			$this->assertSame(
				$tick,
				round( $tick, 4 ),
				'Tick ' . $tick . ' is not a clean value.'
			);
		}

		$this->assertGreaterThanOrEqual( 0.42, $nice['max'] );
	}

	/**
	 * A flat range does not collapse into a zero-height axis.
	 */
	public function test_nice_bounds_of_a_zero_range_still_spans() {
		$nice = designsetgo_chart_nice_bounds( 5, 5, 5 );
		$this->assertGreaterThan( $nice['min'], $nice['max'] );
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
