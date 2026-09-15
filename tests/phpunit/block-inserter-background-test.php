<?php
/**
 * Native backgrounds belong in block attributes; Core renders their CSS later.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

/** Verifies saved and rendered background ownership. */
class Block_Inserter_Background_Test extends WP_UnitTestCase {

	/** Background attributes survive while render-only declarations stay out of save HTML. */
	public function test_native_background_is_not_baked_into_saved_section_html() {
		$background = array(
			'backgroundImage'    => array(
				'url'    => 'https://example.com/hero.jpg',
				'source' => 'file',
			),
			'backgroundSize'     => 'cover',
			'backgroundPosition' => 'center center',
			'backgroundRepeat'   => 'no-repeat',
		);
		$markup     = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'overlayColor' => 'rgba(0,0,0,0.475)',
				'style'        => array(
					'background' => $background,
					'dimensions' => array( 'minHeight' => '80vh' ),
					'spacing'    => array( 'padding' => array( 'top' => '40px' ) ),
				),
			),
			array()
		);
		$block      = parse_blocks( $markup )[0];
		$this->assertSame( $background, $block['attrs']['style']['background'] );
		$this->assertStringNotContainsString( 'background-image:', $block['innerHTML'] );
		$this->assertStringNotContainsString( 'background-size:', $block['innerHTML'] );
		$this->assertStringNotContainsString( 'background-position:', $block['innerHTML'] );
		$this->assertStringNotContainsString( 'background-repeat:', $block['innerHTML'] );
		$this->assertStringContainsString( 'min-height:80vh', $block['innerHTML'] );
		$this->assertStringContainsString( 'padding-top:40px', $block['innerHTML'] );
		$this->assertStringContainsString( '--dsgo-overlay-color:rgba(0,0,0,0.475)', $block['innerHTML'] );
		$this->assertStringContainsString( 'background-image:', render_block( $block ) );
	}
}
