<?php
/**
 * Tests for the disabled_blocks denylist and list-field replacement in
 * Settings::update_settings().
 *
 * Covers:
 * - migrating a legacy enabled_blocks allowlist without changing which
 *   blocks are on, and persisting it so later releases' blocks stay on
 * - the legacy enabled_blocks input still accepted from older clients
 * - Block_Manager registering everything not on the denylist
 * - list fields replaced wholesale instead of merged by index
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Block_Manager;
use DesignSetGo\Admin\Settings;

/**
 * Block denylist test class.
 */
class Settings_Block_Denylist_Test extends WP_UnitTestCase {

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
	 * Every catalog block except the ones given.
	 *
	 * @param string ...$names Block names to leave out.
	 * @return string[]
	 */
	private function catalog_without( string ...$names ): array {
		return array_values( array_diff( Settings::get_catalog_block_names(), $names ) );
	}

	/**
	 * The catalog is readable; every other test depends on it.
	 */
	public function test_catalog_lists_section(): void {
		$this->assertContains( 'designsetgo/section', Settings::get_catalog_block_names() );
	}

	/**
	 * A legacy allowlist that left Section out disables exactly Section.
	 */
	public function test_legacy_allowlist_migrates_to_denylist(): void {
		$this->store( array( 'enabled_blocks' => $this->catalog_without( 'designsetgo/section' ) ) );

		$settings = Settings::get_settings();

		$this->assertSame( array( 'designsetgo/section' ), $settings['disabled_blocks'] );
		$this->assertArrayNotHasKey( 'enabled_blocks', $settings );
	}

	/**
	 * Reading converts in memory only. get_settings() runs on anonymous
	 * front-end requests and behind the readonly get-settings ability.
	 */
	public function test_reading_does_not_write(): void {
		$legacy = array( 'enabled_blocks' => $this->catalog_without( 'designsetgo/section' ) );
		$this->store( $legacy );

		Settings::get_settings();

		$this->assertSame( $legacy, get_option( Settings::OPTION_NAME ) );
	}

