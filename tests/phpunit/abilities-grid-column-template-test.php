<?php
/**
 * The Grid block's custom column template reaches generated markup.
 *
 * A free-form `columnTemplate` (a `grid-template-columns` value) gives an
 * asymmetric desktop layout that the column count alone cannot express. The
 * serializer must write it inline exactly as save() does, and refuse a value
 * that could escape the style declaration.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * Tests for the grid column template.
 */
class Abilities_Grid_Column_Template_Test extends WP_UnitTestCase {

	/**
	 * Style attribute of the grid's inner element.
	 *
	 * @param string $markup Serialized block markup.
	 * @return string
	 */
	private function inner_style( string $markup ): string {
		$processor = new WP_HTML_Tag_Processor( $markup );
		$processor->next_tag( array( 'class_name' => 'dsgo-grid__inner' ) );
		return (string) $processor->get_attribute( 'style' );
	}

	/**
	 * A custom template replaces the repeated column count on desktop.
	 */
	public function test_custom_template_is_written_as_the_desktop_track_list(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/grid',
			array(
				'desktopColumns' => 2,
				'columnTemplate' => 'minmax(0, .7fr) minmax(0, 1.3fr)',
			)
		);

		$style = $this->inner_style( $markup );
		$this->assertStringContainsString( 'grid-template-columns:minmax(0, .7fr) minmax(0, 1.3fr)', $style );
		$this->assertStringNotContainsString( 'repeat(2, 1fr)', $style );
		$this->assertStringContainsString( 'dsgo-grid-cols-2', $markup, 'The column-count classes still drive tablet and mobile.' );
	}

	/**
	 * The template wins over a column min width, matching grid-columns.js.
	 */
	public function test_custom_template_wins_over_column_min_width(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/grid',
			array(
				'columnMinWidth' => '16rem',
				'columnTemplate' => '2fr 1fr',
			)
		);

		$this->assertStringContainsString( 'grid-template-columns:2fr 1fr', $this->inner_style( $markup ) );
		$this->assertStringNotContainsString( 'auto-fill', $this->inner_style( $markup ) );
	}

	/**
	 * A blank template leaves the existing behaviour untouched.
	 */
	public function test_blank_template_keeps_the_repeated_column_count(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/grid',
			array(
				'desktopColumns' => 4,
				'columnTemplate' => '  ',
			)
		);

		$this->assertStringContainsString( 'grid-template-columns:repeat(4, 1fr)', $this->inner_style( $markup ) );
	}

	/**
	 * A value that could break out of the declaration is refused up front.
	 *
	 * @dataProvider unsafe_templates
	 *
	 * @param string $template The template under test.
	 */
	public function test_unsafe_template_is_reported( string $template ): void {
		$problems = Block_Inserter::find_invalid_attribute_values(
			array(
				array(
					'name'       => 'designsetgo/grid',
					'attributes' => array( 'columnTemplate' => $template ),
				),
			)
		);

		$this->assertCount( 1, $problems );
		$this->assertSame( 'designsetgo/grid', $problems[0]['block'] );
		$this->assertStringContainsString( 'columnTemplate', $problems[0]['reason'] );
	}

	/**
	 * Templates that must be refused.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function unsafe_templates(): array {
		return array(
			'declaration break' => array( '2fr 1fr;color:red' ),
			'block close'       => array( '2fr 1fr}' ),
			'markup'            => array( '<b>2fr</b>' ),
			'url function'      => array( 'url(https://example.com/x)' ),
		);
	}

	/**
	 * Ordinary track lists pass validation.
	 */
	public function test_ordinary_template_is_accepted(): void {
		$problems = Block_Inserter::find_invalid_attribute_values(
			array(
				array(
					'name'       => 'designsetgo/grid',
					'attributes' => array( 'columnTemplate' => 'repeat(2, minmax(0, 1fr)) 20rem' ),
				),
			)
		);

		$this->assertSame( array(), $problems );
	}
}
