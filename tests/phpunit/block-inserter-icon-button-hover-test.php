<?php
/**
 * Icon buttons inserted with hover colours must carry the custom properties save() writes.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * @group abilities
 */
class Block_Inserter_Icon_Button_Hover_Test extends WP_UnitTestCase {

	public function test_hover_colours_are_serialized_as_button_custom_properties() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/icon-button',
			array(
				'text'                 => 'Get a Quote',
				'url'                  => '/contact/',
				'hoverBackgroundColor' => 'var:preset|color|contrast',
				'hoverTextColor'       => '#112233',
			),
			array()
		);
		$block  = parse_blocks( $markup )[0];
		$this->assertStringContainsString( '--dsgo-button-hover-bg:var(--wp--preset--color--contrast)', $block['innerHTML'] );
		$this->assertStringContainsString( '--dsgo-button-hover-color:#112233', $block['innerHTML'] );
	}

	public function test_buttons_without_hover_colours_write_no_hover_properties() {
		$markup = Block_Inserter::build_block_markup( 'designsetgo/icon-button', array( 'text' => 'Go', 'url' => '#go' ), array() );
		$this->assertStringNotContainsString( '--dsgo-button-hover', parse_blocks( $markup )[0]['innerHTML'] );
	}
}
