<?php
/**
 * Tests for the per-block-type animation render_block injector.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Settings;
use DesignSetGo\Animation_Defaults_Injector;

/**
 * Tests for the Animation_Defaults_Injector render_block filter.
 *
 * @group animations
 */
class Animation_Defaults_Injector_Test extends WP_UnitTestCase {

	/**
	 * The injector under test.
	 *
	 * @var Animation_Defaults_Injector
	 */
	private $injector;

	/**
	 * Set up an injector instance and a per-block-type animation default.
	 */
	public function set_up() {
		parent::set_up();
		$this->injector = new Animation_Defaults_Injector();
		Settings::update_settings(
			array(
				'animations' => array(
					'block_animations_enabled' => true,
					'block_animations'         => array(
						array(
							'block'    => 'core/button',
							'entrance' => 'fadeInUp',
							'duration' => 800,
						),
					),
				),
			)
		);
	}

	/**
	 * Reset the settings option after each test.
	 */
	public function tear_down() {
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	/**
	 * A block with no per-block animation state should inherit the configured default.
	 */
	public function test_injects_classes_and_data_for_inherit_state_block() {
		$html = '<div class="wp-block-button">x</div>';
		$out  = $this->injector->inject(
			$html,
			array(
				'blockName' => 'core/button',
				'attrs'     => array(),
			) 
		);

		$this->assertStringContainsString( 'has-dsgo-animation', $out );
		$this->assertStringContainsString( 'dsgo-animation-fadeInUp', $out );
		$this->assertStringContainsString( 'data-dsgo-animation-enabled="true"', $out );
		$this->assertStringContainsString( 'data-dsgo-animation-duration="800"', $out );
	}

	/**
	 * A block already opted into its own animation (Custom state) is left untouched.
	 */
	public function test_skips_custom_state_block() {
		$html = '<div class="wp-block-button">x</div>';
		$out  = $this->injector->inject(
			$html,
			array(
				'blockName' => 'core/button',
				'attrs'     => array( 'dsgoAnimationEnabled' => true ),
			) 
		);
		$this->assertSame( $html, $out );
	}

	/**
	 * A block explicitly opted out of animation (Off state) is left untouched.
	 */
	public function test_skips_opted_out_block() {
		$html = '<div class="wp-block-button">x</div>';
		$out  = $this->injector->inject(
			$html,
			array(
				'blockName' => 'core/button',
				'attrs'     => array( 'dsgoAnimationOptOut' => true ),
			) 
		);
		$this->assertSame( $html, $out );
	}

	/**
	 * A block type with no configured default is left untouched.
	 */
	public function test_skips_block_type_without_default() {
		$html = '<p>x</p>';
		$out  = $this->injector->inject(
			$html,
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
			) 
		);
		$this->assertSame( $html, $out );
	}
}
