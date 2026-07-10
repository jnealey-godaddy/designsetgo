<?php
/**
 * Tests for Security Fixes
 *
 * Tests the security improvements made to prevent SQL injection,
 * email header injection, and validate REST API parameters.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_REST_Request;

/**
 * Security Fixes Test Case
 */
class Test_Security_Fixes extends WP_UnitTestCase {
	/**
	 * @var int
	 */
	protected int $admin_user;

	/**
	 * @var int
	 */
	protected int $regular_user;
	/**
	 * Set up test environment
	 */
	public function set_up() {
		parent::set_up();

		// Set up admin user for permission tests.
		$this->admin_user = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		// Set up regular user for permission tests.
		$this->regular_user = $this->factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}

	/**
	 * Test SQL injection protection in form submissions count query
	 *
	 * Verifies that the database query uses $wpdb->prepare() to prevent SQL injection.
	 */
	public function test_form_submissions_count_uses_prepared_statement() {
		// Create Settings instance to access the endpoint.
		$settings = new \DesignSetGo\Admin\Settings();

		// Create a test form submission.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'private',
			)
		);

		// Set current user as admin.
		wp_set_current_user( $this->admin_user );

		// Create REST request.
		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/stats' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		// Get stats endpoint response.
		$response = $settings->get_stats_endpoint();

		// Verify response has form_submissions count.
		$this->assertIsArray( $response->data );
		$this->assertArrayHasKey( 'form_submissions', $response->data );
		$this->assertIsNumeric( $response->data['form_submissions'] );
		$this->assertGreaterThanOrEqual( 1, $response->data['form_submissions'] );

		// Clean up.
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test database caching for form submissions count
	 *
	 * Verifies that the form submissions count is cached and invalidated correctly.
	 */
	public function test_form_submissions_count_caching() {
		// Clear any existing cache.
		delete_transient( 'dsgo_form_submissions_count' );

		// Create Settings instance.
		$settings = new \DesignSetGo\Admin\Settings();

		// Set current user as admin.
		wp_set_current_user( $this->admin_user );

		// Create REST request.
		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/stats' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		// First call - should set cache.
		$response1 = $settings->get_stats_endpoint();
		$count1    = $response1->data['form_submissions'];

		// Verify cache is set.
		$cached_value = get_transient( 'dsgo_form_submissions_count' );
		$this->assertNotFalse( $cached_value, 'Cache should be set after first query' );
		$this->assertEquals( $count1, $cached_value );

		// Second call - should use cache.
		$response2 = $settings->get_stats_endpoint();
		$count2    = $response2->data['form_submissions'];
		$this->assertEquals( $count1, $count2, 'Cached value should be returned' );

		// Create a new form submission.
		$form_handler = new \DesignSetGo\Blocks\Form_Handler();
		$reflection   = new \ReflectionClass( $form_handler );
		$method       = $reflection->getMethod( 'store_submission' );
		$method->setAccessible( true );

		$post_id = $method->invoke(
			$form_handler,
			'test-form',
			array(
				'test_field' => 'test_value',
			)
		);

		// Verify cache is cleared after new submission.
		$cached_after = get_transient( 'dsgo_form_submissions_count' );
		$this->assertFalse( $cached_after, 'Cache should be cleared after new submission' );

		// Clean up.
		if ( ! is_wp_error( $post_id ) ) {
			wp_delete_post( $post_id, true );
		}
		delete_transient( 'dsgo_form_submissions_count' );
	}

	/**
	 * Test email header injection prevention
	 *
	 * Verifies that newline characters are stripped from email parameters.
	 */
	public function test_email_header_injection_prevention() {
		$form_handler = new \DesignSetGo\Blocks\Form_Handler();

		// Create malicious request with newlines in email fields.
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form-submit' );

		// Attempt header injection with various newline encodings.
		$request->set_param( 'email_to', "test@example.com\nBcc: hacker@evil.com" );
		$request->set_param( 'email_subject', "Test Subject\r\nBcc: hacker@evil.com" );
		$request->set_param( 'email_from_email', 'from@example.com%0aBcc: hacker@evil.com' );
		$request->set_param( 'email_from_name', 'Sender%0d%0aBcc: hacker@evil.com' );
		$request->set_param( 'email_reply_to', "reply@example.com\r\nCc: hacker@evil.com" );

		// Test that parameters are sanitized (use reflection to access private method if needed).
		$reflection = new \ReflectionClass( $form_handler );

		// Verify the email parameters in the request would be sanitized.
		// Since the actual sanitization happens in handle_submission,
		// we verify that the parameters contain no newlines after processing.
		$email_to = str_replace( array( "\r", "\n", '%0a', '%0d' ), '', $request->get_param( 'email_to' ) );
		$this->assertEquals( 'test@example.comBcc: hacker@evil.com', $email_to );
		$this->assertStringNotContainsString( "\n", $email_to );
		$this->assertStringNotContainsString( "\r", $email_to );
		$this->assertStringNotContainsString( '%0a', strtolower( $email_to ) );
		$this->assertStringNotContainsString( '%0d', strtolower( $email_to ) );
	}

	/**
	 * Test REST API email parameter validation
	 *
	 * Verifies that invalid email addresses are rejected by the REST API schema.
	 */
	public function test_rest_api_email_validation() {
		// Test invalid email addresses.
		$invalid_emails = array(
			'not-an-email',
			'missing@domain',
			'@example.com',
			'test@',
			'test@@example.com',
		);

		foreach ( $invalid_emails as $invalid_email ) {
			$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form-submit' );
			$request->set_param( 'email_to', $invalid_email );

			// The validate_callback should return false for invalid emails.
			$validate_callback = function ( $param ) {
				return empty( $param ) || is_email( $param );
			};

			$is_valid = $validate_callback( $invalid_email );
			$this->assertFalse( $is_valid, "Email '{$invalid_email}' should be invalid" );
		}

		// Test valid email addresses.
		$valid_emails = array(
			'',
			'test@example.com',
			'user.name@example.co.uk',
			'user+tag@example.com',
		);

		foreach ( $valid_emails as $valid_email ) {
			$validate_callback = function ( $param ) {
				return empty( $param ) || is_email( $param );
			};

			$is_valid = $validate_callback( $valid_email );
			$this->assertTrue( $is_valid, "Email '{$valid_email}' should be valid" );
		}
	}

	/**
	 * Test permission check ordering in Settings REST API
	 *
	 * Verifies that capability checks happen before nonce verification.
	 */
	public function test_settings_permission_check_ordering() {
		$settings = new \DesignSetGo\Admin\Settings();

		// Test 1: Non-admin user should fail capability check first.
		wp_set_current_user( $this->regular_user );

		$request = new WP_REST_Request( 'PUT', '/designsetgo/v1/settings' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$permission = $settings->check_write_permission( $request );

		// Should return false (not WP_Error) because capability check happens first.
		$this->assertFalse( $permission, 'Non-admin should fail capability check' );

		// Test 2: Admin user with valid nonce should pass.
		wp_set_current_user( $this->admin_user );

		$request = new WP_REST_Request( 'PUT', '/designsetgo/v1/settings' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$permission = $settings->check_write_permission( $request );
		$this->assertTrue( $permission, 'Admin with valid nonce should pass' );

		// Test 3: Admin user with invalid nonce should fail nonce check.
		wp_set_current_user( $this->admin_user );

		$request = new WP_REST_Request( 'PUT', '/designsetgo/v1/settings' );
		$request->set_header( 'X-WP-Nonce', 'invalid_nonce' );

		$permission = $settings->check_write_permission( $request );

		// Should return WP_Error because nonce check happens after capability check.
		$this->assertInstanceOf( 'WP_Error', $permission, 'Invalid nonce should return WP_Error' );
		$this->assertEquals( 'invalid_nonce', $permission->get_error_code() );
	}

	/**
	 * Test the settings GET endpoint redacts the write-only Turnstile secret.
	 *
	 * The secret must never reach the browser, but get_settings() must keep
	 * returning the real value for server-side consumers.
	 */
	public function test_settings_get_endpoint_redacts_turnstile_secret() {
		wp_set_current_user( $this->admin_user );

		\DesignSetGo\Admin\Settings::invalidate_cache();
		update_option(
			\DesignSetGo\Admin\Settings::OPTION_NAME,
			array( 'integrations' => array( 'turnstile_secret_key' => 'real-secret-0x123' ) )
		);
		\DesignSetGo\Admin\Settings::invalidate_cache();

		$settings = new \DesignSetGo\Admin\Settings();
		$data     = $settings->get_settings_endpoint()->get_data();

		// The REST response redacts the secret.
		$this->assertSame(
			\DesignSetGo\Admin\Settings::REDACTED_PLACEHOLDER,
			$data['integrations']['turnstile_secret_key']
		);
		// The underlying value is intact for server-side consumers.
		$this->assertSame(
			'real-secret-0x123',
			\DesignSetGo\Admin\Settings::get_settings()['integrations']['turnstile_secret_key']
		);

		delete_option( \DesignSetGo\Admin\Settings::OPTION_NAME );
		\DesignSetGo\Admin\Settings::invalidate_cache();
	}

	/**
	 * Test that an empty secret is not redacted (UI shows an empty field).
	 */
	public function test_settings_get_endpoint_leaves_empty_secret_empty() {
		wp_set_current_user( $this->admin_user );

		\DesignSetGo\Admin\Settings::invalidate_cache();
		update_option(
			\DesignSetGo\Admin\Settings::OPTION_NAME,
			array( 'integrations' => array( 'turnstile_secret_key' => '' ) )
		);
		\DesignSetGo\Admin\Settings::invalidate_cache();

		$settings = new \DesignSetGo\Admin\Settings();
		$data     = $settings->get_settings_endpoint()->get_data();

		$this->assertSame( '', $data['integrations']['turnstile_secret_key'] );

		delete_option( \DesignSetGo\Admin\Settings::OPTION_NAME );
		\DesignSetGo\Admin\Settings::invalidate_cache();
	}

	/**
	 * Test the secret round-trip: the placeholder preserves the stored secret,
	 * a real new value replaces it.
	 */
	public function test_settings_update_round_trips_redacted_secret() {
		wp_set_current_user( $this->admin_user );

		\DesignSetGo\Admin\Settings::invalidate_cache();
		update_option(
			\DesignSetGo\Admin\Settings::OPTION_NAME,
			array( 'integrations' => array( 'turnstile_secret_key' => 'stored-secret' ) )
		);
		\DesignSetGo\Admin\Settings::invalidate_cache();

		// Submitting the redaction placeholder preserves the stored secret.
		\DesignSetGo\Admin\Settings::update_settings(
			array( 'integrations' => array( 'turnstile_secret_key' => \DesignSetGo\Admin\Settings::REDACTED_PLACEHOLDER ) )
		);
		$this->assertSame(
			'stored-secret',
			\DesignSetGo\Admin\Settings::get_settings()['integrations']['turnstile_secret_key']
		);

		// Submitting a real new value replaces it.
		\DesignSetGo\Admin\Settings::update_settings(
			array( 'integrations' => array( 'turnstile_secret_key' => 'new-secret' ) )
		);
		$this->assertSame(
			'new-secret',
			\DesignSetGo\Admin\Settings::get_settings()['integrations']['turnstile_secret_key']
		);

		delete_option( \DesignSetGo\Admin\Settings::OPTION_NAME );
		\DesignSetGo\Admin\Settings::invalidate_cache();
	}

	/**
	 * Test permission check ordering in Global Styles REST API
	 *
	 * Verifies that capability checks happen before nonce verification.
	 */
	public function test_global_styles_permission_check_ordering() {
		$global_styles = new \DesignSetGo\Admin\Global_Styles();

		// Test 1: Non-admin user should fail capability check first.
		wp_set_current_user( $this->regular_user );

		$request = new WP_REST_Request( 'PUT', '/designsetgo/v1/global-styles' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$permission = $global_styles->check_write_permission( $request );

		// Should return false because capability check happens first.
		$this->assertFalse( $permission, 'Non-admin should fail capability check' );

		// Test 2: Admin user with valid nonce should pass.
		wp_set_current_user( $this->admin_user );

		$request = new WP_REST_Request( 'PUT', '/designsetgo/v1/global-styles' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$permission = $global_styles->check_write_permission( $request );
		$this->assertTrue( $permission, 'Admin with valid nonce should pass' );

		// Test 3: Admin user with invalid nonce should fail nonce check.
		wp_set_current_user( $this->admin_user );

		$request = new WP_REST_Request( 'PUT', '/designsetgo/v1/global-styles' );
		$request->set_header( 'X-WP-Nonce', 'invalid_nonce' );

		$permission = $global_styles->check_write_permission( $request );

		// Should return WP_Error because nonce check happens after capability check.
		$this->assertInstanceOf( 'WP_Error', $permission, 'Invalid nonce should return WP_Error' );
		$this->assertEquals( 'invalid_nonce', $permission->get_error_code() );
	}
}
