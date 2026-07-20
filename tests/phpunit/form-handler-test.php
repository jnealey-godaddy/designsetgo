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
		$key                    = 'dsgo_form_submit_' . md5( $form_id ) . '_' . $ip_hash;

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
	 * Test that block.json defaults are merged into parsed attributes.
	 *
	 * parse_blocks() only returns attributes serialized into the block comment.
	 * The editor omits attributes whose value equals the block.json default, so
	 * server-side consumers must merge defaults back in. Regression guard for
	 * the "emails never send" bug where enableEmail (default true) was absent
	 * from $block_attrs and the empty() guard bailed before wp_mail().
	 */
	public function test_get_form_block_attributes_merges_block_json_defaults() {
		$form_id = 'defaults1';
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Test Defaults Form',
				// Bare formId only — no enableEmail, no emailSubject, etc.
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '"} --><div class="wp-block-designsetgo-form-builder"></div><!-- /wp:designsetgo/form-builder -->',
			)
		);

		$this->assertNotWPError( $post_id );
		delete_transient( 'dsgo_form_attrs_v2_' . md5( $form_id ) );

		$attrs = $this->call_private_method( 'get_form_block_attributes', array( $form_id ) );

		$this->assertNotNull( $attrs );
		$this->assertArrayHasKey( 'enableEmail', $attrs, 'enableEmail default must be filled in when absent from block comment' );
		$this->assertTrue( $attrs['enableEmail'], 'enableEmail default per block.json is true' );
		$this->assertArrayHasKey( 'emailSubject', $attrs );
		$this->assertEquals( 'New Form Submission', $attrs['emailSubject'] );
		$this->assertArrayHasKey( 'enableHoneypot', $attrs );
		$this->assertTrue( $attrs['enableHoneypot'] );
		$this->assertArrayHasKey( 'enableTurnstile', $attrs );
		$this->assertFalse( $attrs['enableTurnstile'], 'false defaults must also be filled in (not just truthy ones)' );

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

		// Create form with email explicitly disabled. enableEmail must be set to
		// false on the block because its block.json default is true, and
		// apply_form_block_defaults() now fills the default in server-side when
		// the attribute is omitted from the serialized block comment.
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Test No Email Form',
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '","enableEmail":false} --><div class="wp-block-designsetgo-form-builder"></div><!-- /wp:designsetgo/form-builder -->',
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

		// Email should NOT have been sent (enableEmail is explicitly false in block attrs).
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
	 * Test validate_field enforces a server-defined allowed-value list.
	 *
	 * Covers the select/checkbox/hidden enforcement: a value outside the list
	 * is rejected with 'value_not_allowed'; a member passes; empty short-circuits.
	 */
	public function test_validate_field_enforces_allowed_values() {
		$allowed = array( 'option-1', 'option-2' );

		// A member of the list passes.
		$this->assertTrue(
			$this->call_private_method( 'validate_field', array( 'option-1', 'select', $allowed ) )
		);

		// A value outside the list is rejected.
		$result = $this->call_private_method( 'validate_field', array( 'forged', 'select', $allowed ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'value_not_allowed', $result->get_error_code() );

		// A forged hidden-field constant is rejected.
		$hidden = $this->call_private_method( 'validate_field', array( 'tampered', 'hidden', array( 'real-constant' ) ) );
		$this->assertInstanceOf( WP_Error::class, $hidden );
		$this->assertEquals( 'value_not_allowed', $hidden->get_error_code() );

		// Empty values still short-circuit (optional fields), even with a list.
		$this->assertTrue(
			$this->call_private_method( 'validate_field', array( '', 'select', $allowed ) )
		);

		// With no list (null), any value passes the membership gate.
		$this->assertTrue(
			$this->call_private_method( 'validate_field', array( 'anything', 'text', null ) )
		);
	}

	/**
	 * Test get_form_field_value_constraints extracts allowed values per field.
	 */
	public function test_get_form_field_value_constraints_extracts_allowed_values() {
		$form_id = 'constraints1';
		$content = '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '"} --><div class="wp-block-designsetgo-form-builder">'
				. '<!-- wp:designsetgo/form-select-field {"fieldName":"pick","options":[{"label":"A","value":"a"},{"label":"B","value":"b"}]} /-->'
				. '<!-- wp:designsetgo/form-checkbox-field {"fieldName":"consent","value":"yes"} /-->'
				. '<!-- wp:designsetgo/form-hidden-field {"fieldName":"campaign","value":"spring"} /-->'
				. '<!-- wp:designsetgo/form-text-field {"fieldName":"name"} /-->'
				. '</div><!-- /wp:designsetgo/form-builder -->';

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Constraints Form',
				'post_content' => $content,
			)
		);

		$this->assertNotWPError( $post_id );
		delete_transient( 'dsgo_form_field_constraints_' . md5( $form_id ) );

		$constraints = $this->call_private_method( 'get_form_field_value_constraints', array( $form_id ) );

		$this->assertEquals(
			array(
				'pick'     => array( 'a', 'b' ),
				'consent'  => array( 'yes' ),
				'campaign' => array( 'spring' ),
			),
			$constraints
		);
		// Unconstrained text field is absent from the map.
		$this->assertArrayNotHasKey( 'name', $constraints );

		wp_delete_post( $post_id, true );
		delete_transient( 'dsgo_form_field_constraints_' . md5( $form_id ) );
	}

	/**
	 * Test a forged select value is rejected end-to-end via the submit endpoint.
	 */
	public function test_forged_select_value_is_rejected() {
		$form_id = 'forgedselect1';
		$content = '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '"} --><div class="wp-block-designsetgo-form-builder">'
				. '<!-- wp:designsetgo/form-select-field {"fieldName":"pick","options":[{"label":"A","value":"a"},{"label":"B","value":"b"}]} /-->'
				. '</div><!-- /wp:designsetgo/form-builder -->';

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Forged Select Form',
				'post_content' => $content,
			)
		);

		$this->assertNotWPError( $post_id );
		delete_transient( 'dsgo_form_field_types_' . md5( $form_id ) );
		delete_transient( 'dsgo_form_field_constraints_' . md5( $form_id ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_body_params(
			array(
				'formId' => $form_id,
				'fields' => array(
					array(
						'name'  => 'pick',
						'value' => 'c', // Not in the option list.
						'type'  => 'select',
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'validation_error', $response->get_data()['code'] );

		wp_delete_post( $post_id, true );
		delete_transient( 'dsgo_form_field_types_' . md5( $form_id ) );
		delete_transient( 'dsgo_form_field_constraints_' . md5( $form_id ) );
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

	/**
	 * Test cleanup deletes old submissions when retention is enabled.
	 */
	public function test_cleanup_deletes_old_submissions() {
		update_option(
			'designsetgo_settings',
			array(
				'forms' => array(
					'retention_days' => 30,
				),
			)
		);

		// Create an old submission (older than retention period).
		$old_post_id = wp_insert_post(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'private',
				'post_title'  => 'Old Submission',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			)
		);

		// Create a recent submission (within retention period).
		$new_post_id = wp_insert_post(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'private',
				'post_title'  => 'New Submission',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) ),
			)
		);

		$this->assertNotWPError( $old_post_id );
		$this->assertNotWPError( $new_post_id );

		$this->handler->cleanup_old_submissions();

		// Old submission should be deleted.
		$this->assertNull( get_post( $old_post_id ) );

		// Recent submission should still exist.
		$this->assertNotNull( get_post( $new_post_id ) );

		// Cleanup.
		wp_delete_post( $new_post_id, true );
		delete_option( 'designsetgo_settings' );
	}

	/**
	 * Test cleanup respects batch size filter.
	 */
	public function test_cleanup_respects_batch_size() {
		update_option(
			'designsetgo_settings',
			array(
				'forms' => array(
					'retention_days' => 30,
				),
			)
		);

		// Create 3 old submissions.
		$post_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$post_ids[] = wp_insert_post(
				array(
					'post_type'   => 'dsgo_form_submission',
					'post_status' => 'private',
					'post_title'  => "Old Submission {$i}",
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
				)
			);
		}

		// Limit batch to 2.
		add_filter( 'designsetgo_cleanup_batch_size', function () {
			return 2;
		} );

		$this->handler->cleanup_old_submissions();

		// Only 2 should be deleted (batch limit).
		$remaining = 0;
		foreach ( $post_ids as $post_id ) {
			if ( null !== get_post( $post_id ) ) {
				++$remaining;
			}
		}
		$this->assertEquals( 1, $remaining );

		// Cleanup.
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		delete_option( 'designsetgo_settings' );
	}

	/**
	 * Submitted values keep their backslashes.
	 *
	 * update_metadata() unslashes whatever it is handed, so storing already-unslashed
	 * field values strips a level of escaping and silently eats backslashes out of
	 * user content (a pasted "C:\Users\me" became "C:Usersme"). Regression guard for
	 * the wp_slash() in store_submission().
	 */
	public function test_store_submission_preserves_backslashes_in_field_values() {
		$fields = array(
			'win_path' => array(
				'value' => 'C:\Users\me\report.txt',
				'type'  => 'text',
			),
			'message'  => array(
				'value' => 'a backslash \ and an escape \n stay put',
				'type'  => 'textarea',
			),
		);

		$post_id = $this->call_private_method( 'store_submission', array( 'form-slash-test', $fields ) );
		$this->assertNotWPError( $post_id );

		$stored = get_post_meta( $post_id, '_dsg_form_fields', true );

		$this->assertSame( 'C:\Users\me\report.txt', $stored['win_path']['value'] );
		$this->assertSame( 'a backslash \ and an escape \n stay put', $stored['message']['value'] );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Characters that are not backslashes are unaffected by the re-slashing.
	 *
	 * Guards the other direction: wp_slash() must not leave literal escape characters
	 * behind in quotes or double up anything already correct.
	 */
	public function test_store_submission_does_not_corrupt_quotes_or_unicode() {
		$fields = array(
			'name' => array(
				'value' => 'O\'Brien "Tester" & Sons — ünïcode',
				'type'  => 'text',
			),
		);

		$post_id = $this->call_private_method( 'store_submission', array( 'form-quote-test', $fields ) );
		$this->assertNotWPError( $post_id );

		$stored = get_post_meta( $post_id, '_dsg_form_fields', true );
		$this->assertSame( 'O\'Brien "Tester" & Sons — ünïcode', $stored['name']['value'] );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Run the registered turnstile_token validate_callback against a value.
	 *
	 * Exercises the real route registration rather than a copy of the rule, so the
	 * test still fails if the endpoint stops validating the token altogether.
	 *
	 * @param mixed $value Token value to validate.
	 * @return true|WP_Error Validation result.
	 */
	private function validate_turnstile_token( $value ) {
		$routes = rest_get_server()->get_routes();
		$args   = $routes['/designsetgo/v1/form/submit'][0]['args'];

		$this->assertArrayHasKey( 'turnstile_token', $args );
		$this->assertArrayHasKey( 'validate_callback', $args['turnstile_token'] );

		return call_user_func( $args['turnstile_token']['validate_callback'], $value );
	}

	/**
	 * A real Turnstile token is dot-delimited, so the validator must accept dots.
	 *
	 * The original rule was /^[a-zA-Z0-9_-]+$/, which has no '.' in the character
	 * class, so every genuine Cloudflare token was rejected with a 400 and
	 * Turnstile was unusable whenever it was switched on.
	 */
	public function test_turnstile_token_accepts_real_dot_delimited_token() {
		$token = '0.hVo2rjF4Kd_ZjXcLp-9QwT.MTcwOTMyMTQ1Ng.abc123XYZ_-.f00ba7';

		$this->assertTrue( $this->validate_turnstile_token( $token ) );
	}

	/**
	 * Cloudflare's documented dummy token must validate too.
	 */
	public function test_turnstile_token_accepts_cloudflare_dummy_token() {
		$this->assertTrue( $this->validate_turnstile_token( 'XXXX.DUMMY.TOKEN.XXXX' ) );
	}

	/**
	 * Empty token stays valid: the form degrades gracefully when Turnstile fails
	 * to issue one, and verification is skipped rather than blocking submission.
	 */
	public function test_turnstile_token_accepts_empty_value() {
		$this->assertTrue( $this->validate_turnstile_token( '' ) );
	}

	/**
	 * Cloudflare caps tokens at 2048 characters; anything longer is not a token.
	 */
	public function test_turnstile_token_rejects_token_over_length_limit() {
		$result = $this->validate_turnstile_token( str_repeat( 'a', 2049 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_turnstile_token', $result->get_error_code() );
	}

	/**
	 * A token at exactly the documented ceiling is still a valid token.
	 */
	public function test_turnstile_token_accepts_token_at_length_limit() {
		$this->assertTrue( $this->validate_turnstile_token( str_repeat( 'a', 2048 ) ) );
	}

	/**
	 * Whitespace and control characters never appear in a token; reject them
	 * rather than forwarding junk to Cloudflare's siteverify endpoint.
	 */
	public function test_turnstile_token_rejects_whitespace_and_control_characters() {
		$values = array( 'tok en', "tok\nen", "tok\ten", "tok\0en" );

		foreach ( $values as $value ) {
			$result = $this->validate_turnstile_token( $value );

			$this->assertWPError( $result, sprintf( 'Expected %s to be rejected.', wp_json_encode( $value ) ) );
			$this->assertSame( 'invalid_turnstile_token', $result->get_error_code() );
		}
	}

	/**
	 * Non-string input must not blow up the validator.
	 */
	public function test_turnstile_token_rejects_non_string() {
		$this->assertWPError( $this->validate_turnstile_token( array( 'nope' ) ) );
	}

	/**
	 * End-to-end guard on the reported symptom: submitting a real dot-delimited
	 * token returned "400 Invalid parameter(s): turnstile_token" and the request
	 * never reached the handler. Whatever else the submission does, it must not
	 * fail parameter validation on the token any more.
	 */
	public function test_rest_endpoint_does_not_reject_real_turnstile_token_as_invalid_param() {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_body_params(
			array(
				'formId'          => 'test-form',
				'fields'          => array(),
				'turnstile_token' => '0.hVo2rjF4Kd_ZjXcLp-9QwT.MTcwOTMyMTQ1Ng.abc123XYZ_-.f00ba7',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$code     = isset( $data['code'] ) ? $data['code'] : '';

		$this->assertNotSame( 'rest_invalid_param', $code, 'Token must not fail REST parameter validation.' );

		// Belt and braces: even if some other rest_invalid_param fires, it must not
		// be about turnstile_token.
		if ( 'rest_invalid_param' === $code ) {
			$this->assertArrayNotHasKey( 'turnstile_token', $data['data']['params'] );
		}
	}
}
