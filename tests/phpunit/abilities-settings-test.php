<?php
/**
 * Tests for DesignSetGo settings abilities (get/update-global-css).
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * Settings abilities test class.
 */
class Abilities_Settings_Test extends WP_UnitTestCase {

	/**
	 * User with edit_css.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API requires WordPress 6.9+.' );
		}

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	/**
	 * Tear down each test.
	 *
	 * Always clears the active theme's Additional CSS so an early
	 * assertion failure in the round-trip test can't leak global CSS
	 * state into other tests in this process.
	 */
	public function tear_down(): void {
		if ( function_exists( 'wp_update_custom_css_post' ) ) {
			wp_update_custom_css_post( '', array( 'stylesheet' => get_stylesheet() ) );
		}
		parent::tear_down();
	}

	/**
	 * Regression test for the get-global-css REST flow.
	 *
	 * Before the fix, the input schema declared `type: object` with no
	 * default. Core's Abilities run controller passes `null` when no
	 * `?input=` query param is sent, and `null` fails the object check —
	 * so every GET returned 400 `ability_invalid_input`. The fix adds
	 * `'default' => array()` so normalize_input() supplies the empty
	 * object before validation runs.
	 */
	public function test_get_global_css_accepts_null_input(): void {
		$ability = wp_get_ability( 'designsetgo/get-global-css' );
		$this->assertNotNull( $ability, 'Ability should be registered.' );

		$normalized = $ability->normalize_input( null );
		$this->assertSame( array(), $normalized, 'Null input should normalize to the empty-object default.' );

		$valid = $ability->validate_input( $normalized );
		$this->assertNotWPError( $valid, 'Empty object must pass input validation.' );
	}

	/**
	 * The get ability returns a structured payload even before anything
	 * has been written. Guards the no-op read path and exercises every
	 * declared output property so a regression in any field is caught.
	 */
	public function test_get_global_css_empty_state(): void {
		$ability = wp_get_ability( 'designsetgo/get-global-css' );
		$this->assertNotNull( $ability );

		$result = $ability->execute( array() );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] ?? false );
		$this->assertSame( '', $result['css'] );
		$this->assertSame( get_stylesheet(), $result['stylesheet'] );
		$this->assertArrayHasKey( 'post_id', $result );
		$this->assertNull( $result['post_id'], 'post_id must be null when no CSS has been saved.' );
	}

	/**
	 * Smoke-tests the full write → read round-trip through both
	 * abilities, which is the workflow LLM clients will use for
	 * read-modify-write of Additional CSS.
	 */
	public function test_update_then_get_roundtrip(): void {
		if ( ! current_user_can( 'edit_css' ) ) {
			$this->markTestSkipped( 'edit_css cap unavailable in this environment.' );
		}

		$css     = 'body { --dsgo-test: 1; }';
		$updater = wp_get_ability( 'designsetgo/update-global-css' );
		$getter  = wp_get_ability( 'designsetgo/get-global-css' );
		$this->assertNotNull( $updater );
		$this->assertNotNull( $getter );

		$update_result = $updater->execute( array( 'css' => $css ) );
		$this->assertTrue( $update_result['success'] ?? false );
		$this->assertSame( $css, $update_result['css'] );

		$get_result = $getter->execute( array() );
		$this->assertSame( $css, $get_result['css'] );
		$this->assertIsInt( $get_result['post_id'] );
	}

	/**
	 * Permission denial: a subscriber lacks edit_css and must be
	 * rejected by both abilities' permission callbacks. Mirrors the
	 * pattern used elsewhere in the abilities test suite and guards the
	 * cap check in Get_Global_CSS / Update_Global_CSS.
	 */
	public function test_permission_denied_for_subscriber(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$getter  = wp_get_ability( 'designsetgo/get-global-css' );
		$updater = wp_get_ability( 'designsetgo/update-global-css' );
		$this->assertNotNull( $getter );
		$this->assertNotNull( $updater );

		$this->assertFalse(
			$getter->check_permissions( array() ),
			'Subscriber must not be able to read global CSS.'
		);
		$this->assertFalse(
			$updater->check_permissions( array( 'css' => 'body{}' ) ),
			'Subscriber must not be able to write global CSS.'
		);
	}
}
