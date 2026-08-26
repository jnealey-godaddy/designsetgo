<?php
/**
 * Tests for the chart block server render.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * Tests the rendered markup, escaping, and data resolution of the chart block.
 *
 * @group chart
 */
class Chart_Render_Test extends WP_UnitTestCase {

	/**
	 * Render a chart block from an attribute array.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered markup.
	 */
	private function render( array $attributes ) {
		return do_blocks(
			'<!-- wp:designsetgo/chart ' . wp_json_encode( $attributes ) . ' /-->'
		);
	}

	/**
	 * A two-row sample series.
	 *
	 * @return array Rows.
	 */
	private function sample() {
		return array(
			array(
				'label' => 'Q1',
				'value' => 10,
			),
			array(
				'label' => 'Q2',
				'value' => 20,
			),
		);
	}

	/**
	 * The prefix and suffix reach the value labels drawn on the bars.
	 */
	public function test_prefix_and_suffix_reach_the_value_labels() {
		$html = $this->render(
			array(
				'chartType'   => 'bar',
				'data'        => $this->sample(),
				'valuePrefix' => '$',
				'showValues'  => true,
			)
		);

		$this->assertStringContainsString( '>$10<', $html );
		$this->assertStringContainsString( '>$20<', $html );
	}

	/**
	 * They reach the y-axis tick labels too, so the axis matches the bars.
	 */
	public function test_prefix_reaches_the_axis_ticks() {
		$html = $this->render(
			array(
				'chartType'   => 'bar',
				'data'        => $this->sample(),
				'valuePrefix' => '$',
				'showGrid'    => true,
			)
		);

		// The zero tick is always drawn, and carries the prefix.
		$this->assertMatchesRegularExpression(
			'/class="dsgo-chart__tick"[^>]*>\$0</',
			$html
		);
	}

	/**
	 * They reach the screen-reader data table, the accessible representation.
	 */
	public function test_suffix_reaches_the_data_table() {
		$html = $this->render(
			array(
				'chartType'   => 'bar',
				'data'        => $this->sample(),
				'valueSuffix' => '%',
			)
		);

		$this->assertStringContainsString( '<td>10%</td>', $html );
		$this->assertStringContainsString( '<td>20%</td>', $html );
	}

	/**
	 * Grouping separates thousands everywhere the raw number appears.
	 */
	public function test_group_thousands_separates_the_value_labels() {
		$html = $this->render(
			array(
				'chartType'      => 'bar',
				'data'           => array( array( 'label' => 'A', 'value' => 1234567 ) ),
				'groupThousands' => true,
				'showValues'     => true,
			)
		);

		$this->assertStringContainsString( '1,234,567', $html );
		$this->assertStringNotContainsString( '>1234567<', $html );
	}

	/**
	 * The donut's share label is a share of the total, not the author's value.
	 *
	 * Applying a currency prefix there would render "$42%", so the donut is
	 * deliberately left out of the formatting.
	 */
	public function test_donut_share_labels_ignore_the_affixes() {
		$html = $this->render(
			array(
				'chartType'   => 'donut',
				'data'        => $this->sample(),
				'valuePrefix' => '$',
				'showValues'  => true,
			)
		);

		// 10 of 30 and 20 of 30 -- shares, written without the prefix.
		$this->assertStringContainsString( '>33.33%<', $html );
		$this->assertStringNotContainsString( '$33.33%', $html );

		// The table still carries it, because those are the real values.
		$this->assertStringContainsString( '<td>$10</td>', $html );
	}

