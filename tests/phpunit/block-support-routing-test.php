<?php
/**
 * Tests for the block-support routing helpers.
 *
 * @package DesignSetGo
 */

/**
 * @group block-support-routing
 */
class Block_Support_Routing_Test extends WP_UnitTestCase {

	public function test_split_style_groups_moves_only_requested_paths() {
		$style = array(
			'color'   => array( 'background' => '#f00' ),
			'border'  => array( 'radius' => '999px' ),
			'spacing' => array(
				'padding' => array( 'top' => '10px' ),
				'margin'  => array( 'top' => '20px' ),
			),
		);

		$split = designsetgo_split_style_groups(
			$style,
			array( 'color', 'border', 'spacing.padding' )
		);

		$this->assertStringContainsString( 'background-color:#f00', $split['inner'] );
		$this->assertStringContainsString( 'border-radius:999px', $split['inner'] );
		$this->assertStringContainsString( 'padding-top:10px', $split['inner'] );

		$this->assertStringContainsString( 'margin-top:20px', $split['wrapper'] );
		$this->assertStringNotContainsString( 'background-color', $split['wrapper'] );
		$this->assertStringNotContainsString( 'padding-top', $split['wrapper'] );
	}

	public function test_route_visual_supports_moves_classes_and_styles_to_inner() {
		$html = '<div class="wp-block-x dsgo-x has-accent-background-color has-background" style="margin-top:20px;background-color:#f00">'
			. '<span class="dsgo-x__box"></span></div>';

		$out = designsetgo_route_visual_supports(
			$html,
			array( 'style' => array( 'color' => array( 'background' => '#f00' ) ) ),
			'dsgo-x__box',
			array( 'color' )
		);

		// Visual class left the wrapper and landed on the inner element.
		$this->assertStringNotContainsString(
			'has-accent-background-color has-background" style',
			$out
		);
		$this->assertMatchesRegularExpression(
			'/<span class="[^"]*dsgo-x__box[^"]*has-accent-background-color/',
			$out
		);
		$this->assertMatchesRegularExpression(
			'/<span[^>]*style="[^"]*background-color:#f00/',
			$out
		);
	}

	public function test_route_visual_supports_is_a_noop_without_visual_styles() {
		$html = '<div class="wp-block-x dsgo-x"><span class="dsgo-x__box"></span></div>';
		$out  = designsetgo_route_visual_supports( $html, array(), 'dsgo-x__box', array( 'color' ) );

		$this->assertStringNotContainsString( 'style=', $out );
	}
}
