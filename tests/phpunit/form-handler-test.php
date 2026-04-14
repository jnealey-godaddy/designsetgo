<?php
/**
 * Tests for Form Handler Security
 *
 * Tests the form submission REST API endpoint for security measures:
 * - Field validation and sanitization
 * - Rate limiting
 * - Honeypot detection
 * - Email header injection prevention
 * - IP address extraction
 *
 * These tests focus on security-critical behavior rather than implementation.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use DesignSetGo\Blocks\Form_Handler;
use DesignSetGo\Blocks\Form_Security;
use ReflectionClass;
use ReflectionMethod;

/**
 * Form Handler Test Case
 */
class Test_Form_Handler extends WP_UnitTestCase {

	/**
	 * Form handler instance.
	 *
	 * @var Form_Handler
	 */
	private $handler;

	/**
	 * Form security instance.
	 *
	 * @var Form_Security
	 */
	private $security;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$this->handler  = new Form_Handler();
		$this->security = new Form_Security();

		// Register the REST route for testing.
		do_action( 'rest_api_init' );
	}

	/**
	 * Helper to call private methods on Form_Handler for testing.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @return mixed Method result.
	 */
	private function call_private_method( $method, $args = array() ) {
		$reflection = new ReflectionClass( $this->handler );
		$method     = $reflection->getMethod( $method );
		$method->setAccessible( true );
		return $method->invokeArgs( $this->handler, $args );
	}

	/**
	 * Helper to call private methods on Form_Security for testing.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @return mixed Method result.
	 */
	private function call_security_method( $method, $args = array() ) {
		$reflection = new ReflectionClass( $this->security );
		$method     = $reflection->getMethod( $method );
		$method->setAccessible( true );
		return $method->invokeArgs( $this->security, $args );
	}

	/**
	 * Test validate_field with email type - valid emails.
	 */
	public function test_validate_field_email_valid() {
		$valid_emails = array(
			'test@example.com',
			'user.name@domain.org',
			'user+tag@example.co.uk',
		);

		foreach ( $valid_emails as $email ) {
			$result = $this->call_private_method( 'validate_field', array( $email, 'email' ) );
			$this->assertTrue( $result, "Email '$email' should be valid" );
		}
	}

	/**
	 * Test validate_field with email type - invalid emails.
	 */
	public function test_validate_field_email_invalid() {
		$invalid_emails = array(
			'notanemail',
			'missing@',
			'@nodomain.com',
			'spaces in@email.com',
		);

		foreach ( $invalid_emails as $email ) {
			$result = $this->call_private_method( 'validate_field', array( $email, 'email' ) );
			$this->assertInstanceOf( WP_Error::class, $result, "Email '$email' should be invalid" );
			$this->assertEquals( 'invalid_email', $result->get_error_code() );
		}
	}

	/**
	 * Test validate_field with URL type - valid URLs.
	 */
	public function test_validate_field_url_valid() {
		$valid_urls = array(
			'https://example.com',
			'http://subdomain.example.org/path',
			'https://example.com:8080/page?query=value',
		);

		foreach ( $valid_urls as $url ) {
			$result = $this->call_private_method( 'validate_field', array( $url, 'url' ) );
			$this->assertTrue( $result, "URL '$url' should be valid" );
		}
	}

	/**
	 * Test validate_field with URL type - invalid URLs.
	 */
	public function test_validate_field_url_invalid() {
		$invalid_urls = array(
			'not-a-url',
			'ftp://example.com', // filter_var rejects non-http(s).
			'javascript:alert(1)',
		);

		foreach ( $invalid_urls as $url ) {
			$result = $this->call_private_method( 'validate_field', array( $url, 'url' ) );
			$this->assertInstanceOf( WP_Error::class, $result, "URL '$url' should be invalid" );
		}
	}

	/**
	 * Test validate_field with number type.
	 */
	public function test_validate_field_number() {
		// Valid numbers.
		$this->assertTrue( $this->call_private_method( 'validate_field', array( '123', 'number' ) ) );
		$this->assertTrue( $this->call_private_method( 'validate_field', array( '-45.67', 'number' ) ) );
		$this->assertTrue( $this->call_private_method( 'validate_field', array( '0', 'number' ) ) );

		// Invalid numbers.
		$result = $this->call_private_method( 'validate_field', array( 'abc', 'number' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_number', $result->get_error_code() );
	}

	/**
	 * Test validate_field with phone type.
	 */
	public function test_validate_field_phone() {
		// Valid phone numbers.
		$valid_phones = array(
			'+1 (555) 123-4567',
			'555-123-4567',
			'5551234567',
			'+44 20 7946 0958',
		);

		foreach ( $valid_phones as $phone ) {
			$result = $this->call_private_method( 'validate_field', array( $phone, 'tel' ) );
			$this->assertTrue( $result, "Phone '$phone' should be valid" );
		}

		// Invalid phone numbers (containing letters or special chars).
		$result = $this->call_private_method( 'validate_field', array( 'call-me-maybe', 'tel' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_phone', $result->get_error_code() );
	}

	/**
	 * Test validate_field allows empty values (optional fields).
	 */
	public function test_validate_field_empty_allowed() {
		// Empty values should pass validation (required is handled on frontend).
		$this->assertTrue( $this->call_private_method( 'validate_field', array( '', 'email' ) ) );
		$this->assertTrue( $this->call_private_method( 'validate_field', array( '', 'url' ) ) );
		$this->assertTrue( $this->call_private_method( 'validate_field', array( '', 'number' ) ) );
	}

	/**
	 * Test sanitize_field for different types.
	 */
	public function test_sanitize_field_types() {
		// Email sanitization.
		$this->assertEquals(
			'test@example.com',
			$this->call_private_method( 'sanitize_field', array( 'test@example.com', 'email' ) )
		);

		// Number sanitization.
		$this->assertEquals(
			123.45,
			$this->call_private_method( 'sanitize_field', array( '123.45', 'number' ) )
		);

		// Phone sanitization - removes invalid characters.
		$this->assertEquals(
			'+1 (555) 123-4567',
			$this->call_private_method( 'sanitize_field', array( '+1 (555) 123-4567', 'tel' ) )
		);

		// Text sanitization.
		$this->assertEquals(
			'Hello World',
			$this->call_private_method( 'sanitize_field', array( 'Hello World', 'text' ) )
		);
	}

	/**
	 * Test sanitize_field strips HTML from text.
	 */
	public function test_sanitize_field_strips_html() {
		$malicious = '<script>alert("xss")</script>Hello';
		$result    = $this->call_private_method( 'sanitize_field', array( $malicious, 'text' ) );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( '</script>', $result );
	}

	/**
	 * Test sanitize_field handles null/empty gracefully.
	 */
	public function test_sanitize_field_empty_values() {
		$this->assertEquals( '', $this->call_private_method( 'sanitize_field', array( '', 'text' ) ) );
	}

	/**
	 * Test IP address extraction defaults to REMOTE_ADDR.
	 */
	public function test_get_client_ip_default() {
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

		// Clear any proxy headers.
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		unset( $_SERVER['HTTP_X_REAL_IP'] );

		$ip = $this->security->get_client_ip();

		$this->assertEquals( '192.168.1.100', $ip );
	}

	/**
	 * Test IP address returns 'unknown' when not available.
	 */
	public function test_get_client_ip_unknown() {
		unset( $_SERVER['REMOTE_ADDR'] );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$ip = $this->security->get_client_ip();

		$this->assertEquals( 'unknown', $ip );

		// Restore for other tests.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
	}

	/**
	 * Test IP address ignores proxy headers without trusted proxy config.
	 */
	public function test_get_client_ip_ignores_untrusted_proxy() {
		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		// Without trusted proxies filter, should use REMOTE_ADDR.
		$ip = $this->security->get_client_ip();

		$this->assertEquals( '10.0.0.1', $ip );
	}

	/**
	 * Test CIDR range matching.
	 */
	public function test_ip_in_range() {
		// IP in range.
		$this->assertTrue(
			$this->call_security_method( 'ip_in_range', array( '192.168.1.50', '192.168.1.0/24' ) )
		);

		// IP outside range.
		$this->assertFalse(
			$this->call_security_method( 'ip_in_range', array( '192.168.2.50', '192.168.1.0/24' ) )
		);

		// Exact match with /32.
		$this->assertTrue(
			$this->call_security_method( 'ip_in_range', array( '10.0.0.5', '10.0.0.5/32' ) )
		);
	}

	/**
	 * Test trusted proxy detection.
	 */
	public function test_is_trusted_proxy() {
		$trusted_proxies = array(
			'10.0.0.1',
			'192.168.0.0/16',
		);

		// Exact match.
		$this->assertTrue(
			$this->call_security_method( 'is_trusted_proxy', array( '10.0.0.1', $trusted_proxies ) )
		);

		// CIDR match.
		$this->assertTrue(
			$this->call_security_method( 'is_trusted_proxy', array( '192.168.50.100', $trusted_proxies ) )
		);

		// Not trusted.
		$this->assertFalse(
			$this->call_security_method( 'is_trusted_proxy', array( '203.0.113.1', $trusted_proxies ) )
		);
	}

	/**
	 * Test rate limiting allows first submission.
	 */
	public function test_rate_limit_first_submission() {
		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';

		$result = $this->security->check_rate_limit( 'test-form-1' );

		$this->assertTrue( $result );
	}

	/**
	 * Test rate limiting allows submissions under limit.
	 */
	public function test_rate_limit_under_limit() {
		$_SERVER['REMOTE_ADDR'] = '192.168.1.2';
		$form_id                = 'test-form-2';

		// First two submissions should be allowed (increment after check).
		for ( $i = 0; $i < 2; $i++ ) {
			$result = $this->security->check_rate_limit( $form_id );
			$this->assertTrue( $result, "Submission $i should be allowed" );
			$this->security->increment_rate_limit( $form_id );
		}
	}

	/**
	 * Test rate limiting blocks after limit exceeded.
	 */
	public function test_rate_limit_exceeded() {
		$_SERVER['REMOTE_ADDR'] = '192.168.1.3';
		$form_id                = 'test-form-3';
		$ip_hash                = md5( '192.168.1.3' );
		$key                    = 'form_submit_' . $form_id . '_' . $ip_hash;

		// Set transient to max submissions (3 by default).
		set_transient( $key, 3, 60 );

		$result = $this->security->check_rate_limit( $form_id );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'rate_limit', $result->get_error_code() );
	}

	/**
	 * Test form settings defaults.
	 */
	public function test_get_form_settings_defaults() {
		// Clear any existing settings.
		delete_option( 'designsetgo_settings' );

		$settings = $this->call_private_method( 'get_form_settings', array() );

		$this->assertTrue( $settings['enable_honeypot'] );
		$this->assertTrue( $settings['enable_rate_limiting'] );
		$this->assertEquals( 30, $settings['retention_days'] );
	}

	/**
	 * Test cleanup respects retention_days setting.
	 */
	public function test_cleanup_respects_retention() {
		// Set retention to 0 (disabled).
		update_option(
			'designsetgo_settings',
			array(
				'forms' => array(
					'retention_days' => 0,
				),
			)
		);

		// Create an old submission.
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'private',
				'post_title'  => 'Test Submission',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			)
		);

		$this->assertNotWPError( $post_id );

		// Run cleanup.
		$this->handler->cleanup_old_submissions();

		// Submission should still exist (retention disabled).
		$post = get_post( $post_id );
		$this->assertNotNull( $post );

		// Cleanup.
		wp_delete_post( $post_id, true );
		delete_option( 'designsetgo_settings' );
	}

	/**
	 * Test REST endpoint is registered.
	 */
	public function test_rest_endpoint_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/designsetgo/v1/form/submit', $routes );
	}

	/**
	 * Test REST endpoint requires formId.
	 */
	public function test_rest_endpoint_requires_form_id() {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_body_params(
			array(
				'fields' => array(),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test REST endpoint requires fields array.
	 */
	public function test_rest_endpoint_requires_fields() {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_body_params(
			array(
				'formId' => 'test-form',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test get_form_block_attributes finds form in published post content.
	 */
	public function test_get_form_block_attributes_finds_form() {
		$form_id = 'testattr';
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Test Form Page',
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '","enableEmail":true,"emailTo":"test@example.com"} --><div class="wp-block-designsetgo-form-builder"></div><!-- /wp:designsetgo/form-builder -->',
			)
		);

		$this->assertNotWPError( $post_id );

		// Clear any cached transient from the insert.
		delete_transient( 'dsgo_form_attrs_v2_' . md5( $form_id ) );

		$attrs = $this->call_private_method( 'get_form_block_attributes', array( $form_id ) );

		$this->assertNotNull( $attrs );
		$this->assertEquals( $form_id, $attrs['formId'] );
		$this->assertTrue( $attrs['enableEmail'] );
		$this->assertEquals( 'test@example.com', $attrs['emailTo'] );

		// Cleanup.
		wp_delete_post( $post_id, true );
		delete_transient( 'dsgo_form_attrs_v2_' . md5( $form_id ) );
	}

	/**
	 * Test get_form_block_attributes returns null for non-existent form.
	 */
	public function test_get_form_block_attributes_returns_null_for_missing() {
		$attrs = $this->call_private_method( 'get_form_block_attributes', array( 'nonexistent' ) );
		$this->assertNull( $attrs );
	}

	/**
	 * Test that email configuration from client request is ignored.
	 *
	 * Email settings must come from server-side block attributes, not from
	 * the client request. This prevents attackers from using the form as
	 * an open email relay.
	 */
	public function test_client_email_params_are_ignored() {
		$form_id = 'noemail1';

		// Create form WITHOUT email enabled.
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Test No Email Form',
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '"} --><div class="wp-block-designsetgo-form-builder"></div><!-- /wp:designsetgo/form-builder -->',
			)
		);

		$this->assertNotWPError( $post_id );
		delete_transient( 'dsgo_form_attrs_v2_' . md5( $form_id ) );

		// Submit with client-supplied email params (should be ignored by server).
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_body_params(
			array(
				'formId'       => $form_id,
				'fields'       => array(
					array(
						'name'  => 'test',
						'value' => 'hello',
						'type'  => 'text',
					),
				),
				'enable_email' => true,
				'email_to'     => 'attacker@evil.com',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );

		// Email should NOT have been sent (enableEmail is not set in block attrs).
		$email_sent = get_post_meta( $data['submissionId'], '_dsg_email_sent', true );
		$this->assertEmpty( $email_sent, 'Email must not be sent when enableEmail is false in block attributes' );

		// Cleanup.
		wp_delete_post( $post_id, true );
		wp_delete_post( $data['submissionId'], true );
		delete_transient( 'dsgo_form_attrs_v2_' . md5( $form_id ) );
	}

	/**
	 * Test get_form_field_types reads server-side field definitions.
	 */
	public function test_get_form_field_types_finds_known_fields() {
		$form_id = 'fieldtypes1';
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Test Field Types Form',
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '"} --><div class="wp-block-designsetgo-form-builder"><!-- wp:designsetgo/form-email-field {"fieldName":"email"} /--><!-- wp:designsetgo/form-textarea-field {"fieldName":"message"} /--><!-- wp:designsetgo/form-phone-field {"fieldName":"phone"} /--><!-- wp:designsetgo/form-checkbox-field {"fieldName":"consent"} /--></div><!-- /wp:designsetgo/form-builder -->',
			)
		);

		$this->assertNotWPError( $post_id );
		delete_transient( 'dsgo_form_field_types_' . md5( $form_id ) );

		$field_types = $this->call_private_method( 'get_form_field_types', array( $form_id ) );

		$this->assertEquals(
			array(
				'email'   => 'email',
				'message' => 'textarea',
				'phone'   => 'tel',
				'consent' => 'checkbox',
			),
			$field_types
		);

		wp_delete_post( $post_id, true );
		delete_transient( 'dsgo_form_field_types_' . md5( $form_id ) );
	}

	/**
	 * Test server-side field definitions override client-supplied field types.
	 */
	public function test_server_field_types_override_client_types() {
		$form_id = 'typedoverride1';
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Typed Override Form',
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '"} --><div class="wp-block-designsetgo-form-builder"><!-- wp:designsetgo/form-email-field {"fieldName":"email"} /--></div><!-- /wp:designsetgo/form-builder -->',
			)
		);

		$this->assertNotWPError( $post_id );
		delete_transient( 'dsgo_form_field_types_' . md5( $form_id ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_body_params(
			array(
				'formId' => $form_id,
				'fields' => array(
					array(
						'name'  => 'email',
						'value' => 'not-an-email',
						'type'  => 'text',
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );

		wp_delete_post( $post_id, true );
		delete_transient( 'dsgo_form_field_types_' . md5( $form_id ) );
	}

	/**
	 * Test get_form_block_attributes uses transient cache.
	 */
	public function test_form_block_attributes_are_cached() {
		$form_id   = 'cachetest';
		$cache_key = 'dsgo_form_attrs_v2_' . md5( $form_id );

		// Seed the transient cache directly.
		$fake_attrs = array(
			'formId'      => $form_id,
			'enableEmail' => true,
			'emailTo'     => 'cached@example.com',
		);
		set_transient( $cache_key, $fake_attrs, HOUR_IN_SECONDS );

		// Should return the cached value without hitting the database.
		$attrs = $this->call_private_method( 'get_form_block_attributes', array( $form_id ) );

		$this->assertEquals( $fake_attrs, $attrs );

		// Cleanup.
		delete_transient( $cache_key );
	}
}