	/**
	 * Author-supplied affixes are escaped, not injected into the markup.
	 */
	public function test_affixes_are_escaped() {
		$html = $this->render(
			array(
				'chartType'   => 'bar',
				'data'        => $this->sample(),
				'valuePrefix' => '<script>x</script>',
			)
		);

		$this->assertStringNotContainsString( '<script>x</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * A chart that sets none of them renders exactly as it did before.
	 */
	public function test_unformatted_chart_is_unchanged() {
		$html = $this->render(
			array(
				'chartType'  => 'bar',
				'data'       => $this->sample(),
				'showValues' => true,
			)
		);

		$this->assertStringContainsString( '>10<', $html );
		$this->assertStringContainsString( '<td>10</td>', $html );
	}

	/**
	 * Wide axis labels widen the gutter instead of escaping the chart box.
	 *
	 * The canvas is `overflow: visible`, so a label that does not fit the
	 * gutter is not clipped -- it is painted outside the block's own border,
	 * over whatever sits to the left of it.
	 */
	public function test_wide_axis_labels_widen_the_plot_gutter() {
		$html = $this->render(
			array(
				'chartType'      => 'bar',
				'data'           => array(
					array(
						'label' => 'Q1',
						'value' => 1234567,
					),
					array(
						'label' => 'Q2',
						'value' => 2345678,
					),
				),
				'valuePrefix'    => '$',
				'groupThousands' => true,
				'showGrid'       => true,
			)
		);

		$this->assertSame(
			1,
			preg_match( '/class="dsgo-chart__plot" transform="translate\((\d+), /', $html, $m ),
			'The plot group is missing its transform.'
		);

		$gutter = (int) $m[1];

		// "$3,000,000" is the widest tick this data produces.
		$this->assertGreaterThanOrEqual(
			designsetgo_chart_text_width( '$3,000,000' ) + 8,
			$gutter,
			'The gutter is too narrow to hold the widest tick label.'
		);
	}

	/**
	 * A plain chart keeps the gutter it has always had.
	 */
	public function test_narrow_axis_labels_keep_the_original_gutter() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
				'showGrid'  => true,
			)
		);

