<?php
/**
 * Tests for Cloudflare Turnstile verification.
 *
 * Verification fails closed: a token is accepted only when Cloudflare says it
 * passed.
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
 * Turnstile verification test case.
 */
class Test_Form_Turnstile_Verification extends WP_UnitTestCase {

	/**
	 * Requests sent to Cloudflare during the test.
	 *
	 * @var array<int, array>
	 */
	private $requests = array();

	/**
	 * What the mocked siteverify call returns.
	 *
	 * @var array|WP_Error
	 */
	private $reply;

	/**
	 * Set up: mock the siteverify endpoint.
	 */
	public function set_up() {
		parent::set_up();
		$this->requests = array();
		$this->reply    = self::json( array( 'success' => true ) );

		add_filter( 'pre_http_request', array( $this, 'mock_siteverify' ), 10, 3 );
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_siteverify' ), 10 );
		remove_all_filters( 'designsetgo_turnstile_accept_when_unavailable' );
		delete_option( 'designsetgo_settings' );
		parent::tear_down();
	}

	/**
	 * Answer siteverify requests with the configured reply.
	 *
	 * @param false|array $pre  Short-circuit value.
	 * @param array       $args Request arguments.
	 * @param string      $url  Request URL.
	 * @return false|array|WP_Error
	 */
	public function mock_siteverify( $pre, $args, $url ) {
		if ( false === strpos( $url, 'challenges.cloudflare.com/turnstile/v0/siteverify' ) ) {
			return $pre;
		}
		$this->requests[] = $args;

		return $this->reply;
	}

	/**
	 * A siteverify HTTP response.
	 *
	 * @param mixed $body Decoded body, or a raw string.
	 * @param int   $code HTTP status.
	 * @return array
	 */
	private static function json( $body, $code = 200 ) {
		return array(
			'headers'  => array(),
			'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
			'response' => array(
				'code'    => $code,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Store the Turnstile keys.
	 *
	 * @param string $site   Site key.
	 * @param string $secret Secret key.
	 */
	private static function keys( $site, $secret ) {
		update_option(
			'designsetgo_settings',
			array(
				'integrations' => array(
					'turnstile_site_key'   => $site,
					'turnstile_secret_key' => $secret,
				),
			)
		);
	}

	/**
	 * Submit a published form.
	 *
	 * @param bool   $turnstile Whether the form enables Turnstile.
	 * @param string $token     Submitted Turnstile token.
	 * @return mixed Handler result.
	 */
	private function submit( $turnstile, $token ) {
		$form_id = 'turnstile-' . wp_generate_password( 8, false );
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '","enableEmail":false' . ( $turnstile ? ',"enableTurnstile":true' : '' ) . '} -->'
					. '<!-- wp:designsetgo/form-text-field {"fieldName":"name"} /-->'
					. '<!-- /wp:designsetgo/form-builder -->',
			)
		);

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_param( 'formId', $form_id );
		$request->set_param(
			'fields',
			array(
				'name' => array(
					'value' => 'Ada',
					'type'  => 'text',
				),
			)
		);
		$request->set_param( 'honeypot', '' );
		$request->set_param( 'timestamp', '' );
		$request->set_param( 'turnstile_token', $token );

		return ( new Form_Handler() )->handle_form_submission( $request );
	}

	/**
	 * Assert a result is a WP_Error with a code.
	 *
	 * @param string $code   Expected error code.
	 * @param mixed  $result Result.
	 */
	private function assertErrorCode( $code, $result ) {
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $code, $result->get_error_code() );
	}

	public function test_configured_needs_both_keys() {
		self::keys( 'site', '' );
		$this->assertFalse( Form_Security::is_turnstile_configured() );

		self::keys( 'site', '   ' );
		$this->assertFalse( Form_Security::is_turnstile_configured(), 'A whitespace secret is no secret.' );

		self::keys( '', 'secret' );
		$this->assertFalse( Form_Security::is_turnstile_configured() );

		self::keys( 'site', 'secret' );
		$this->assertTrue( Form_Security::is_turnstile_configured() );
	}

