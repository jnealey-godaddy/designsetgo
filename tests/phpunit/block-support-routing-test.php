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
			array(
				'backgroundColor' => 'accent',
				'style'           => array(
					'color'   => array( 'background' => '#f00' ),
					'spacing' => array( 'margin' => array( 'top' => '20px' ) ),
				),
			),
			'dsgo-x__box',
			array( 'color' )
		);

		$processor = new WP_HTML_Tag_Processor( $out );
		$processor->next_tag(); // Wrapper <div>.
		$this->assertFalse( $processor->has_class( 'has-accent-background-color' ) );
		$this->assertFalse( $processor->has_class( 'has-background' ) );
		$this->assertStringContainsString( 'margin-top:20px', (string) $processor->get_attribute( 'style' ) );
		$this->assertStringNotContainsString( 'background-color', (string) $processor->get_attribute( 'style' ) );

		while ( $processor->next_tag() && ! $processor->has_class( 'dsgo-x__box' ) ) {
			continue;
		}
		$this->assertTrue( $processor->has_class( 'has-accent-background-color' ) );
		$this->assertTrue( $processor->has_class( 'has-background' ) );
		$this->assertStringContainsString( 'background-color:#f00', (string) $processor->get_attribute( 'style' ) );
	}

	public function test_route_visual_supports_is_a_noop_without_visual_styles() {
		$html = '<div class="wp-block-x dsgo-x"><span class="dsgo-x__box"></span></div>';
		$out  = designsetgo_route_visual_supports( $html, array(), 'dsgo-x__box', array( 'color' ) );

		$this->assertStringNotContainsString( 'style=', $out );
	}

	public function test_route_visual_supports_moves_preset_gradient() {
		$html = '<div class="wp-block-x dsgo-x has-vivid-cyan-blue-gradient-background has-background">'
			. '<span class="dsgo-x__box"></span></div>';

		$out = designsetgo_route_visual_supports(
			$html,
			array( 'gradient' => 'vivid-cyan-blue' ),
			'dsgo-x__box',
			array( 'color' )
		);

		$processor = new WP_HTML_Tag_Processor( $out );
		$processor->next_tag(); // Wrapper <div>.
		$this->assertFalse( $processor->has_class( 'has-vivid-cyan-blue-gradient-background' ) );
		$this->assertFalse( $processor->has_class( 'has-background' ) );

		while ( $processor->next_tag() && ! $processor->has_class( 'dsgo-x__box' ) ) {
			continue;
		}
		$this->assertTrue( $processor->has_class( 'has-vivid-cyan-blue-gradient-background' ) );
		$this->assertTrue( $processor->has_class( 'has-background' ) );
	}

	public function test_route_visual_supports_moves_preset_background_and_text_color() {
		$html = '<div class="wp-block-x dsgo-x has-accent-background-color has-background has-base-color has-text-color">'
			. '<span class="dsgo-x__box"></span></div>';

		$out = designsetgo_route_visual_supports(
			$html,
			array(
				'backgroundColor' => 'accent',
				'textColor'       => 'base',
			),
			'dsgo-x__box',
			array( 'color' )
		);

		$processor = new WP_HTML_Tag_Processor( $out );
		$processor->next_tag(); // Wrapper <div>.
		$this->assertFalse( $processor->has_class( 'has-accent-background-color' ) );
		$this->assertFalse( $processor->has_class( 'has-background' ) );
		$this->assertFalse( $processor->has_class( 'has-base-color' ) );
		$this->assertFalse( $processor->has_class( 'has-text-color' ) );

		while ( $processor->next_tag() && ! $processor->has_class( 'dsgo-x__box' ) ) {
			continue;
		}
		$this->assertTrue( $processor->has_class( 'has-accent-background-color' ) );
		$this->assertTrue( $processor->has_class( 'has-background' ) );
		$this->assertTrue( $processor->has_class( 'has-base-color' ) );
		$this->assertTrue( $processor->has_class( 'has-text-color' ) );
	}

	public function test_route_visual_supports_leaves_font_size_class_on_wrapper_when_typography_not_requested() {
		$html = '<div class="wp-block-x dsgo-x has-large-font-size has-accent-background-color has-background">'
			. '<span class="dsgo-x__box"></span></div>';

		$out = designsetgo_route_visual_supports(
			$html,
			array(
				'fontSize'        => 'large',
				'backgroundColor' => 'accent',
			),
			'dsgo-x__box',
			array( 'color' )
		);

		$processor = new WP_HTML_Tag_Processor( $out );
		$processor->next_tag(); // Wrapper <div>.
		$this->assertTrue( $processor->has_class( 'has-large-font-size' ) );
		$this->assertFalse( $processor->has_class( 'has-accent-background-color' ) );

		while ( $processor->next_tag() && ! $processor->has_class( 'dsgo-x__box' ) ) {
			continue;
		}
		$this->assertFalse( $processor->has_class( 'has-large-font-size' ) );
		$this->assertTrue( $processor->has_class( 'has-accent-background-color' ) );
	}

	public function test_route_visual_supports_moves_preset_border_color() {
		$html = '<div class="wp-block-x dsgo-x has-accent-border-color has-border-color">'
			. '<span class="dsgo-x__box"></span></div>';

		$out = designsetgo_route_visual_supports(
			$html,
			array( 'borderColor' => 'accent' ),
			'dsgo-x__box',
			array( 'border' )
		);

		$processor = new WP_HTML_Tag_Processor( $out );
		$processor->next_tag(); // Wrapper <div>.
		$this->assertFalse( $processor->has_class( 'has-accent-border-color' ) );
		$this->assertFalse( $processor->has_class( 'has-border-color' ) );

		while ( $processor->next_tag() && ! $processor->has_class( 'dsgo-x__box' ) ) {
			continue;
		}
		$this->assertTrue( $processor->has_class( 'has-accent-border-color' ) );
		$this->assertTrue( $processor->has_class( 'has-border-color' ) );
	}
}
