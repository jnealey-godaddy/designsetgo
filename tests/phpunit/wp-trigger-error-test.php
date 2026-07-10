<?php
/**
 * Tests for wp_trigger_error usage across plugin files.
 *
 * Verifies that error paths using wp_trigger_error() execute without
 * fatal errors and produce correct return values. When WP_DEBUG is true,
 * also verifies notices are triggered via the wp_trigger_error_run action.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\Assets;
use DesignSetGo\Patterns\Loader;
use ReflectionClass;

/**
 * wp_trigger_error Error Path Tests
 */
class Test_WP_Trigger_Error extends WP_UnitTestCase {

	/**
	 * Captured notices via wp_trigger_error_run action.
	 *
	 * @var array
	 */
	private $captured_notices = array();

	/**
	 * Set up: hook into wp_trigger_error_run action.
	 */
	public function set_up() {
		parent::set_up();
		$this->captured_notices = array();
		add_action( 'wp_trigger_error_run', array( $this, 'capture_trigger_error' ), 10, 3 );
	}

	/**
	 * Tear down: remove action hook.
	 */
	public function tear_down() {
		remove_action( 'wp_trigger_error_run', array( $this, 'capture_trigger_error' ), 10 );
		parent::tear_down();
	}

	/**
	 * Capture wp_trigger_error calls via the action hook.
	 *
	 * @param string $function_name Function that triggered the error.
	 * @param string $message       Error message.
	 * @param int    $error_level   Error level.
	 */
	public function capture_trigger_error( $function_name, $message, $error_level ) {
		$this->captured_notices[] = array(
			'function' => $function_name,
			'message'  => $message,
			'level'    => $error_level,
		);
	}

	/**
	 * Assert that a wp_trigger_error was called with expected message substring.
	 *
	 * When WP_DEBUG is false, wp_trigger_error bails before firing the action,
	 * so we skip the notice assertion and only verify the code path executed
	 * (via return value or side effects).
	 *
	 * @param string $expected_substring Substring expected in the message.
	 * @param int    $expected_count     Expected number of notices.
	 */
	private function assert_notice_triggered( $expected_substring, $expected_count = 1 ) {
		if ( ! WP_DEBUG ) {
			$this->markTestSkipped(
				'WP_DEBUG is false — wp_trigger_error() bails early. ' .
				'Run with WP_DEBUG=true to fully test notice triggering.'
			);
			return;
		}

		$this->assertCount( $expected_count, $this->captured_notices );
		$this->assertStringContainsString( $expected_substring, $this->captured_notices[0]['message'] );
		$this->assertSame( E_USER_NOTICE, $this->captured_notices[0]['level'] );
	}

	// -------------------------------------------------------------------------
	// uninstall.php — designsetgo_uninstall_step()
	// -------------------------------------------------------------------------

	/**
	 * Test that designsetgo_uninstall_step handles exception without fatal.
	 */
	public function test_uninstall_step_survives_exception() {
		if ( ! function_exists( 'designsetgo_uninstall_step' ) ) {
			$this->markTestSkipped( 'designsetgo_uninstall_step not loaded — run uninstall-test.php first or load uninstall.php separately.' );
			return;
		}

		@designsetgo_uninstall_step(
			'test failure',
			function () {
				throw new \RuntimeException( 'Simulated error' );
			}
		);

		// Primary assertion: no fatal error occurred.
		$this->assertTrue( true, 'designsetgo_uninstall_step should catch exceptions without fatal' );
	}

	/**
	 * Test that designsetgo_uninstall_step triggers notice on exception.
	 */
	public function test_uninstall_step_triggers_notice_on_exception() {
		if ( ! WP_DEBUG ) {
			$this->markTestSkipped( 'WP_DEBUG is false — wp_trigger_error() bails early.' );
			return;
		}

		if ( ! function_exists( 'designsetgo_uninstall_step' ) ) {
			$this->markTestSkipped( 'designsetgo_uninstall_step not loaded — run uninstall-test.php first or load uninstall.php separately.' );
			return;
		}

		@designsetgo_uninstall_step(
			'test failure',
			function () {
				throw new \RuntimeException( 'Simulated error' );
			}
		);

		$this->assertCount( 1, $this->captured_notices );
		$this->assertStringContainsString( 'test failure', $this->captured_notices[0]['message'] );
		$this->assertStringContainsString( 'Simulated error', $this->captured_notices[0]['message'] );
		$this->assertSame( E_USER_NOTICE, $this->captured_notices[0]['level'] );
	}