		$this->assertStringContainsString(
			'class="dsgo-chart__plot" transform="translate(44, ',
			$html
		);
	}

	/**
	 * A gridless chart still has no left gutter at all.
	 */
	public function test_a_gridless_chart_has_no_gutter() {
		$html = $this->render(
			array(
				'chartType'   => 'bar',
				'data'        => $this->sample(),
				'valuePrefix' => '$',
				'showGrid'    => false,
			)
		);

		$this->assertStringContainsString(
			'class="dsgo-chart__plot" transform="translate(0, ',
			$html
		);
	}

	/**
	 * The block is registered, so the test suite is exercising real markup.
	 */
	public function test_the_block_is_registered() {
		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( 'designsetgo/chart' ),
			'designsetgo/chart is not registered — run `npm run build` first.'
		);
	}

	/**
	 * Each row becomes one bar.
	 */
	public function test_bar_chart_renders_one_rect_per_row() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
			)
		);
		$this->assertSame( 2, substr_count( $html, '<rect class="dsgo-chart__bar"' ) );
	}

	/**
	 * A line chart draws a single polyline through the values.
	 */
	public function test_line_chart_renders_a_polyline() {
		$html = $this->render(
			array(
				'chartType' => 'line',
				'data'      => $this->sample(),
			)
		);
		$this->assertStringContainsString( '<polyline', $html );
	}

	/**
	 * Each row becomes one donut slice.
	 */
	public function test_donut_chart_renders_one_path_per_row() {
		$html = $this->render(
			array(
				'chartType' => 'donut',
				'data'      => $this->sample(),
			)
		);
		$this->assertSame( 2, substr_count( $html, '<path class="dsgo-chart__slice"' ) );
	}

	/**
	 * An unknown chart type falls back to a bar chart rather than rendering nothing.
	 */
	public function test_an_unknown_chart_type_falls_back_to_bar() {
		$html = $this->render(
			array(
				'chartType' => 'pie-of-lies',
				'data'      => $this->sample(),
			)
		);
		$this->assertStringContainsString( '<rect class="dsgo-chart__bar"', $html );
	}

	/**
	 * The data is always available to assistive tech as a real table.
	 */
	public function test_always_emits_an_accessible_data_table() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
			)
		);
		$this->assertStringContainsString( '<table', $html );
		$this->assertStringContainsString( 'screen-reader-text', $html );
		$this->assertStringContainsString( 'Q1', $html );
	}

	/**
	 * The description becomes the table caption.
	 */
	public function test_the_label_becomes_the_table_caption() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
				'label'     => 'Quarterly sales',
			)
		);
		$this->assertStringContainsString( '<caption>Quarterly sales</caption>', $html );
	}

	/**
	 * The SVG is decorative because the table carries the data.
	 */
	public function test_svg_is_hidden_from_assistive_tech_since_the_table_carries_the_data() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
			)
		);
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
	}

	/**
	 * A label containing markup is escaped, not executed.
	 */
	public function test_escapes_a_malicious_label() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => array(
					array(
						'label' => '<script>alert(1)</script>',
						'value' => 5,
					),
				),
			)
		);
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * A non-numeric value is dropped rather than rendered.
	 */
	public function test_ignores_a_non_numeric_value() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => array(
					array(
						'label' => 'Bad',
						'value' => 'not a number',
					),
				),
			)
		);
		$this->assertStringNotContainsString( 'not a number', $html );
	}

	/**
	 * With no usable rows the block renders nothing at all.
	 */
	public function test_empty_data_renders_nothing() {
		$this->assertSame(
			'',
			trim(
				$this->render(
					array(
						'chartType' => 'bar',
						'data'      => array(),
					)
				)
			)
		);
	}

	/**
	 * A JSON array in post meta is a valid data source.
	 */
	public function test_reads_data_from_post_meta_when_the_source_is_meta() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'sales', wp_json_encode( $this->sample() ) );

		$this->go_to( get_permalink( $post_id ) );
		the_post();

		$html = $this->render(
			array(
				'chartType'  => 'bar',
				'dataSource' => 'meta',
				'metaKey'    => 'sales',
			)
		);

		$this->assertStringContainsString( 'Q1', $html );
	}

	/**
	 * Protected meta is never exposed through the chart.
	 */
	public function test_refuses_to_read_protected_meta() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_secret', wp_json_encode( $this->sample() ) );

		$this->go_to( get_permalink( $post_id ) );
		the_post();

		$html = $this->render(
			array(
				'chartType'  => 'bar',
				'dataSource' => 'meta',
				'metaKey'    => '_secret',
			)
		);

		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * Gridlines and axis ticks are drawn when the grid is on.
	 */
	public function test_grid_renders_gridlines_and_tick_labels() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
				'showGrid'  => true,
			)
		);
		$this->assertStringContainsString( 'dsgo-chart__gridline', $html );
		$this->assertStringContainsString( 'dsgo-chart__tick', $html );
	}

	/**
	 * Turning the grid off removes both the lines and the tick labels.
	 */
	public function test_grid_is_absent_when_disabled() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
				'showGrid'  => false,
			)
		);
		$this->assertStringNotContainsString( 'dsgo-chart__gridline', $html );
		$this->assertStringNotContainsString( 'dsgo-chart__tick', $html );
	}

	/**
	 * A donut has no axes, so it never draws a grid.
	 */
	public function test_donut_never_renders_a_grid() {
		$html = $this->render(
			array(
				'chartType' => 'donut',
				'data'      => $this->sample(),
				'showGrid'  => true,
			)
		);
		$this->assertStringNotContainsString( 'dsgo-chart__gridline', $html );
	}

	/**
	 * Each row gets a value label when values are shown.
	 */
	public function test_show_values_renders_one_label_per_row() {
		$html = $this->render(
			array(
				'chartType'  => 'bar',
				'data'       => $this->sample(),
				'showValues' => true,
				'showGrid'   => false,
			)
		);
		$this->assertSame( 2, substr_count( $html, '<text class="dsgo-chart__value"' ) );
		$this->assertStringContainsString( '>10</text>', $html );
	}

	/**
	 * Value labels are omitted when the toggle is off.
	 */
	public function test_show_values_can_be_turned_off() {
		$html = $this->render(
			array(
				'chartType'  => 'bar',
				'data'       => $this->sample(),
				'showValues' => false,
			)
		);
		$this->assertStringNotContainsString( 'dsgo-chart__value', $html );
	}

	/**
	 * Line charts label their points too.
	 */
	public function test_line_chart_labels_its_points() {
		$html = $this->render(
			array(
				'chartType'  => 'line',
				'data'       => $this->sample(),
				'showValues' => true,
			)
		);
		$this->assertSame( 2, substr_count( $html, '<text class="dsgo-chart__value"' ) );
	}

	/**
	 * Donut slices are labelled with their share of the total.
	 */
	public function test_donut_labels_slices_with_a_percentage() {
		$html = $this->render(
			array(
				'chartType'  => 'donut',
				'data'       => $this->sample(),
				'showValues' => true,
			)
		);
		$this->assertStringContainsString( '33.33%', $html );
	}

	/**
	 * Donut labels are drawn clear of the ring.
	 *
	 * Text sitting on a slice has to contrast with a colour the author chose,
	 * which cannot be guaranteed — outside the ring it contrasts with the page.
	 */
	public function test_donut_labels_sit_outside_the_ring() {
		$html = $this->render(
			array(
				'chartType'  => 'donut',
				'data'       => $this->sample(),
				'showValues' => true,
				'height'     => 240,
			)
		);

		preg_match_all(
			'/<text class="dsgo-chart__value" x="([-\d.]+)" y="([-\d.]+)"/',
			$html,
			$matches,
			PREG_SET_ORDER
		);

		$this->assertNotEmpty( $matches, 'No donut value labels were rendered.' );

		// Mirrors designsetgo_chart_donut(): half of min(600, 240), less the
		// 4-unit inset and the 26-unit label gutter.
		$radius = 240 / 2 - 4 - 26;
		$cx     = 300.0;
		$cy     = 120.0;

		foreach ( $matches as $match ) {
			$distance = sqrt(
				pow( (float) $match[1] - $cx, 2 ) + pow( (float) $match[2] - $cy, 2 )
			);
			$this->assertGreaterThan(
				$radius,
				$distance,
				'A donut label was drawn on top of the ring.'
			);
		}
	}

	/**
	 * The legend is emitted by default and can be turned off.
	 */
	public function test_legend_can_be_turned_off() {
		$with = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
			)
		);
		$this->assertStringContainsString( 'dsgo-chart__legend', $with );

		$without = $this->render(
			array(
				'chartType'  => 'bar',
				'data'       => $this->sample(),
				'showLegend' => false,
			)
		);
		$this->assertStringNotContainsString( 'dsgo-chart__legend', $without );
	}

	/**
	 * A donut has no axis, so its legend is the only sighted route from a
	 * slice to its category and must survive `showLegend: false`.
	 */
	public function test_donut_legend_cannot_be_turned_off() {
		$html = $this->render(
			array(
				'chartType'  => 'donut',
				'data'       => $this->sample(),
				'showLegend' => false,
			)
		);
		$this->assertStringContainsString( 'dsgo-chart__legend', $html );
		$this->assertStringContainsString( 'Q1', $html );
	}

	/**
	 * Category names live on the axis, so hiding the legend never leaves a
	 * sighted reader unable to tell which bar is which.
	 */
	public function test_category_labels_survive_a_hidden_legend() {
		foreach ( array( 'bar', 'line' ) as $type ) {
			$html = $this->render(
				array(
					'chartType'  => $type,
					'data'       => $this->sample(),
					'showLegend' => false,
				)
			);

			$this->assertStringNotContainsString( 'dsgo-chart__legend', $html );
			$this->assertMatchesRegularExpression(
				'/<text class="dsgo-chart__category"[^>]*>Q1<\/text>/',
				$html,
				"The {$type} chart lost its category labels."
			);
		}
	}

	/**
	 * A negative bar hangs below the zero line rather than being drawn upward
	 * from the axis floor, where it would read as a small positive value.
	 */
	public function test_negative_bars_hang_below_the_zero_baseline() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'showGrid'  => false,
				'data'      => array(
					array(
						'label' => 'Up',
						'value' => 10,
					),
					array(
						'label' => 'Down',
						'value' => -10,
					),
				),
			)
		);

		$this->assertStringContainsString(
			'dsgo-chart__baseline',
			$html,
			'An axis crossing zero must draw a baseline.'
		);

		preg_match_all(
			'/<line class="dsgo-chart__baseline"[^>]*y1="([\d.-]+)"/',
			$html,
			$baseline
		);
		$this->assertNotEmpty( $baseline[1] );
		$zero = (float) $baseline[1][0];

		preg_match_all(
			'/<rect class="dsgo-chart__bar"[^>]*y="([\d.-]+)" width="[\d.-]+" height="([\d.-]+)"/',
			$html,
			$bars,
			PREG_SET_ORDER
		);
		$this->assertCount( 2, $bars );

		// SVG y grows downward: the positive bar's top is above zero and its
		// foot lands on it; the negative bar starts at zero and grows down.
		$this->assertLessThan( $zero, (float) $bars[0][1] );
		$this->assertEqualsWithDelta( $zero, (float) $bars[0][1] + (float) $bars[0][2], 0.5 );
		$this->assertEqualsWithDelta( $zero, (float) $bars[1][1], 0.5 );
		$this->assertGreaterThan( $zero, (float) $bars[1][1] + (float) $bars[1][2] );

		// Equal magnitudes must produce equal bar heights.
		$this->assertEqualsWithDelta( (float) $bars[0][2], (float) $bars[1][2], 0.5 );
	}

	/**
	 * A donut cannot show a share of a negative, so such rows leave the chart,
	 * the legend, and the data table together rather than one of the three.
	 */
	public function test_donut_drops_non_positive_rows_everywhere() {
		$html = $this->render(
			array(
				'chartType' => 'donut',
				'data'      => array(
					array(
						'label' => 'Keep',
						'value' => 10,
					),
					array(
						'label' => 'Drop',
						'value' => -10,
					),
					array(
						'label' => 'Zero',
						'value' => 0,
					),
				),
			)
		);

		$this->assertSame( 1, substr_count( $html, 'dsgo-chart__slice' ) );
		$this->assertSame( 1, substr_count( $html, 'dsgo-chart__legend-item' ) );
		$this->assertStringContainsString( 'Keep', $html );
		$this->assertStringNotContainsString( 'Drop', $html );
		$this->assertStringNotContainsString( 'Zero', $html );

		// The single surviving row is the whole total.
		$this->assertStringContainsString( '100%', $html );
	}

	/**
	 * A donut with nothing positive left to draw renders nothing at all.
	 */
	public function test_donut_with_only_negative_rows_renders_nothing() {
		$html = $this->render(
			array(
				'chartType' => 'donut',
				'data'      => array(
					array(
						'label' => 'Loss',
						'value' => -10,
					),
				),
			)
		);
		$this->assertStringNotContainsString( 'dsgo-chart', $html );
	}

	/**
	 * The legend swatches describe the line's own point colours; a legend that
	 * claimed a colour mapping the chart did not honour would be worse than no
	 * legend, because it is one of the two non-colour a11y channels.
	 */
	public function test_line_points_use_their_own_palette_slot() {
		$html = $this->render(
			array(
				'chartType' => 'line',
				'data'      => $this->sample(),
				'palette'   => array( '#111111', '#222222' ),
			)
		);

		preg_match_all(
			'/<circle class="dsgo-chart__point"[^>]*fill="([^"]+)"/',
			$html,
			$points
		);
		$this->assertSame( array( '#111111', '#222222' ), $points[1] );

		preg_match_all(
			'/<span class="dsgo-chart__swatch" style="background:([^"]+)"/',
			$html,
			$swatches
		);
		$this->assertSame( $points[1], $swatches[1] );
	}

	/**
	 * Meta rows are refused for a post the visitor cannot read, matching the
	 * gates every other binding path in the plugin applies.
	 */
	public function test_meta_rows_are_refused_for_a_private_post() {
		$post_id = self::factory()->post->create(
			array( 'post_status' => 'private' )
		);
		update_post_meta( $post_id, 'dsgo_chart_data', wp_json_encode( $this->sample() ) );

		$block = new WP_Block(
			array(
				'blockName' => 'designsetgo/chart',
				'attrs'     => array(),
			),
			array( 'postId' => $post_id )
		);

		wp_set_current_user( 0 );
		$this->assertSame(
			array(),
			designsetgo_chart_meta_rows( 'dsgo_chart_data', $block ),
			'Private post meta leaked to an anonymous visitor.'
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertCount(
			2,
			designsetgo_chart_meta_rows( 'dsgo_chart_data', $block ),
			'An editor with read access should still see the data.'
		);
		wp_set_current_user( 0 );
	}

	/**
	 * Meta rows are refused while a post's password is outstanding.
	 */
	public function test_meta_rows_are_refused_for_a_password_protected_post() {
		$post_id = self::factory()->post->create(
			array( 'post_password' => 'secret' )
		);
		update_post_meta( $post_id, 'dsgo_chart_data', wp_json_encode( $this->sample() ) );

		$block = new WP_Block(
			array(
				'blockName' => 'designsetgo/chart',
				'attrs'     => array(),
			),
			array( 'postId' => $post_id )
		);

		$this->assertSame( array(), designsetgo_chart_meta_rows( 'dsgo_chart_data', $block ) );
	}

	/**
	 * A non-scalar label in stored JSON is dropped, not cast — casting an array
	 * to string raises a notice that would print into the page under WP_DEBUG.
	 */
	public function test_a_non_scalar_label_does_not_raise_a_notice() {
		$rows = designsetgo_chart_rows(
			array(
				'data' => array(
					array(
						'label' => array( 'nested' ),
						'value' => 5,
					),
				),
			)
		);

		$this->assertSame( '', $rows[0]['label'] );
		$this->assertSame( 5.0, $rows[0]['value'] );
	}

	/**
	 * A colour cannot append declarations of its own to the legend swatch's
	 * style attribute. `esc_attr()` does not stop this — the payload is valid
	 * markup — so the character allowlist is what has to hold.
	 */
	public function test_palette_rejects_a_css_declaration_injection() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
				'palette'   => array( 'red;position:fixed;inset:0;z-index:99999' ),
			)
		);

		$this->assertStringNotContainsString( 'position:fixed', $html );
		$this->assertStringNotContainsString( 'z-index', $html );
	}

	/**
	 * The allowlist refuses the shapes an encoding trick would arrive in, while
	 * every colour form the picker can actually produce still survives it.
	 */
	public function test_safe_color_allowlist_boundaries() {
		$rejected = array(
			'red;color:blue',
			'red}body{display:none',
			'\0075rl(#x)',
			'url(#evil)',
			'expression(alert(1))',
			'javascript:alert(1)',
			'"onload="alert(1)',
			'@import "x"',
		);

		foreach ( $rejected as $value ) {
			$this->assertSame(
				'',
				designsetgo_chart_safe_color( $value ),
				"Accepted an unsafe colour: {$value}"
			);
		}

		$accepted = array(
			'#ff8800',
			'rgb(255, 136, 0)',
			'rgba(255, 136, 0, 0.5)',
			'hsl(210 50% 40% / 0.5)',
			'rebeccapurple',
			'var(--wp--preset--color--primary)',
			'var(--wp--preset--color--primary, #3858e9)',
			'color-mix(in srgb, red 50%, blue)',
		);

		foreach ( $accepted as $value ) {
			$this->assertSame(
				$value,
				designsetgo_chart_safe_color( $value ),
				"Rejected a legitimate colour: {$value}"
			);
		}
	}

	/**
	 * Rows are capped, and the cap is filterable.
	 */
	public function test_rows_are_capped() {
		$rows = array();

		for ( $i = 0; $i < 500; $i++ ) {
			$rows[] = array(
				'label' => 'R' . $i,
				'value' => $i,
			);
		}

		$this->assertCount( 200, designsetgo_chart_rows( array( 'data' => $rows ) ) );

		$raise = static function () {
			return 5;
		};
		add_filter( 'designsetgo_chart_max_rows', $raise );
		$this->assertCount( 5, designsetgo_chart_rows( array( 'data' => $rows ) ) );
		remove_filter( 'designsetgo_chart_max_rows', $raise );
	}

	/**
	 * A truncated chart says so in the data table — the table is the only place
	 * the full count could have been checked, and a silent cap is
	 * indistinguishable from complete data.
	 */
	public function test_a_truncated_chart_discloses_the_shortfall() {
		$rows = array();

		for ( $i = 0; $i < 250; $i++ ) {
			$rows[] = array(
				'label' => 'R' . $i,
				'value' => $i + 1,
			);
		}

		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $rows,
			)
		);
		$this->assertStringContainsString( 'Showing the first 200 of 250 rows.', $html );
	}

	/**
	 * Rows dropped for being unusable are not reported as a truncation — a
	 * donut legitimately discards negatives and must not cry wolf about it.
	 */
	public function test_dropped_rows_are_not_reported_as_truncation() {
		$html = $this->render(
			array(
				'chartType' => 'donut',
				'data'      => array(
					array(
						'label' => 'Keep',
						'value' => 10,
					),
					array(
						'label' => 'Drop',
						'value' => -1,
					),
				),
			)
		);
		$this->assertStringNotContainsString( 'Showing the first', $html );
	}

	/**
	 * Dense axes drop labels to stay readable, and the legend comes back when
	 * they do, so the full set of category names never leaves the page.
	 */
	public function test_a_dense_axis_thins_labels_and_restores_the_legend() {
		$rows = array();

		for ( $i = 0; $i < 40; $i++ ) {
			$rows[] = array(
				'label' => 'September ' . $i,
				'value' => $i + 1,
			);
		}

		$html = $this->render(
			array(
				'chartType'  => 'bar',
				'data'       => $rows,
				'showLegend' => false,
			)
		);

		$drawn = substr_count( $html, 'dsgo-chart__category' );
		$this->assertGreaterThan( 0, $drawn, 'The axis lost every label.' );
		$this->assertLessThan( 40, $drawn, 'Dense labels were not thinned.' );

		$this->assertStringContainsString(
			'dsgo-chart__legend',
			$html,
			'A thinned axis must bring the legend back.'
		);
		$this->assertSame( 40, substr_count( $html, 'dsgo-chart__legend-item' ) );
	}

	/**
	 * A sparse axis still honours `showLegend: false` — the thinning rule must
	 * not quietly pin the legend on for every chart.
	 */
	public function test_a_sparse_axis_still_honours_a_hidden_legend() {
		$html = $this->render(
			array(
				'chartType'  => 'bar',
				'data'       => $this->sample(),
				'showLegend' => false,
			)
		);
		$this->assertStringNotContainsString( 'dsgo-chart__legend', $html );
	}

	/**
	 * Near-zero negatives format as "0", never "-0".
	 *
	 * Pinned rather than guarded: `number_format()` has not returned "-0.00"
	 * since before the plugin's PHP floor, verified on 7.4 and 8.3, so this
	 * records the reliance instead of adding an unreachable branch.
	 */
	public function test_near_zero_negatives_never_format_as_minus_zero() {
		foreach ( array( -0.001, -0.004, -0.0, 0.0 ) as $value ) {
			$this->assertSame(
				'0',
				designsetgo_chart_format_value( $value ),
				"Formatted {$value} with a signed zero."
			);
		}

		// A value that genuinely rounds away from zero keeps its sign.
		$this->assertSame( '-0.01', designsetgo_chart_format_value( -0.006 ) );
	}

	/**
	 * A caller-supplied palette colour is sanitised before it reaches the markup.
	 */
	public function test_palette_rejects_a_javascript_url() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
				'palette'   => array( 'javascript:alert(1)' ),
			)
		);
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	/**
	 * A theme preset chosen in the colour picker renders as its CSS variable.
	 */
	public function test_palette_converts_a_preset_token_to_a_css_variable() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
				'palette'   => array( 'var:preset|color|accent-3' ),
			)
		);
		$this->assertStringContainsString(
			'var(--wp--preset--color--accent-3)',
			$html
		);
		$this->assertStringNotContainsString( 'var:preset', $html );
	}

	/**
	 * A custom hex colour is passed through untouched.
	 */
	public function test_palette_passes_through_a_custom_hex() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
				'palette'   => array( '#ff8800' ),
			)
		);
		$this->assertStringContainsString( '#ff8800', $html );
	}

	/**
	 * Setting one series colour leaves the other series on their defaults.
	 */
	public function test_palette_is_positional_not_cycled() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
				'palette'   => array( '#ff8800' ),
			)
		);
		$this->assertSame( 1, substr_count( $html, 'fill="#ff8800"' ) );
		$this->assertStringContainsString( 'var(--wp--preset--color--', $html );
	}

	/**
	 * A gap in the palette falls back to the default for that series only.
	 */
	public function test_palette_tolerates_an_empty_slot() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
				'palette'   => array( '', '#ff8800' ),
			)
		);
		$this->assertStringContainsString( 'fill="#ff8800"', $html );
		$this->assertStringContainsString( 'var(--wp--preset--color--', $html );
	}

	/**
	 * The height attribute is clamped to a sane viewport range.
	 */
	public function test_height_is_clamped() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
				'height'    => 100000,
			)
		);
		$this->assertStringContainsString( 'viewBox="0 0 600 800"', $html );
	}
}
