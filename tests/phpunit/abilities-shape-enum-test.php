<?php
/**
 * The shape-divider enum must match the JavaScript shape library.
 *
 * Configure_Shape_Divider's list of shapes used to be typed out by hand next to
 * a comment saying it came from shape-dividers.js. The two agreed, and nothing
 * made them keep agreeing: adding a shape to the library would have left this
 * ability rejecting it as out-of-enum — valid input refused before the callback
 * ever runs, which is the most common way an abilities schema drifts from the
 * thing it describes.
 *
 * The list is now generated into includes/abilities/generated/. This test does
 * NOT read that generated file for its expectation: it parses the JavaScript
 * source directly, so a generator that silently produced the wrong thing, or a
 * committed file left stale, still fails here rather than passing by agreeing
 * with itself.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Configurators\Configure_Shape_Divider;

/**
 * Shape enum parity test.
 */
class Abilities_Shape_Enum_Test extends WP_UnitTestCase {

	/**
	 * Shape names as declared in the JavaScript library.
	 *
	 * @return array<int, string>
	 */
	private function javascript_shapes(): array {
		$path = dirname( __DIR__, 2 ) . '/src/blocks/section/utils/shape-dividers.js';

		$this->assertFileExists( $path, 'The shape library moved; update this test and the generator.' );

		$source = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Reading a source file in a test.
		$start  = strpos( $source, 'export const SHAPE_DIVIDERS' );

		$this->assertNotFalse( $start, 'SHAPE_DIVIDERS not found in the shape library.' );

		preg_match_all( "/^\t'?([a-z][a-z0-9-]*)'?:\s/m", substr( $source, (int) $start ), $matches );

		return $matches[1];
	}

	/**
	 * The ability accepts exactly the shapes the library defines.
	 */
	public function test_enum_matches_the_javascript_library(): void {
		$javascript = $this->javascript_shapes();
		$php        = Configure_Shape_Divider::valid_shapes();

		$this->assertNotEmpty( $javascript, 'Parsed no shapes from the JavaScript library.' );

		sort( $javascript );
		sort( $php );

		$this->assertSame(
			$javascript,
			$php,
			"The shape enum has drifted from src/blocks/section/utils/shape-dividers.js.\n"
				. 'Regenerate with: npm run generate:shape-enum'
		);
	}

	/**
	 * The generated file exists and is a plain list.
	 *
	 * An empty or missing file would leave the ability's enum empty, and an
	 * ability whose enum is empty accepts nothing — a silent failure rather than
	 * a loud one, which is exactly why this is committed rather than read from
	 * build/ at runtime.
	 */
	public function test_generated_file_is_a_usable_list(): void {
		$shapes = Configure_Shape_Divider::valid_shapes();

		$this->assertNotEmpty( $shapes, 'The generated shape list is empty; run npm run generate:shape-enum.' );
		$this->assertSame( array_values( $shapes ), $shapes, 'The generated list must be a plain ordered array.' );

		foreach ( $shapes as $shape ) {
			$this->assertIsString( $shape );
			$this->assertMatchesRegularExpression( '/^[a-z][a-z0-9-]*$/', $shape );
		}
	}
}
