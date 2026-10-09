<?php
/** Layout composition, validation and generation contract regressions.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Layout_Support;
use DesignSetGo\Abilities\Block_Inserter;

/** Shared layout behavior. */
class Layout_Support_Test extends WP_UnitTestCase {

	/** The production class must be loaded by the plugin. */
	public function set_up(): void {
		parent::set_up();
		$this->assertTrue( class_exists( Layout_Support::class ), 'Layout support must be loaded.' );
	}

	/** Empty settings preserve legacy serialized markup. */
	public function test_empty_overrides_preserve_old_markup(): void {
		$this->assertSame( '', Layout_Support::class_name( 'designsetgo/grid', array() ) );
		$this->assertSame( '', Layout_Support::compile_css( 'designsetgo/grid', array() ) );
		$old = parse_blocks( Block_Inserter::build_block_markup( 'designsetgo/grid', array() ) )[0];
		$new = parse_blocks( Block_Inserter::build_block_markup( 'designsetgo/grid', array( 'dsgoLayout' => array() ) ) )[0];
		// An explicitly supplied empty attribute may remain in the comment;
		// compatibility requires the saved HTML to remain byte-identical.
		$this->assertSame( $old['innerHTML'], $new['innerHTML'] );
	}

	/** CSS routes properties to their actual layout host and cascades devices. */
	public function test_container_layout_routes_root_and_inner_properties(): void {
		$layout = array(
			'desktop' => array( 'width' => 'clamp(20rem, 60vw, 70rem)' ),
			'tablet'  => array(
				'direction' => 'column',
				'gap' => '24px',
				'paddingTop' => '8px',
			),
			'mobile'  => array(
				'gap' => '12px',
				'contentWidth' => '90%',
			),
		);
		$css = Layout_Support::compile_css( 'designsetgo/row', $layout );
		$this->assertStringContainsString( 'width:clamp(20rem, 60vw, 70rem)!important', $css );
		$this->assertStringContainsString( '> .dsgo-flex__inner{flex-direction:column!important;gap:24px!important', $css );
		$this->assertStringContainsString( 'padding-top:8px!important', $css );
		$this->assertStringContainsString( '@media (max-width:1024px)', $css );
		$this->assertStringContainsString( '@media (max-width:767px)', $css );
		$this->assertStringContainsString( 'max-width:90%!important', $css );
	}

	/** Key ordering cannot change the saved selector class. */
	public function test_hash_ignores_input_key_order(): void {
		$a = array(
			'mobile' => array(
				'width' => '100%',
				'height' => 'auto',
			),
		);
		$b = array(
			'mobile' => array(
				'height' => 'auto',
				'width' => '100%',
			),
		);
		$this->assertSame( Layout_Support::class_name( 'designsetgo/section', $a ), Layout_Support::class_name( 'designsetgo/section', $b ) );
	}

	/** Named areas and explicit placement can compose asymmetrical layouts. */
	public function test_grid_areas_and_child_placement_are_supported(): void {
		$this->assertIsArray(
			Layout_Support::sanitize(
				'designsetgo/grid',
				array(
					'desktop' => array(
						'gridTemplateAreas' => '"hero hero aside" "copy image aside"',
						'gridTemplateRows' => 'auto minmax(10rem, 1fr)',
					),
				)
			)
		);
		$css = Layout_Support::compile_css(
			'core/group',
			array(
				'desktop' => array(
					'gridColumn' => '2 / span 2',
					'gridRow' => '1 / 3',
					'position' => 'relative',
					'top' => '-24px',
					'zIndex' => 3,
				),
			)
		);
		$this->assertStringContainsString( 'grid-column:2 / span 2!important', $css );
		$this->assertStringContainsString( 'top:-24px!important', $css );
	}

	/** Unsafe and inapplicable settings are refused rather than silently omitted. */
	public function test_invalid_settings_are_refused(): void {
		$cases = array(
			array( 'designsetgo/row', array( 'phone' => array( 'width' => '10px' ) ) ),
			array( 'designsetgo/row', array( 'mobile' => array( 'unknown' => '10px' ) ) ),
			array( 'designsetgo/row', array( 'mobile' => array( 'width' => '1px;color:red' ) ) ),
			array( 'designsetgo/row', array( 'mobile' => array( 'width' => 'url(https://example.com)' ) ) ),
			array( 'designsetgo/grid', array( 'mobile' => array( 'direction' => 'column' ) ) ),
			array( 'core/paragraph', array( 'mobile' => array( 'gap' => '10px' ) ) ),
			array( 'designsetgo/grid', array( 'desktop' => array( 'gridTemplateAreas' => '"a b" "b a"' ) ) ),
			array( 'designsetgo/grid', array( 'desktop' => array( 'gridTemplateAreas' => '"a a" "b"' ) ) ),
		);
		foreach ( $cases as $case ) {
			$this->assertWPError( Layout_Support::sanitize( $case[0], $case[1] ) );
		}
	}

	/** Syntactically safe but invalid CSS must also be refused. */
	public function test_invalid_css_grammar_is_refused(): void {
		$cases = array(
			array( 'width', 'var(1px)' ),
			array( 'width', 'calc(1px +)' ),
			array( 'width', 'none' ),
			array( 'width', '20' ),
			array( 'width', "\xC2\xA0" . '10px' ),
			array( 'gap', '-2px' ),
			array( 'paddingTop', '-4px' ),
			array( 'gridTemplateRows', 'repeat(0, 1fr)' ),
			array( 'gridTemplateRows', '1fr + 2fr' ),
			array( 'flexGrow', 0.000001 ),
		);
		foreach ( $cases as $case ) {
			$this->assertWPError( Layout_Support::sanitize( 'designsetgo/grid', array( 'desktop' => array( $case[0] => $case[1] ) ) ), wp_json_encode( $case ) );
		}
	}

	/** Generation must screen nested layout errors before building markup. */
	public function test_inserter_refuses_unsafe_layout_values(): void {
		$errors = Block_Inserter::find_invalid_attribute_values(
			array(
				array(
					'name'       => 'designsetgo/row',
					'attributes' => array( 'dsgoLayout' => array( 'mobile' => array( 'width' => '10px;display:none' ) ) ),
					'innerBlocks' => array(),
				),
			)
		);
		$this->assertNotEmpty( $errors );
	}
}