	/**
	 * Test that designsetgo_uninstall_step does not trigger notice on success.
	 */
	public function test_uninstall_step_no_notice_on_success() {
		if ( ! function_exists( 'designsetgo_uninstall_step' ) ) {
			$this->markTestSkipped( 'designsetgo_uninstall_step not loaded — run uninstall-test.php first or load uninstall.php separately.' );
			return;
		}

		designsetgo_uninstall_step(
			'success step',
			function () {
				// No-op — success.
			}
		);

		$this->assertCount( 0, $this->captured_notices );
	}

	// -------------------------------------------------------------------------
	// includes/helpers.php — sanitize functions
	// -------------------------------------------------------------------------

	/**
	 * Test that invalid CSS size returns null (error path executes).
	 */
	public function test_sanitize_css_size_returns_null_on_invalid() {
		$result = @designsetgo_sanitize_css_size( 'not-a-size' );
		$this->assertNull( $result );
	}

	/**
	 * Test that invalid CSS size triggers notice when WP_DEBUG is on.
	 */
	public function test_sanitize_css_size_triggers_notice_on_invalid() {
		if ( ! WP_DEBUG ) {
			$this->markTestSkipped( 'WP_DEBUG is false — wp_trigger_error() bails early.' );
			return;
		}

		@designsetgo_sanitize_css_size( 'not-a-size' );

		$this->assertCount( 1, $this->captured_notices );
		$this->assertStringContainsString( 'Invalid CSS size value rejected', $this->captured_notices[0]['message'] );
		$this->assertStringContainsString( 'not-a-size', $this->captured_notices[0]['message'] );
	}

	/**
	 * Test that valid CSS size does not trigger a notice.
	 */
	public function test_sanitize_css_size_no_notice_on_valid() {
		$result = designsetgo_sanitize_css_size( '24px' );

		$this->assertSame( '24px', $result );
		$this->assertCount( 0, $this->captured_notices );
	}

	/**
	 * Test that invalid CSS color returns null (error path executes).
	 */
	public function test_sanitize_css_color_returns_null_on_invalid() {
		$result = @designsetgo_sanitize_css_color( 'definitely-not-a-color' );
		$this->assertNull( $result );
	}

	/**
	 * Test that invalid CSS color triggers notice when WP_DEBUG is on.
	 */
	public function test_sanitize_css_color_triggers_notice_on_invalid() {
		if ( ! WP_DEBUG ) {
			$this->markTestSkipped( 'WP_DEBUG is false — wp_trigger_error() bails early.' );
			return;
		}

		@designsetgo_sanitize_css_color( 'definitely-not-a-color' );

		$this->assertCount( 1, $this->captured_notices );
		$this->assertStringContainsString( 'Invalid CSS color value rejected', $this->captured_notices[0]['message'] );
		$this->assertStringContainsString( 'definitely-not-a-color', $this->captured_notices[0]['message'] );
	}

	/**
	 * Test that valid CSS color does not trigger a notice.
	 */
	public function test_sanitize_css_color_no_notice_on_valid() {
		$result = designsetgo_sanitize_css_color( '#ff0000' );

		$this->assertSame( '#ff0000', $result );
		$this->assertCount( 0, $this->captured_notices );
	}

	// -------------------------------------------------------------------------
	// includes/core/class-assets.php — editor/frontend asset loading
	// -------------------------------------------------------------------------

	/**
	 * Test that enqueue_editor_assets works without fatal when build exists.
	 */
	public function test_editor_assets_enqueues_when_build_exists() {
		$asset_file_path = DESIGNSETGO_PATH . 'build/index.asset.php';
		if ( ! file_exists( $asset_file_path ) ) {
			$this->markTestSkipped( 'Build assets not present — run `npm run build` first.' );
		}

		set_current_screen( 'edit-post' );
		wp_dequeue_script( 'designsetgo-extensions' );
		wp_deregister_script( 'designsetgo-extensions' );

		$assets = new Assets();
		$assets->enqueue_editor_assets();

		$this->assertTrue(
			wp_script_is( 'designsetgo-extensions', 'enqueued' ),
			'Editor script should be enqueued when asset file exists'
		);
		$this->assertCount( 0, $this->captured_notices );

		set_current_screen( 'front' );
	}

	/**
	 * Test that enqueue_editor_assets returns early (no fatal) in non-admin.
	 */
	public function test_editor_assets_skips_non_admin() {
		set_current_screen( 'front' );
		wp_dequeue_script( 'designsetgo-extensions' );
		wp_deregister_script( 'designsetgo-extensions' );

		$assets = new Assets();
		$assets->enqueue_editor_assets();

		$this->assertFalse(
			wp_script_is( 'designsetgo-extensions', 'enqueued' ),
			'Editor script should not be enqueued outside admin'
		);
		$this->assertCount( 0, $this->captured_notices );
	}

