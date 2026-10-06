<?php
/**
 * The webhook signing secret is a write-only setting.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\Admin\Settings;

/**
 * Webhook secret setting test case.
 */
class Test_Form_Webhook_Secret_Setting extends WP_UnitTestCase {

	/**
	 * Reset settings between tests.
	 */
	public function tear_down() {
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	/**
	 * The secret defaults to an empty string.
	 */
	public function test_default_is_empty() {
		Settings::invalidate_cache();
		$this->assertSame( '', Settings::get_settings()['integrations']['form_webhook_secret'] );
	}

	/**
	 * The REST read redacts the secret and a placeholder write keeps it.
	 */
	public function test_secret_is_redacted_on_read_and_kept_on_placeholder_write() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		Settings::update_settings( array( 'integrations' => array( 'form_webhook_secret' => 'abc123' ) ) );
		Settings::invalidate_cache();

		$data = ( new Settings() )->get_settings_endpoint()->get_data();
		$this->assertSame( Settings::REDACTED_PLACEHOLDER, $data['integrations']['form_webhook_secret'] );

		Settings::update_settings( array( 'integrations' => array( 'form_webhook_secret' => Settings::REDACTED_PLACEHOLDER ) ) );
		Settings::invalidate_cache();
		$this->assertSame( 'abc123', Settings::get_settings()['integrations']['form_webhook_secret'] );
	}

	/**
	 * Saving through the REST route redacts the secret in the response too.
	 */
	public function test_rest_save_response_redacts_the_secret() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/settings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body( wp_json_encode( array( 'integrations' => array( 'form_webhook_secret' => 'abc123' ) ) ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Settings::REDACTED_PLACEHOLDER, $response->get_data()['settings']['integrations']['form_webhook_secret'] );
		$this->assertStringNotContainsString( 'abc123', wp_json_encode( $response->get_data() ) );

		Settings::invalidate_cache();
		$this->assertSame( 'abc123', Settings::get_settings()['integrations']['form_webhook_secret'], 'Stored as typed.' );
	}

	/**
	 * The secret is kept verbatim apart from surrounding whitespace and control characters.
	 */
	public function test_secret_is_not_mangled_by_text_sanitizing() {
		$typed = 'ab%41<x>  y&z';
		Settings::update_settings( array( 'integrations' => array( 'form_webhook_secret' => "  {$typed}\t\n" ) ) );
		Settings::invalidate_cache();

		$this->assertSame( $typed, Settings::get_settings()['integrations']['form_webhook_secret'] );
	}
}