	/**
	 * An administrator's wp-admin request writes the conversion back, so a
	 * block added after it stays enabled.
	 */
	public function test_admin_migration_is_persisted(): void {
		$this->store(
			array(
				'enabled_blocks' => $this->catalog_without( 'designsetgo/section' ),
				'forms'          => array( 'retention_days' => 90 ),
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		Settings::migrate_legacy_settings();
		$stored = get_option( Settings::OPTION_NAME );

		$this->assertArrayNotHasKey( 'enabled_blocks', $stored );
		$this->assertSame( array( 'designsetgo/section' ), $stored['disabled_blocks'] );
		$this->assertSame( 90, $stored['forms']['retention_days'], 'Other stored settings survive the migration.' );
	}

	/**
	 * The migration hook runs on admin_init, which also fires for
	 * admin-ajax.php; only users who may change settings trigger the write.
	 */
	public function test_migration_needs_manage_options(): void {
		$legacy = array( 'enabled_blocks' => $this->catalog_without( 'designsetgo/section' ) );
		$this->store( $legacy );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		Settings::migrate_legacy_settings();

		$this->assertSame( $legacy, get_option( Settings::OPTION_NAME ) );
	}

	/**
	 * Saving any setting persists the conversion too.
	 */
	public function test_saving_persists_conversion(): void {
		$this->store( array( 'enabled_blocks' => $this->catalog_without( 'designsetgo/section' ) ) );

		Settings::update_settings( array( 'forms' => array( 'retention_days' => 60 ) ) );
		$stored = get_option( Settings::OPTION_NAME );

		$this->assertArrayNotHasKey( 'enabled_blocks', $stored );
		$this->assertSame( array( 'designsetgo/section' ), $stored['disabled_blocks'] );
	}

	/**
	 * An empty legacy allowlist meant "all enabled" and disables nothing.
	 */
	public function test_empty_legacy_allowlist_disables_nothing(): void {
		$this->store( array( 'enabled_blocks' => array() ) );

		$this->assertSame( array(), Settings::get_settings()['disabled_blocks'] );
	}

	/**
	 * Only disabled blocks are refused; blocks outside the catalog register.
	 */
	public function test_block_manager_registers_everything_not_disabled(): void {
		$this->store( array( 'enabled_blocks' => $this->catalog_without( 'designsetgo/section' ) ) );
		$manager = new Block_Manager();

		$this->assertFalse( $manager->should_register_block( true, 'designsetgo/section' ) );
		$this->assertTrue( $manager->should_register_block( true, 'designsetgo/row' ) );
		$this->assertTrue(
			$manager->should_register_block( true, 'designsetgo/added-in-a-later-release' ),
			'A block the stored settings never mention must register.'
		);
	}

	/**
	 * Older clients that still send enabled_blocks are translated.
	 */
	public function test_legacy_enabled_blocks_input_is_translated(): void {
		Settings::update_settings( array( 'enabled_blocks' => $this->catalog_without( 'designsetgo/tabs' ) ) );

		$stored = get_option( Settings::OPTION_NAME );
		$this->assertSame( array( 'designsetgo/tabs' ), $stored['disabled_blocks'] );
		$this->assertArrayNotHasKey( 'enabled_blocks', $stored );
	}

	/**
	 * When both are sent, disabled_blocks wins.
	 */
	public function test_disabled_blocks_input_wins_over_legacy_input(): void {
		Settings::update_settings(
			array(
				'enabled_blocks'  => array( 'designsetgo/row' ),
				'disabled_blocks' => array( 'designsetgo/tabs' ),
			)
		);

		$this->assertSame( array( 'designsetgo/tabs' ), Settings::get_settings()['disabled_blocks'] );
	}

	/**
	 * A shorter list replaces the stored one instead of keeping its tail.
	 */
	public function test_shorter_list_replaces_stored_list(): void {
		$this->store( array( 'disabled_blocks' => array( 'designsetgo/tabs', 'designsetgo/section', 'designsetgo/row' ) ) );

		Settings::update_settings( array( 'disabled_blocks' => array( 'designsetgo/row' ) ) );

		$this->assertSame( array( 'designsetgo/row' ), Settings::get_settings()['disabled_blocks'] );
	}

	/**
	 * An empty list clears the stored one.
	 */
	public function test_empty_list_clears_stored_list(): void {
		$this->store( array( 'disabled_blocks' => array( 'designsetgo/section' ) ) );

		Settings::update_settings( array( 'disabled_blocks' => array() ) );

		$this->assertSame( array(), Settings::get_settings()['disabled_blocks'] );
	}

	/**
	 * The other top-level lists get the same wholesale replacement.
	 */
	public function test_disabled_extensions_list_is_replaced(): void {
		$this->store( array( 'disabled_extensions' => array( 'block-animations', 'draft-mode', 'dynamic-tags' ) ) );

		Settings::update_settings( array( 'disabled_extensions' => array( 'dynamic-tags' ) ) );

		$this->assertSame( array( 'dynamic-tags' ), Settings::get_settings()['disabled_extensions'] );
	}

	/**
	 * Nested lists get it too, without disturbing their sibling fields.
	 */
	public function test_nested_list_is_replaced_and_siblings_kept(): void {
		$this->store(
			array(
				'llms_txt' => array(
					'enable'     => true,
					'post_types' => array( 'page', 'post', 'product' ),
				),
			)
		);

		Settings::update_settings( array( 'llms_txt' => array( 'post_types' => array( 'page' ) ) ) );

		$llms = Settings::get_settings()['llms_txt'];
		$this->assertSame( array( 'page' ), $llms['post_types'] );
		$this->assertTrue( $llms['enable'], 'Sibling fields in the group are preserved.' );
	}
}
