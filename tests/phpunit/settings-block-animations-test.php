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
	 * The sanitizer should drop entries with no valid block name or no
	 * animation, and fall back enum fields to their defaults.
	 */
	public function test_sanitizer_drops_invalid_block_and_enum_values() {
		$clean = Settings::sanitize_block_animations_list(
			array(
				array(
					'blocks'   => array( 'core/button' ),
					'entrance' => 'fadeInUp',
					'trigger'  => 'bogus',
					'duration' => 999999,
				),
				array(
					'blocks'   => array( 'not a block name' ),
					'entrance' => 'fadeIn',
				), // No valid name -> dropped.
				array( 'entrance' => 'fadeIn' ), // No blocks -> dropped.
				array( 'blocks' => array( 'core/quote' ) ), // No entrance/exit -> dropped.
			)
		);
		$this->assertCount( 1, $clean );
		$this->assertSame( array( 'core/button' ), $clean[0]['blocks'] );
		$this->assertSame( 'scroll', $clean[0]['trigger'] ); // Bogus -> default.
		$this->assertSame( 5000, $clean[0]['duration'] ); // Clamped to max.
	}

	/**
	 * An entry may target several blocks; invalid names within it are dropped
	 * without discarding the entry, and duplicates are collapsed.
	 */
	public function test_sanitizer_keeps_multiple_targets_and_drops_bad_ones() {
		$clean = Settings::sanitize_block_animations_list(
			array(
				array(
					'blocks'   => array(
						'core/button',
						'  designsetgo/icon-button  ', // Trimmed.
						'Core/Button', // Uppercase -> invalid.
						'core/button', // Duplicate within the entry.
						'designsetgo/*',
					),
					'entrance' => 'fadeInUp',
				),
			)
		);
		$this->assertCount( 1, $clean );
		$this->assertSame(
			array( 'core/button', 'designsetgo/icon-button', 'designsetgo/*' ),
			$clean[0]['blocks']
		);
	}

	/**
	 * The sanitizer should accept a `namespace/*` wildcard block name.
	 */
	public function test_sanitizer_accepts_namespace_wildcard() {
		$clean = Settings::sanitize_block_animations_list(
			array(
				array(
					'blocks'   => array( 'designsetgo/*' ),
					'entrance' => 'fadeIn',
				),
			)
		);
		$this->assertCount( 1, $clean );
		$this->assertSame( array( 'designsetgo/*' ), $clean[0]['blocks'] );
	}

	/**
	 * The historical singular `block` key is still accepted on input and
	 * normalized to the `blocks` list, so theme.json / Style Kits authored
	 * against the first shape of this feature keep working.
	 */
	public function test_sanitizer_accepts_legacy_singular_block_key() {
		$clean = Settings::sanitize_block_animations_list(
			array(
				array(
					'block'    => 'core/button',
					'entrance' => 'fadeIn',
				),
			)
		);
		$this->assertCount( 1, $clean );
		$this->assertSame( array( 'core/button' ), $clean[0]['blocks'] );
	}

	/**
	 * A block name claimed by two entries would silently resolve to the later
	 * one (get_effective() builds a name => config map), so the sanitizer
	 * strips the earlier claim rather than persisting a rule that can never
	 * take effect.
	 */
	public function test_sanitizer_dedupes_a_block_claimed_by_two_entries() {
		$clean = Settings::sanitize_block_animations_list(
			array(
				array(
					'blocks'   => array( 'core/button', 'core/image' ),
					'entrance' => 'fadeInUp',
				),
				array(
					'blocks'   => array( 'core/button' ),
					'entrance' => 'zoomIn',
				),
			)
		);
		$this->assertCount( 2, $clean );
		// Earlier entry keeps only the target the later one doesn't claim.
		$this->assertSame( array( 'core/image' ), $clean[0]['blocks'] );
		$this->assertSame( 'fadeInUp', $clean[0]['entrance'] );
		// Last claim wins, matching the resolver's map precedence.
		$this->assertSame( array( 'core/button' ), $clean[1]['blocks'] );
		$this->assertSame( 'zoomIn', $clean[1]['entrance'] );
	}

	/**
	 * An entry left with no targets after deduping is dropped entirely.
	 */
	public function test_sanitizer_drops_entry_fully_claimed_by_a_later_one() {
		$clean = Settings::sanitize_block_animations_list(
			array(
				array(
					'blocks'   => array( 'core/button' ),
					'entrance' => 'fadeInUp',
				),
				array(
					'blocks'   => array( 'core/button' ),
					'entrance' => 'zoomIn',
				),
			)
		);
		$this->assertCount( 1, $clean );
		$this->assertSame( array( 'core/button' ), $clean[0]['blocks'] );
		$this->assertSame( 'zoomIn', $clean[0]['entrance'] );
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
							'blocks'   => array( 'core/button' ),
							'entrance' => 'fadeInUp',
						),
						array(
							'blocks'   => array( 'core/image' ),
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
							'blocks'   => array( 'core/heading' ),
							'entrance' => 'fadeIn',
						),
					),
				),
			)
		);
		$saved = Settings::get_settings()['animations']['block_animations'];
		$this->assertCount( 1, $saved );
		$this->assertSame( array( 'core/heading' ), $saved[0]['blocks'] );
	}
}
