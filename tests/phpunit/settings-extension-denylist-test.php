<?php
/**
 * Tests for the disabled_extensions denylist.
 *
 * Covers:
 * - the frozen 2.8.1 extension catalog naming only extensions that exist
 * - converting a legacy enabled_extensions allowlist without changing which
 *   extensions are on, and persisting it from an admin request
 * - both legacy allowlists migrating together
 * - the legacy enabled_extensions input still accepted from older clients
 * - Block_Manager loading everything not on the denylist
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Block_Manager;
use DesignSetGo\Admin\Settings;

/**
 * Extension denylist test class.
 */
class Settings_Extension_Denylist_Test extends WP_UnitTestCase {

	/**
	 * Reset stored settings before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
	}

	/**
	 * Reset stored settings after each test.
	 */
	public function tear_down(): void {
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	/**
	 * Store a raw settings array, as an older release would have.
	 *
	 * @param array $settings Settings to store.
	 */
	private function store( array $settings ): void {
		update_option( Settings::OPTION_NAME, $settings );
		Settings::invalidate_cache();
	}

	/**
	 * Act as an administrator, who may run the settings migration.
	 */
	private function as_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Every extension except the ones given.
	 *
	 * @param string ...$names Extension names to leave out.
	 * @return string[]
	 */
	private function extensions_without( string ...$names ): array {
		return array_values( array_diff( Settings::get_legacy_catalog( 'disabled_extensions' ), $names ) );
	}

	/**
	 * The frozen 2.8.1 extension catalog only names extensions that still
	 * exist, so the conversion can't disable something by a stale name.
	 */
	public function test_legacy_catalog_is_a_subset_of_available_extensions(): void {
		$this->assertSame(
			array(),
			array_values(
				array_diff(
					Settings::get_legacy_catalog( 'disabled_extensions' ),
					wp_list_pluck( Settings::get_available_extensions(), 'name' )
				)
			)
		);
	}

	/**
	 * A legacy allowlist that left Dynamic Tags out disables exactly Dynamic Tags.
	 */
	public function test_legacy_allowlist_migrates_to_denylist(): void {
		$this->store( array( 'enabled_extensions' => $this->extensions_without( 'dynamic-tags' ) ) );

		$settings = Settings::get_settings();

		$this->assertSame( array( 'dynamic-tags' ), $settings['disabled_extensions'] );
		$this->assertArrayNotHasKey( 'enabled_extensions', $settings );
	}

	/**
	 * Reading converts in memory only; an administrator's request persists it.
	 */
	public function test_migration_is_persisted_by_admin_only(): void {
		$legacy = array( 'enabled_extensions' => $this->extensions_without( 'draft-mode' ) );
		$this->store( $legacy );

		Settings::get_settings();
		$this->assertSame( $legacy, get_option( Settings::OPTION_NAME ), 'Reading must not write.' );

		$this->as_admin();
		Settings::migrate_legacy_settings();
		$stored = get_option( Settings::OPTION_NAME );

		$this->assertArrayNotHasKey( 'enabled_extensions', $stored );
		$this->assertSame( array( 'draft-mode' ), $stored['disabled_extensions'] );
	}

	/**
	 * An empty legacy allowlist meant "all enabled" and disables nothing.
	 */
	public function test_empty_legacy_allowlist_disables_nothing(): void {
		$this->store( array( 'enabled_extensions' => array() ) );

		$this->assertSame( array(), Settings::get_settings()['disabled_extensions'] );
	}

	/**
	 * Block and extension allowlists stored together both migrate.
	 */
	public function test_both_allowlists_migrate_together(): void {
		$blocks = array_values( array_diff( Settings::get_legacy_catalog( 'disabled_blocks' ), array( 'designsetgo/section' ) ) );
		$this->store(
			array(
				'enabled_blocks'     => $blocks,
				'enabled_extensions' => $this->extensions_without( 'dynamic-tags' ),
			)
		);

		$this->as_admin();
		Settings::migrate_legacy_settings();
		$stored = get_option( Settings::OPTION_NAME );

		$this->assertSame( array( 'designsetgo/section' ), $stored['disabled_blocks'] );
		$this->assertSame( array( 'dynamic-tags' ), $stored['disabled_extensions'] );
		$this->assertArrayNotHasKey( 'enabled_blocks', $stored );
		$this->assertArrayNotHasKey( 'enabled_extensions', $stored );
	}

	/**
	 * Only disabled extensions are refused; extensions outside the list load.
	 */
	public function test_block_manager_loads_everything_not_disabled(): void {
		$this->store( array( 'enabled_extensions' => $this->extensions_without( 'dynamic-tags' ) ) );
		$manager = new Block_Manager();

		$this->assertFalse( $manager->should_load_extension( true, 'dynamic-tags' ) );
		$this->assertTrue( $manager->should_load_extension( true, 'draft-mode' ) );
		$this->assertTrue(
			$manager->should_load_extension( true, 'added-in-a-later-release' ),
			'An extension the stored settings never mention must load.'
		);
	}

	/**
	 * Older clients that still send enabled_extensions are translated.
	 */
	public function test_legacy_enabled_extensions_input_is_translated(): void {
		Settings::update_settings( array( 'enabled_extensions' => $this->extensions_without( 'custom-css' ) ) );

		$stored = get_option( Settings::OPTION_NAME );
		$this->assertSame( array( 'custom-css' ), $stored['disabled_extensions'] );
		$this->assertArrayNotHasKey( 'enabled_extensions', $stored );
	}

	/**
	 * When both are sent, disabled_extensions wins.
	 */
	public function test_disabled_extensions_input_wins_over_legacy_input(): void {
		Settings::update_settings(
			array(
				'enabled_extensions'  => array( 'animation' ),
				'disabled_extensions' => array( 'dynamic-tags' ),
			)
		);

		$this->assertSame( array( 'dynamic-tags' ), Settings::get_settings()['disabled_extensions'] );
	}
}
