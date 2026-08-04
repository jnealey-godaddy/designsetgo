<?php
/**
 * Tests for extension settings localization in the block editor iframe.
 *
 * The `designsetgo-extensions` script (which registers every DSG block
 * extension via `blocks.registerBlockType`) is enqueued on the
 * `enqueue_block_assets` hook because, in the block editor, that hook is the
 * only one that fires inside the iframed canvas (`_wp_get_iframed_editor_assets()`)
 * where the extensions actually run. The `dsgoSettings` payload — which carries
 * the user's `excluded_blocks` list consumed by `shouldExtendBlock()` — must be
 * localized onto that same script, on that same hook, or it never reaches the
 * iframe and exclusions are silently ignored.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\Admin\Settings;

/**
 * Extension Settings Localization Test Case
 */
class Test_Extension_Settings_Localize extends WP_UnitTestCase {

	/**
	 * Decode the JSON object out of a `var dsgoSettings = {...};` payload.
	 *
	 * Assert against the decoded array rather than searching the raw string:
	 * `wp_localize_script()` runs the payload through `wp_json_encode()`, and
	 * whether that escapes a forward slash (`gravityforms\/form`) or leaves it
	 * bare varies by WordPress version. A substring search for a value
	 * containing `/` therefore passes on some versions of WP and fails on
	 * others for reasons that have nothing to do with the behaviour under test.
	 *
	 * WP_Scripts::localize() appends rather than replaces
	 * (`$script = "$data\n$script"`), so a handle localized more than once
	 * carries several newline-joined `var … = {…};` statements. Decode the last
	 * dsgoSettings one — that is the payload the current test produced.
	 *
	 * @param string $data Localized script data.
	 * @return array Decoded settings payload.
	 */
	private function decode_localized_settings( $data ) {
		$prefix = 'var dsgoSettings = ';

		$statements = array_filter(
			array_map( 'trim', explode( "\n", $data ) ),
			static function ( $line ) use ( $prefix ) {
				return 0 === strpos( $line, $prefix );
			}
		);

		$this->assertNotEmpty(
			$statements,
			'Expected a dsgoSettings statement in the localized payload.'
		);

		$json    = rtrim( substr( (string) end( $statements ), strlen( $prefix ) ), ';' );
		$decoded = json_decode( $json, true );

		$this->assertIsArray( $decoded, 'The dsgoSettings payload should decode as JSON.' );

		return $decoded;
	}

	/**
	 * Reset scripts and screen after each test.
	 */
	public function tear_down() {
		wp_dequeue_script( 'designsetgo-extensions' );
		set_current_screen( 'front' );
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	/**
	 * The excluded_blocks list must reach the iframe extensions script.
	 *
	 * Fires the same hook the iframed editor canvas uses and asserts the
	 * enqueued extensions script carries the configured exclusions in its
	 * localized `dsgoSettings` payload.
	 */
	public function test_excluded_blocks_localized_onto_extensions_script() {
		// Admin/editor context so Assets::enqueue_editor_assets() runs.
		set_current_screen( 'edit-post' );

		// Persist an explicit exclusion the reporter would configure.
		// excluded_blocks is a top-level key, so it wins the wp_parse_args
		// merge in get_settings() regardless of the packaged defaults.
		update_option(
			Settings::OPTION_NAME,
			array( 'excluded_blocks' => array( 'gravityforms/form' ) )
		);
		Settings::invalidate_cache();

		// The block editor fires this hook inside the iframed canvas.
		do_action( 'enqueue_block_assets' );

		$this->assertTrue(
			wp_script_is( 'designsetgo-extensions', 'enqueued' ),
			'The extensions script should be enqueued in the editor iframe context.'
		);

		$data = wp_scripts()->get_data( 'designsetgo-extensions', 'data' );

		$this->assertIsString(
			$data,
			'dsgoSettings must be localized on the same hook that enqueues the extensions script.'
		);
		$this->assertStringContainsString( 'dsgoSettings', $data );

		$settings = $this->decode_localized_settings( $data );

		$this->assertArrayHasKey( 'excludedBlocks', $settings );
		$this->assertContains(
			'gravityforms/form',
			$settings['excludedBlocks'],
			'The configured excluded block must be present in the iframe payload.'
		);
	}

	/**
	 * The enabled-extensions allowlist must reach the iframe too.
	 *
	 * The same localization bug also disabled per-extension gating (e.g.
	 * dynamic-tags, which reads window.dsgoSettings.enabledExtensions inside
	 * the iframe). Cover it so a regression in either field is caught.
	 */
	public function test_enabled_extensions_localized_onto_extensions_script() {
		set_current_screen( 'edit-post' );

		update_option(
			Settings::OPTION_NAME,
			array( 'enabled_extensions' => array( 'dynamic-tags' ) )
		);
		Settings::invalidate_cache();

		do_action( 'enqueue_block_assets' );

		$data = wp_scripts()->get_data( 'designsetgo-extensions', 'data' );

		$this->assertIsString(
			$data,
			'dsgoSettings must be localized on the same hook that enqueues the extensions script.'
		);
		$settings = $this->decode_localized_settings( $data );

		$this->assertArrayHasKey( 'enabledExtensions', $settings );
		$this->assertContains(
			'dynamic-tags',
			$settings['enabledExtensions'],
			'The configured enabled extension must be present in the iframe payload.'
		);
	}
}