	public function test_missing_secret_fails_without_calling_cloudflare() {
		self::keys( 'site', '' );

		$this->assertErrorCode( 'turnstile_not_configured', ( new Form_Security() )->verify_turnstile( 'any-token' ) );
		$this->assertSame( array(), $this->requests );
	}

	public function test_token_cloudflare_rejects_fails() {
		self::keys( 'site', 'secret' );
		$this->reply = self::json(
			array(
				'success'     => false,
				'error-codes' => array( 'invalid-input-response' ),
			)
		);

		$this->assertErrorCode( 'turnstile_failed', ( new Form_Security() )->verify_turnstile( 'forged' ) );
	}

	public function test_unreachable_or_unreadable_cloudflare_fails() {
		self::keys( 'site', 'secret' );
		$security = new Form_Security();

		$this->reply = new WP_Error( 'http_request_failed', 'Operation timed out' );
		$this->assertErrorCode( 'turnstile_unavailable', $security->verify_turnstile( 'token' ) );

		$this->reply = self::json( '<html>Bad gateway</html>' );
		$this->assertErrorCode( 'turnstile_unavailable', $security->verify_turnstile( 'token' ) );

		$this->reply = self::json( array( 'success' => true ), 503 );
		$this->assertErrorCode( 'turnstile_unavailable', $security->verify_turnstile( 'token' ), 'A 5xx is not an answer, even with a body.' );
	}

	public function test_success_is_accepted_and_sends_the_secret_and_token() {
		self::keys( 'site', 'secret' );

		$this->assertTrue( ( new Form_Security() )->verify_turnstile( 'good-token' ) );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'secret', $this->requests[0]['body']['secret'] );
		$this->assertSame( 'good-token', $this->requests[0]['body']['response'] );
	}

	public function test_sites_can_accept_submissions_while_cloudflare_is_unavailable() {
		add_filter( 'designsetgo_turnstile_accept_when_unavailable', '__return_true' );
		$security = new Form_Security();

		self::keys( 'site', 'secret' );
		$this->reply = new WP_Error( 'http_request_failed', 'Operation timed out' );
		$this->assertTrue( $security->verify_turnstile( 'token' ) );

		// The opt-in never covers a rejected token or a missing secret.
		$this->reply = self::json( array( 'success' => false ) );
		$this->assertErrorCode( 'turnstile_failed', $security->verify_turnstile( 'forged' ) );

		self::keys( 'site', '' );
		$this->assertErrorCode( 'turnstile_not_configured', $security->verify_turnstile( 'token' ) );
	}

	public function test_turnstile_form_with_incomplete_keys_is_turned_away() {
		self::keys( 'site', '' );

		$result = $this->submit( true, 'any-token' );

		$this->assertErrorCode( 'turnstile_not_configured', $result );
		$this->assertSame( 503, $result->get_error_data()['status'] );
		$this->assertSame( array(), $this->requests );
	}

	public function test_turnstile_form_requires_a_token() {
		self::keys( 'site', 'secret' );

		$this->assertErrorCode( 'turnstile_required', $this->submit( true, '' ) );
	}

	public function test_turnstile_form_rejects_a_forged_token() {
		self::keys( 'site', 'secret' );
		$this->reply = self::json( array( 'success' => false ) );

		$this->assertErrorCode( 'turnstile_failed', $this->submit( true, 'forged' ) );
	}

	public function test_turnstile_form_accepts_a_verified_token() {
		self::keys( 'site', 'secret' );

		$result = $this->submit( true, 'good-token' );

		$this->assertNotInstanceOf( WP_Error::class, $result );
		$this->assertCount( 1, $this->requests );
	}

	public function test_form_without_turnstile_ignores_a_stray_token() {
		// Cloudflare would reject this token. A form without Turnstile must
		// not ask, so its submissions never depend on Cloudflare.
		self::keys( 'site', 'secret' );
		$this->reply = self::json( array( 'success' => false ) );

		$result = $this->submit( false, 'stray-token' );

		$this->assertNotInstanceOf( WP_Error::class, $result );
		$this->assertSame( array(), $this->requests, 'A form without Turnstile never calls Cloudflare.' );
	}
}
