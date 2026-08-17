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
