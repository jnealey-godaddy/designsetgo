<?php
/**
 * Form field wrapper width must let N equal columns share one row.
 *
 * The fields container is a wrapping flex row with a gap between fields. The
 * old width style subtracted half a gap from every field, which only fits two
 * columns: three 33% fields overflowed and the third wrapped to a new row.
 * Expectations are shared with the editor preview's Jest test through
 * tests/fixtures/form-field-width-cases.json.
 *
 * @package DesignSetGo
 */

/**
 * @group forms
 */
class Form_Field_Width_Style_Test extends WP_UnitTestCase {

	/**
	 * Shared PHP/JS expectations.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function width_cases() {
		$fixture = json_decode(
			file_get_contents( dirname( __DIR__ ) . '/fixtures/form-field-width-cases.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			true
		);

		$cases = array();
		foreach ( $fixture['cases'] as $case ) {
			$cases[ 'width "' . $case['width'] . '"' ] = array( $case['width'], $case['style'] );
		}
		return $cases;
	}

	/**
	 * @dataProvider width_cases
	 *
	 * @param string $width    fieldWidth attribute.
	 * @param string $expected Expected inline style.
	 */
	public function test_width_style_matches_shared_expectation( $width, $expected ) {
		$this->assertSame( $expected, designsetgo_form_field_width_style( $width ) );
	}

	/**
	 * Two halves, three thirds and four quarters each fill exactly one row.
	 */
	public function test_three_thirds_and_four_quarters_fit_one_row() {
		$columns = array(
			'50' => 2,
			'33' => 3,
			'25' => 4,
		);
		foreach ( $columns as $width => $count ) {
			$style = designsetgo_form_field_width_style( (string) $width );
			$this->assertSame(
				1,
				preg_match( '/^flex-basis:calc\(([\d.]+)% - var\(--dsgo-form-field-spacing, 1\.5rem\) \* ([\d.]+)\);/', $style, $m ),
				"Unexpected style for {$width}: {$style}"
			);

			$percent    = (float) $m[1];
			$gap_factor = (float) $m[2];

			// N * P% of the row, and the gap each field gives up covers the N-1 gaps.
			$this->assertEqualsWithDelta( 100, $count * $percent, 0.001, "{$width}% percent sum" );
			$this->assertLessThanOrEqual( 100, $count * $percent, "{$width}% percent never over-fills" );
			$this->assertEqualsWithDelta( $count - 1, $count * $gap_factor, 0.001, "{$width}% gap sum" );
			$this->assertGreaterThanOrEqual( $count - 1, $count * $gap_factor, "{$width}% gaps never over-fill" );
		}
	}

	/**
	 * Non-numeric widths fall back to full width; ints match strings.
	 */
	public function test_non_numeric_input_is_full_width() {
		$this->assertSame( 'flex-basis:100%;max-width:100%', designsetgo_form_field_width_style( 'abc' ) );
		$this->assertSame( designsetgo_form_field_width_style( '50' ), designsetgo_form_field_width_style( 50 ) );
	}
}