	/**
	 * Test that register_frontend_assets works without fatal when build exists.
	 */
	public function test_frontend_assets_registers_when_build_exists() {
		$frontend_asset_path = DESIGNSETGO_PATH . 'build/frontend.asset.php';
		if ( ! file_exists( $frontend_asset_path ) ) {
			$this->markTestSkipped( 'Build assets not present — run `npm run build` first.' );
		}

		wp_deregister_script( 'designsetgo-frontend' );

		$assets = new Assets();
		$assets->register_frontend_assets();

		$this->assertTrue(
			wp_script_is( 'designsetgo-frontend', 'registered' ),
			'Frontend script should be registered when asset file exists'
		);
		$this->assertCount( 0, $this->captured_notices );
	}

	// -------------------------------------------------------------------------
	// includes/patterns/class-loader.php — pattern discovery
	// -------------------------------------------------------------------------

	/**
	 * Test that is_valid_relative_path rejects traversal paths.
	 */
	public function test_patterns_is_valid_relative_path_rejects_traversal() {
		$reflection = new ReflectionClass( Loader::class );
		$method     = $reflection->getMethod( 'is_valid_relative_path' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( null, '../evil/file.php' ) );
		$this->assertFalse( $method->invoke( null, 'hero/../../etc/passwd.php' ) );
		$this->assertFalse( $method->invoke( null, '/absolute/path.php' ) );
		$this->assertFalse( $method->invoke( null, '' ) );
		$this->assertFalse( $method->invoke( null, 'no-extension' ) );
	}

	/**
	 * Test that is_valid_relative_path accepts valid paths.
	 */
	public function test_patterns_is_valid_relative_path_accepts_valid() {
		$reflection = new ReflectionClass( Loader::class );
		$method     = $reflection->getMethod( 'is_valid_relative_path' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null, 'hero/my-pattern.php' ) );
		$this->assertTrue( $method->invoke( null, 'cta/landing-page.php' ) );
	}

	/**
	 * Test that get_category_patterns returns empty array for non-existent category.
	 */
	public function test_patterns_returns_empty_for_nonexistent_category() {
		$loader     = new Loader();
		$reflection = new ReflectionClass( $loader );
		$method     = $reflection->getMethod( 'get_category_patterns' );
		$method->setAccessible( true );

		$result = $method->invoke( $loader, 'nonexistent_category_xyz' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
		$this->assertCount( 0, $this->captured_notices, 'No notice for non-existent directory (returns early before glob)' );
	}

	/**
	 * Test that get_category_patterns loads patterns from valid directory.
	 */
	public function test_patterns_loads_from_valid_category() {
		$patterns_dir = DESIGNSETGO_PATH . 'patterns/hero/';
		if ( ! is_dir( $patterns_dir ) ) {
			$this->markTestSkipped( 'Hero patterns directory not present.' );
		}

		$loader     = new Loader();
		$reflection = new ReflectionClass( $loader );
		$method     = $reflection->getMethod( 'get_category_patterns' );
		$method->setAccessible( true );

		$result = $method->invoke( $loader, 'hero' );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result, 'Should find at least one hero pattern' );

		// No error notices should fire.
		$this->assertCount( 0, $this->captured_notices, 'No error notices for valid pattern directory' );
	}

	// -------------------------------------------------------------------------
	// includes/admin/class-global-styles.php — admin page rendering
	// -------------------------------------------------------------------------

	/**
	 * Test that render_admin_page works without fatal when build exists.
	 */
	public function test_admin_page_enqueues_when_build_exists() {
		$asset_file_path = DESIGNSETGO_PATH . 'build/admin.asset.php';
		if ( ! file_exists( $asset_file_path ) ) {
			$this->markTestSkipped( 'Admin build assets not present — run `npm run build` first.' );
		}

		// Grant admin capability.
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		wp_dequeue_script( 'designsetgo-admin' );
		wp_deregister_script( 'designsetgo-admin' );

		$global_styles = new \DesignSetGo\Admin\Global_Styles();

		ob_start();
		$global_styles->render_admin_page();
		ob_end_clean();

		$this->assertTrue(
			wp_script_is( 'designsetgo-admin', 'enqueued' ),
			'Admin script should be enqueued when asset file exists'
		);
		$this->assertCount( 0, $this->captured_notices );
	}
}
