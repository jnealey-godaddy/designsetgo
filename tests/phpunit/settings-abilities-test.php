<?php
/**
 * Tests for the Settings abilities (designsetgo/get-settings,
 * designsetgo/update-settings) and the shared Settings::update_settings()
 * pipeline they rely on.
 *
 * Covers:
 * - permission enforcement (manage_options required)
 * - partial update semantics (nested merge preserves omitted keys)
 * - empty-object edge case so groups aren't accidentally reset
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Settings\Get_Settings;
use DesignSetGo\Abilities\Settings\Update_Settings;
use DesignSetGo\Admin\Settings;

/**
 * Settings abilities test class.
 */
class Settings_Abilities_Test extends WP_UnitTestCase {

	/**
	 * Administrator user ID (meets manage_options).
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Subscriber user ID (lacks manage_options).
	 *
	 * @var int
	 */
	private int $subscriber_id;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();

		wp_set_current_user( $this->admin_id );
	}

	/**
	 * Reset any cached state that could leak between tests.
	 */
	public function tear_down(): void {
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Permission enforcement
	// -------------------------------------------------------------------------

	/**
	 * Administrators can read settings.
	 */
	public function test_get_settings_permission_granted_for_admin(): void {
		wp_set_current_user( $this->admin_id );
		$ability = new Get_Settings();
		$this->assertTrue( $ability->check_permission_callback() );
	}

	/**
	 * Subscribers cannot read settings.
	 */
	public function test_get_settings_permission_denied_for_subscriber(): void {
		wp_set_current_user( $this->subscriber_id );
		$ability = new Get_Settings();
		$this->assertFalse( $ability->check_permission_callback() );
	}

	/**
	 * Anonymous users cannot read settings.
	 */
	public function test_get_settings_permission_denied_for_anonymous(): void {
		wp_set_current_user( 0 );
		$ability = new Get_Settings();
		$this->assertFalse( $ability->check_permission_callback() );
	}

	/**
	 * Administrators can update settings.
	 */
	public function test_update_settings_permission_granted_for_admin(): void {
		wp_set_current_user( $this->admin_id );
		$ability = new Update_Settings();
		$this->assertTrue( $ability->check_permission_callback() );
	}

	/**
	 * Subscribers cannot update settings.
	 */
	public function test_update_settings_permission_denied_for_subscriber(): void {
		wp_set_current_user( $this->subscriber_id );
		$ability = new Update_Settings();
		$this->assertFalse( $ability->check_permission_callback() );
	}

	// -------------------------------------------------------------------------
	// get-settings execution
	// -------------------------------------------------------------------------

	/**
	 * Returns the expected top-level setting groups.
	 */
	public function test_get_settings_returns_full_settings_structure(): void {
		$ability = new Get_Settings();
		$result  = $ability->execute( array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'forms', $result );
		$this->assertArrayHasKey( 'integrations', $result );
		$this->assertArrayHasKey( 'animations', $result );
		$this->assertArrayHasKey( 'draft_mode', $result );
		$this->assertArrayHasKey( 'llms_txt', $result );
	}

	// -------------------------------------------------------------------------
	// Partial update semantics — nested merge preserves omitted keys
	// -------------------------------------------------------------------------

	/**
	 * Updating a single nested field preserves all other fields in the group.
	 */
	public function test_partial_nested_update_preserves_sibling_fields(): void {
		// Seed a non-default value across multiple fields in the forms group.
		update_option(
			Settings::OPTION_NAME,
			array(
				'forms' => array(
					'enable_honeypot' => false,
					'retention_days'  => 90,
				),
			)
		);
		Settings::invalidate_cache();

		$ability = new Update_Settings();
		$ability->execute(
			array(
				'forms' => array( 'retention_days' => 120 ),
			)
		);

		$saved = Settings::get_settings();
		$this->assertSame( 120, $saved['forms']['retention_days'], 'Submitted field should update.' );
		$this->assertFalse( $saved['forms']['enable_honeypot'], 'Omitted sibling field should be preserved.' );
	}

	/**
	 * Updating one group leaves other groups untouched.
	 */
	public function test_update_one_group_does_not_affect_other_groups(): void {
		update_option(
			Settings::OPTION_NAME,
			array(
				'integrations' => array(
					'google_maps_api_key'  => 'SEEDED-MAPS-KEY',
					'turnstile_site_key'   => 'SEEDED-SITE-KEY',
					'turnstile_secret_key' => 'SEEDED-SECRET',
				),
			)
		);
		Settings::invalidate_cache();

		$ability = new Update_Settings();
		$ability->execute(
			array(
				'forms' => array( 'retention_days' => 7 ),
			)
		);

		$saved = Settings::get_settings();
		$this->assertSame( 'SEEDED-MAPS-KEY', $saved['integrations']['google_maps_api_key'] );
		$this->assertSame( 'SEEDED-SITE-KEY', $saved['integrations']['turnstile_site_key'] );
		$this->assertSame( 'SEEDED-SECRET', $saved['integrations']['turnstile_secret_key'] );
	}

	// -------------------------------------------------------------------------
	// Empty-object edge case
	// -------------------------------------------------------------------------

	/**
	 * Submitting an empty nested group is a no-op — the group is not reset.
	 *
	 * Regression coverage for the bug where `{ "integrations": {} }` on a
	 * fresh install would persist `integrations => []`, and subsequent
	 * get_settings() would lose the defaults for that group because
	 * wp_parse_args is not recursive.
	 */
	public function test_empty_nested_group_does_not_reset_existing_values(): void {
		update_option(
			Settings::OPTION_NAME,
			array(
				'integrations' => array(
					'google_maps_api_key' => 'SEEDED-MAPS-KEY',
				),
			)
		);
		Settings::invalidate_cache();

		$ability = new Update_Settings();
		$ability->execute( array( 'integrations' => array() ) );

		$saved = Settings::get_settings();
		$this->assertSame(
			'SEEDED-MAPS-KEY',
			$saved['integrations']['google_maps_api_key'],
			'Empty group payload must not clear existing values.'
		);
	}

	/**
	 * On a fresh install (no prior saved option), submitting an empty nested
	 * group must not suppress the defaults for that group.
	 */
	public function test_empty_group_on_fresh_install_keeps_defaults(): void {
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();

		$ability = new Update_Settings();
		$ability->execute( array( 'integrations' => array() ) );

		$saved = Settings::get_settings();
		$this->assertArrayHasKey( 'google_maps_api_key', $saved['integrations'] );
		$this->assertArrayHasKey( 'turnstile_site_key', $saved['integrations'] );
		$this->assertArrayHasKey( 'turnstile_secret_key', $saved['integrations'] );
	}

	/**
	 * Submitting an empty top-level object is a total no-op.
	 */
	public function test_empty_top_level_object_is_noop(): void {
		update_option(
			Settings::OPTION_NAME,
			array(
				'forms' => array( 'retention_days' => 45 ),
			)
		);
		Settings::invalidate_cache();

		$ability = new Update_Settings();
		$ability->execute( array() );

		$saved = Settings::get_settings();
		$this->assertSame( 45, $saved['forms']['retention_days'] );
	}
}
