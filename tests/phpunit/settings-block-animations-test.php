<?php
/**
 * Tests for per-block-type animation defaults in plugin settings.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Settings;

/**
 * Per-block-type animation defaults settings test class.
 *
 * @group settings
 */
class Settings_Block_Animations_Test extends WP_UnitTestCase {

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	/**
	 * Defaults should include the disabled gate and an empty list.
	 */
	public function test_defaults_include_disabled_gate_and_empty_list() {
		$defaults = Settings::get_defaults();
		$this->assertFalse( $defaults['animations']['block_animations_enabled'] );
		$this->assertSame( array(), $defaults['animations']['block_animations'] );
	}

	/**
	 * The sanitizer should drop entries with an invalid block name or no
	 * animation, and fall back enum fields to their defaults.
	 */
	public function test_sanitizer_drops_invalid_block_and_enum_values() {
		$clean = Settings::sanitize_block_animations_list(
			array(
				array(
					'block'    => 'core/button',
					'entrance' => 'fadeInUp',
					'trigger'  => 'bogus',
					'duration' => 999999,
				),
				array(
					'block'    => 'not a block name',
					'entrance' => 'fadeIn',
				), // Invalid name -> dropped.
				array( 'entrance' => 'fadeIn' ), // No block -> dropped.
				array( 'block' => 'core/quote' ), // No entrance/exit -> dropped.
			)
		);
		$this->assertCount( 1, $clean );
		$this->assertSame( 'core/button', $clean[0]['block'] );
		$this->assertSame( 'scroll', $clean[0]['trigger'] ); // Bogus -> default.
		$this->assertSame( 5000, $clean[0]['duration'] ); // Clamped to max.
	}

	/**
	 * The sanitizer should accept a `namespace/*` wildcard block name.
	 */
	public function test_sanitizer_accepts_namespace_wildcard() {
		$clean = Settings::sanitize_block_animations_list(
			array(
				array(
					'block'    => 'designsetgo/*',
					'entrance' => 'fadeIn',
				),
			)
		);
		$this->assertCount( 1, $clean );
		$this->assertSame( 'designsetgo/*', $clean[0]['block'] );
	}

	/**
	 * Saving a new `block_animations` list should replace it wholesale
	 * rather than index-merge with the previously saved list.
	 */
	public function test_saving_replaces_list_instead_of_index_merging() {
		Settings::update_settings(
			array(
				'animations' => array(
					'block_animations' => array(
						array(
							'block'    => 'core/button',
							'entrance' => 'fadeInUp',
						),
						array(
							'block'    => 'core/image',
							'entrance' => 'zoomIn',
						),
					),
				),
			)
		);
		Settings::update_settings(
			array(
				'animations' => array(
					'block_animations' => array(
						array(
							'block'    => 'core/heading',
							'entrance' => 'fadeIn',
						),
					),
				),
			)
		);
		$saved = Settings::get_settings()['animations']['block_animations'];
		$this->assertCount( 1, $saved );
		$this->assertSame( 'core/heading', $saved[0]['block'] );
	}
}
