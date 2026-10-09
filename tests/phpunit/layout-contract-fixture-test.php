<?php
/** Shared PHP/JavaScript acceptance and selector fixtures.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Layout_Support;

/** Both runtimes must make the same typed layout decisions. */
class Layout_Contract_Fixture_Test extends WP_UnitTestCase {
	/** Validated fixture decisions and deterministic CSS/classes. */
	public function test_shared_layout_values(): void {
		$path  = dirname( __DIR__ ) . '/fixtures/layout-values.json';
		$cases = json_decode( file_get_contents( $path ), true );
		foreach ( $cases as $index => &$case ) {
			$result = Layout_Support::sanitize( $case['name'], $case['layout'] );
			$this->assertSame( $case['valid'], ! is_wp_error( $result ), 'Case ' . $index . ': ' . wp_json_encode( $case['layout'] ) );
			if ( ! $case['valid'] ) {
				continue;
			}
			$class = Layout_Support::class_name( $case['name'], $case['layout'] );
			$css   = Layout_Support::compile_css( $case['name'], $case['layout'] );
			if ( getenv( 'DSGO_UPDATE_FIXTURES' ) ) {
				$case['className'] = $class;
				$case['css']       = $css;
			} else {
				$this->assertSame( $case['className'], $class );
				$this->assertSame( $case['css'], $css );
			}
		}
		unset( $case );
		if ( getenv( 'DSGO_UPDATE_FIXTURES' ) ) {
			file_put_contents( $path, wp_json_encode( $cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
		}
	}
}
