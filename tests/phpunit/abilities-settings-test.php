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
	 * Whether the first-touch _doing_it_wrong notice has already fired
	 * in this PHP process. Unrelated abilities in the registry trip one
	 * about an invalid `keywords` property on first construction; it
	 * fires exactly once per process so only one test needs to declare
	 * it as expected.
	 *
	 * @var bool
	 */
	private static bool $primed_registry = false;

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
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		if ( ! self::$primed_registry ) {
			$this->setExpectedIncorrectUsage( 'WP_Ability::__construct' );
			self::$primed_registry = true;
		}
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
		$this->assertTrue( $valid, 'Empty object must pass input validation.' );
	}

	/**
	 * The get ability returns a structured payload even before anything
	 * has been written. Guards the no-op read path.
	 */
	public function test_get_global_css_empty_state(): void {
		$ability = wp_get_ability( 'designsetgo/get-global-css' );
		$this->assertNotNull( $ability );

		$result = $ability->execute( array() );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] ?? false );
		$this->assertSame( '', $result['css'] );
		$this->assertSame( get_stylesheet(), $result['stylesheet'] );
	}

	/**
	 * Smoke-tests the full write → read round-trip through both
	 * abilities, which is the workflow LLM clients will use for
	 * read-modify-write of Additional CSS.
	 */
	public function test_update_then_get_roundtrip(): void {
		// edit_css is gated on unfiltered_html; administrators on single
		// site have it. Skip if the environment denies the cap so this
		// test stays portable to multisite phpunit runs.
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

		// Clean up so this test leaves no global state behind.
		$updater->execute( array( 'css' => '' ) );
	}
}
