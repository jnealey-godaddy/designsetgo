<?php
/**
 * Tests for the per-block-type animation defaults resolver.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Settings;
use DesignSetGo\Animation_Defaults;

/**
 * Tests for the Animation_Defaults resolver.
 *
 * @group animations
 */
class Animation_Defaults_Test extends WP_UnitTestCase {

	/**
	 * Reset the settings option and theme.json cache after each test.
	 */
	public function tear_down() {
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		// wp_get_global_settings() caches merged theme.json data in a static
		// property that survives across tests in the same process. Reset it
		// so a `wp_theme_json_data_theme` filter added by one test never
		// leaks its data into the next.
		wp_clean_theme_json_cache();
		parent::tear_down();
	}

	/**
	 * Store the admin option's block-animations gate and list.
	 *
	 * @param bool  $enabled Whether the admin block-animations gate is on.
	 * @param array $entries List of block-animation entries.
	 */
	private function set_option( $enabled, array $entries ) {
		Settings::update_settings(
			array(
				'animations' => array(
					'block_animations_enabled' => $enabled,
					'block_animations'         => $entries,
				),
			)
		);
	}

	/**
	 * When the feature is disabled entirely, resolution returns null.
	 */
	public function test_disabled_gate_returns_null() {
		$this->set_option(
			false,
			array(
				array(
					'blocks'   => array( 'core/button' ),
					'entrance' => 'fadeInUp',
				),
			)
		);
		$this->assertNull( Animation_Defaults::resolve_for_block( 'core/button' ) );
	}

	/**
	 * An exact block-name match resolves to a fully normalized config.
	 */
	public function test_exact_match_resolves_normalized_config() {
		$this->set_option(
			true,
			array(
				array(
					'blocks'   => array( 'core/button' ),
					'entrance' => 'fadeInUp',
				),
			)
		);
		$config = Animation_Defaults::resolve_for_block( 'core/button' );
		$this->assertSame( 'fadeInUp', $config['entrance'] );
		$this->assertSame( 'scroll', $config['trigger'] ); // Normalized default.
		$this->assertSame( 600, $config['duration'] );
	}

	/**
	 * A namespace wildcard entry matches blocks with no exact entry.
	 */
	public function test_wildcard_match_when_no_exact() {
		$this->set_option(
			true,
			array(
				array(
					'blocks'   => array( 'designsetgo/*' ),
					'entrance' => 'fadeIn',
				),
			)
		);
		$this->assertSame( 'fadeIn', Animation_Defaults::resolve_for_block( 'designsetgo/section' )['entrance'] );
		$this->assertNull( Animation_Defaults::resolve_for_block( 'core/paragraph' ) );
	}

	/**
	 * An exact block-name match wins over a namespace wildcard.
	 */
	public function test_exact_beats_wildcard() {
		$this->set_option(
			true,
			array(
				array(
					'blocks'   => array( 'designsetgo/*' ),
					'entrance' => 'fadeIn',
				),
				array(
					'blocks'   => array( 'designsetgo/section' ),
					'entrance' => 'zoomIn',
				),
			)
		);
		$this->assertSame( 'zoomIn', Animation_Defaults::resolve_for_block( 'designsetgo/section' )['entrance'] );
	}

	/**
	 * The admin option overrides a theme.json / Style-Kit entry for the same block.
	 */
	public function test_admin_option_overrides_theme_json_for_same_block() {
		// Theme.json layer via wp_get_global_settings filter.
		add_filter(
			'wp_theme_json_data_theme',
			function ( $data ) {
				return $data->update_with(
					array(
						'version'  => 2,
						'settings' => array(
							'custom' => array(
								'designsetgo' => array(
									'blockAnimationsEnabled' => true,
									'blockAnimations' => array(
										array(
											'blocks'   => array( 'core/button' ),
											'entrance' => 'fadeIn',
										),
									),
								),
							),
						),
					)
				);
			}
		);
		// Force the merged theme.json data to recompute now that the filter
		// above is registered, in case an earlier test already cached it.
		wp_clean_theme_json_cache();
		$this->set_option(
			true,
			array(
				array(
					'blocks'   => array( 'core/button' ),
					'entrance' => 'zoomIn',
				),
			)
		);

		$this->assertSame( 'zoomIn', Animation_Defaults::resolve_for_block( 'core/button' )['entrance'] );
	}

	/**
	 * The global (theme.json / Style-Kit) gate alone is enough to enable the feature.
	 */
	public function test_theme_json_used_when_admin_list_empty() {
		add_filter(
			'wp_theme_json_data_theme',
			function ( $data ) {
				return $data->update_with(
					array(
						'version'  => 2,
						'settings' => array(
							'custom' => array(
								'designsetgo' => array(
									'blockAnimationsEnabled' => true,
									'blockAnimations' => array(
										array(
											'blocks'   => array( 'core/image' ),
											'entrance' => 'zoomIn',
										),
									),
								),
							),
						),
					)
				);
			}
		);
		// Force the merged theme.json data to recompute now that the filter
		// above is registered, in case an earlier test already cached it.
		wp_clean_theme_json_cache();
		// Admin list empty, admin gate off — global gate turns it on.
		$this->set_option( false, array() );

		$this->assertSame( 'zoomIn', Animation_Defaults::resolve_for_block( 'core/image' )['entrance'] );
	}

