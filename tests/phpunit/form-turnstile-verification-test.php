<?php
/**
 * Turnstile verification must fail closed.
 *
 * When a form requires Turnstile (or a token is presented for verification),
 * accept the token only after Cloudflare explicitly reports success. Missing
 * secret, HTTP failures, and malformed responses must reject — not pass.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_Error;
use DesignSetGo\Blocks\Form_Handler;
use DesignSetGo\Blocks\Form_Security;

/**
 * Form Turnstile verification tests.
 */
class Test_Form_Turnstile_Verification extends WP_UnitTestCase {

	/**
	 * @var Form_Security
	 */
	private $security;

	/**
	 * @var Form_Handler
	 */
	private $handler;

	/**
	 * @var callable|null
	 */
	private $http_filter;

	public function set_up() {
		parent::set_up();
		$this->security = new Form_Security();
		$this->handler  = new Form_Handler();
		do_action( 'rest_api_init' );
	}

	public function tear_down() {
		if ( null !== $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter );
			$this->http_filter = null;
		}
		delete_option( 'designsetgo_settings' );
		parent::tear_down();
	}

	/**
	 * Stub Cloudflare siteverify responses via pre_http_request.
	 *
	 * @param mixed $response WP_Error, response array, or null to assert no call.
	 */
	private function mock_siteverify( $response ) {
		$this->http_filter = function ( $preempt, $args, $url ) use ( $response ) {
			if ( false === strpos( $url, 'challenges.cloudflare.com/turnstile/v0/siteverify' ) ) {
				return $preempt;
			}
			if ( null === $response ) {
				$this->fail( 'siteverify should not have been called' );
			}
			return $response;
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
	}

	private function set_turnstile_keys( $site_key, $secret_key ) {
		update_option(
			'designsetgo_settings',
			array(
				'integrations' => array(
					'turnstile_site_key'   => $site_key,
					'turnstile_secret_key' => $secret_key,
				),
				'forms'        => array(
					'enable_honeypot'      => false,
					'enable_rate_limiting' => false,
				),
			)
		);
	}

	private function success_http_response() {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'success' => true ) ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}

	private function failure_http_response( array $error_codes = array( 'invalid-input-response' ) ) {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'success'     => false,
					'error-codes' => $error_codes,
				)
			),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}

	public function test_verify_turnstile_rejects_when_secret_missing() {
		$this->set_turnstile_keys( 'site-key-present', '' );
		$this->mock_siteverify( null );

		$result = $this->security->verify_turnstile( 'forged-token' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'turnstile_not_configured', $result->get_error_code() );
	}

	public function test_verify_turnstile_rejects_forged_token() {
		$this->set_turnstile_keys( 'site-key', 'secret-key' );
		$this->mock_siteverify( $this->failure_http_response() );

		$result = $this->security->verify_turnstile( 'forged-token' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'turnstile_failed', $result->get_error_code() );
	}

	public function test_verify_turnstile_rejects_http_error() {
		$this->set_turnstile_keys( 'site-key', 'secret-key' );
		$this->mock_siteverify( new WP_Error( 'http_request_failed', 'cURL error 28: Timeout' ) );

		$result = $this->security->verify_turnstile( 'real-looking-token' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'turnstile_http_error', $result->get_error_code() );
	}

	public function test_verify_turnstile_rejects_malformed_json() {
		$this->set_turnstile_keys( 'site-key', 'secret-key' );
		$this->mock_siteverify(
			array(
				'headers'  => array(),
				'body'     => 'not-json{{{',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			)
		);

		$result = $this->security->verify_turnstile( 'real-looking-token' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'turnstile_invalid_response', $result->get_error_code() );
	}

	public function test_verify_turnstile_accepts_explicit_cloudflare_success() {
		$this->set_turnstile_keys( 'site-key', 'secret-key' );
		$this->mock_siteverify( $this->success_http_response() );

		$result = $this->security->verify_turnstile( 'valid-token' );

		$this->assertTrue( $result );
	}

	public function test_submission_with_turnstile_disabled_skips_verification() {
		$this->set_turnstile_keys( '', '' );
		$this->mock_siteverify( null );

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"no-turnstile-form","enableEmail":false,"enableTurnstile":false} -->'
					. '<!-- wp:designsetgo/form-text-field {"fieldName":"name","required":false} /-->'
					. '<!-- /wp:designsetgo/form-builder -->',
			)
		);

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_param( 'formId', 'no-turnstile-form' );
		$request->set_param(
			'fields',
			array(
				array(
					'name'  => 'name',
					'value' => 'Ada',
				),
			)
		);
		$request->set_param( 'honeypot', '' );
		$request->set_param( 'timestamp', '' );
		$request->set_param( 'turnstile_token', '' );

		$result = $this->handler->handle_form_submission( $request );

		$this->assertNotInstanceOf( WP_Error::class, $result );
		wp_delete_post( $post_id, true );
	}

	public function test_submission_with_turnstile_required_rejects_forged_token_when_secret_missing() {
		$this->set_turnstile_keys( 'site-key-only', '' );
		$this->mock_siteverify( null );

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"turnstile-form","enableEmail":false,"enableTurnstile":true} -->'
					. '<!-- wp:designsetgo/form-text-field {"fieldName":"name","required":false} /-->'
					. '<!-- /wp:designsetgo/form-builder -->',
			)
		);

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_param( 'formId', 'turnstile-form' );
		$request->set_param(
			'fields',
			array(
				array(
					'name'  => 'name',
					'value' => 'Ada',
				),
			)
		);
		$request->set_param( 'honeypot', '' );
		$request->set_param( 'timestamp', '' );
		$request->set_param( 'turnstile_token', 'forged-nonempty-token' );

		$result = $this->handler->handle_form_submission( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'turnstile_not_configured', $result->get_error_code() );
		wp_delete_post( $post_id, true );
	}
}