	/**
	 * A theme.json / Style-Kit entry with invalid values is validated and
	 * clamped the same way an admin-submitted entry would be (validation
	 * parity) — an unknown trigger/easing falls back to the default and an
	 * out-of-range duration/offset is clamped, rather than reaching the
	 * frontend as raw data-* attributes.
	 */
	public function test_theme_json_entry_is_validated_like_admin() {
		add_filter(
			'wp_theme_json_data_theme',
			function ( $data ) {
				return $data->update_with(
					array(
						'version'  => 2,
						'settings' => array(
							'custom' => array(
								'designsetgo' => array(
									'blockAnimationsEnabled' => true,
									'blockAnimations' => array(
										array(
											'blocks'   => array( 'core/quote' ),
											'entrance' => 'fadeIn',
											'trigger'  => 'bogus',   // Invalid -> 'scroll'.
											'easing'   => 'garbage', // Invalid -> 'ease-out'.
											'duration' => 999999,    // Out of range -> 5000.
											'offset'   => 99999,     // Out of range -> 1000.
										),
									),
								),
							),
						),
					)
				);
			}
		);
		wp_clean_theme_json_cache();
		$this->set_option( false, array() );

		$config = Animation_Defaults::resolve_for_block( 'core/quote' );
		$this->assertSame( 'fadeIn', $config['entrance'] );
		$this->assertSame( 'scroll', $config['trigger'] );
		$this->assertSame( 'ease-out', $config['easing'] );
		$this->assertSame( 5000, $config['duration'] );
		$this->assertSame( 1000, $config['offset'] );
	}

	/**
	 * One entry may target several blocks; each resolves to the same config.
	 */
	public function test_entry_targeting_multiple_blocks_resolves_for_each() {
		$this->set_option(
			true,
			array(
				array(
					'blocks'   => array( 'core/button', 'designsetgo/icon-button' ),
					'entrance' => 'zoomIn',
				),
			)
		);

		$this->assertSame( 'zoomIn', Animation_Defaults::resolve_for_block( 'core/button' )['entrance'] );
		$this->assertSame( 'zoomIn', Animation_Defaults::resolve_for_block( 'designsetgo/icon-button' )['entrance'] );
		$this->assertNull( Animation_Defaults::resolve_for_block( 'core/paragraph' ) );
	}

	/**
	 * A multi-target entry may mix exact names and a namespace wildcard, and
	 * an exact entry elsewhere still beats that wildcard.
	 */
	public function test_multi_target_entry_mixes_exact_and_wildcard() {
		$this->set_option(
			true,
			array(
				array(
					'blocks'   => array( 'core/button', 'designsetgo/*' ),
					'entrance' => 'fadeIn',
				),
				array(
					'blocks'   => array( 'designsetgo/section' ),
					'entrance' => 'bounceIn',
				),
			)
		);

		$this->assertSame( 'fadeIn', Animation_Defaults::resolve_for_block( 'core/button' )['entrance'] );
		$this->assertSame( 'fadeIn', Animation_Defaults::resolve_for_block( 'designsetgo/row' )['entrance'] );
		$this->assertSame( 'bounceIn', Animation_Defaults::resolve_for_block( 'designsetgo/section' )['entrance'] );
	}

	/**
	 * The historical singular `block` key still resolves, so a theme.json /
	 * Style Kit authored against the first shape of this feature keeps working.
	 */
	public function test_legacy_singular_block_key_still_resolves() {
		add_filter(
			'wp_theme_json_data_theme',
			function ( $data ) {
				return $data->update_with(
					array(
						'version'  => 2,
						'settings' => array(
							'custom' => array(
								'designsetgo' => array(
									'blockAnimationsEnabled' => true,
									'blockAnimations' => array(
										array(
											'block'    => 'core/list',
											'entrance' => 'slideInUp',
										),
									),
								),
							),
						),
					)
				);
			}
		);
		wp_clean_theme_json_cache();
		$this->set_option( false, array() );

		$this->assertSame( 'slideInUp', Animation_Defaults::resolve_for_block( 'core/list' )['entrance'] );
	}

	/**
	 * A theme.json entry whose block name is malformed is rejected outright
	 * rather than sitting in the map under a key nothing can ever match — the
	 * same format validation the admin sanitizer applies.
	 */
	public function test_theme_json_entry_with_malformed_block_name_is_ignored() {
		add_filter(
			'wp_theme_json_data_theme',
			function ( $data ) {
				return $data->update_with(
					array(
						'version'  => 2,
						'settings' => array(
							'custom' => array(
								'designsetgo' => array(
									'blockAnimationsEnabled' => true,
									'blockAnimations' => array(
										array(
											'blocks'   => array( 'Core/Button', 'no-slash' ),
											'entrance' => 'fadeIn',
										),
										array(
											// Padded name is trimmed, not rejected.
											'blocks'   => array( '  core/code  ' ),
											'entrance' => 'zoomIn',
										),
									),
								),
							),
						),
					)
				);
			}
		);
		wp_clean_theme_json_cache();
		$this->set_option( false, array() );

		$effective = Animation_Defaults::get_effective();
		$this->assertArrayNotHasKey( 'Core/Button', $effective['map'] );
		$this->assertArrayNotHasKey( 'no-slash', $effective['map'] );
		$this->assertSame( 'zoomIn', Animation_Defaults::resolve_for_block( 'core/code' )['entrance'] );
	}
}
