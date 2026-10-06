<?php
/**
 * Webhook delivery for form submissions.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_Error;
use DesignSetGo\Admin\Settings;
use DesignSetGo\Blocks\Form_Webhooks;

/**
 * Form webhooks test case.
 */
class Test_Form_Webhooks extends WP_UnitTestCase {

	/**
	 * A public IP literal: passes wp_http_validate_url() without DNS (newer WP rejects TEST-NET ranges).
	 */
	const URL = 'https://93.184.216.34/hook';

	/**
	 * Service under test.
	 *
	 * @var Form_Webhooks
	 */
	private $webhooks;

	/**
	 * Captured outbound requests: list of [ url, args ].
	 *
	 * @var array
	 */
	private $requests = array();

	/**
	 * Response the fake transport returns (array or WP_Error).
	 *
	 * @var mixed
	 */
	private $response;

	public function set_up() {
		parent::set_up();
		$this->webhooks = new Form_Webhooks();
		$this->requests = array();
		$this->response = array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'body'     => '',
			'headers'  => array(),
		);
		add_filter( 'pre_http_request', array( $this, 'fake_transport' ), 10, 3 );
		Settings::invalidate_cache();
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_transport' ), 10 );
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		wp_unschedule_hook( Form_Webhooks::RETRY_HOOK );
		parent::tear_down();
	}

	/**
	 * Fake HTTP transport.
	 *
	 * @param mixed  $pre  Short-circuit value.
	 * @param array  $args Request args.
	 * @param string $url  URL.
	 * @return mixed
	 */
	public function fake_transport( $pre, $args, $url ) {
		$this->requests[] = array( $url, $args );
		return $this->response;
	}

	/**
	 * Create a stored submission like Form_Handler::store_submission() does.
	 *
	 * @param array $fields name => [ value, type, label? ].
	 * @return int Submission ID.
	 */
	private function make_submission( array $fields ) {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'private',
				'post_date'   => '2026-10-06 09:15:00',
			)
		);
		update_post_meta( $id, '_dsg_form_id', 'contact-a1' );
		update_post_meta( $id, '_dsg_form_fields', wp_slash( $fields ) );
		update_post_meta( $id, '_dsg_submission_referer', 'https://example.com/contact/' );
		return $id;
	}

	/**
	 * Fire the submitted hook the way Form_Handler does.
	 *
	 * @param int    $id  Submission ID.
	 * @param string $url Webhook URL attribute.
	 */
	private function submit( $id, $url = self::URL ) {
		$this->webhooks->handle_submission( $id, 'contact-a1', array(), array( 'webhookUrl' => $url ) );
	}

	private function set_secret( $secret ) {
		update_option( Settings::OPTION_NAME, array( 'integrations' => array( 'form_webhook_secret' => $secret ) ) );
		Settings::invalidate_cache();
	}

	public function test_no_request_without_webhook_url() {
		$id = $this->make_submission( array( 'a' => array( 'value' => 'x', 'type' => 'text' ) ) );
		$this->submit( $id, '' );

		$this->assertCount( 0, $this->requests );
		$this->assertSame( '', get_post_meta( $id, '_dsg_webhook_status', true ) );
		$this->assertSame( '', Form_Webhooks::status_label( $id ) );
	}

	public function test_signed_delivery_payload_and_headers() {
		$this->set_secret( 's3cret' );
		$id = $this->make_submission(
			array(
				'your_name' => array( 'value' => 'Pat "P" O\'Neil, C:\\Users\\pat', 'type' => 'text', 'label' => 'Your name' ),
				'topics'    => array( 'value' => array( 'a', 'b' ), 'type' => 'checkbox' ),
				'note'      => array( 'value' => "Line 1\nLíne 2 — ✓", 'type' => 'textarea', 'label' => 'Note' ),
			)
		);

		$this->submit( $id );

		$this->assertCount( 1, $this->requests );
		list( $url, $args ) = $this->requests[0];
		$this->assertSame( self::URL, $url );
		$this->assertSame( 0, $args['redirection'] );

		$headers = $args['headers'];
		$this->assertSame( 'application/json', $headers['Content-Type'] );
		$this->assertSame( 'form.submitted', $headers['X-DSGo-Event'] );
		$this->assertSame( get_post_meta( $id, '_dsg_webhook_delivery_id', true ), $headers['X-DSGo-Delivery'] );
		$this->assertSame(
			'sha256=' . hash_hmac( 'sha256', $headers['X-DSGo-Timestamp'] . '.' . $args['body'], 's3cret' ),
			$headers['X-DSGo-Signature']
		);

		$payload = json_decode( $args['body'], true );
		$this->assertSame( 'form.submitted', $payload['event'] );
		$this->assertSame( 'contact-a1', $payload['form_id'] );
		$this->assertSame( $id, $payload['submission_id'] );
		$this->assertSame( 'https://example.com/contact/', $payload['source_url'] );
		$this->assertSame( 'Pat "P" O\'Neil, C:\\Users\\pat', $payload['fields']['your_name'] );
		$this->assertSame( "Line 1\nLíne 2 — ✓", $payload['fields']['note'] );
		$this->assertSame( array( 'a', 'b' ), $payload['fields']['topics'] );
		$this->assertSame( 'Your name', $payload['labels']['your_name'] );
		$this->assertSame( 'topics', $payload['labels']['topics'], 'No stored label falls back to the field name.' );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $payload['submitted_at'] );

		$this->assertSame( 'delivered', get_post_meta( $id, '_dsg_webhook_status', true ) );
		$this->assertSame( 'yes', get_post_meta( $id, '_dsg_webhook_signed', true ) );
		$this->assertNotEmpty( get_post_meta( $id, '_dsg_webhook_delivered_date', true ) );
		$this->assertFalse( wp_next_scheduled( Form_Webhooks::RETRY_HOOK, array( $id ) ) );
	}

	public function test_unsigned_without_secret() {
		$id = $this->make_submission( array( 'a' => array( 'value' => 'x', 'type' => 'text' ) ) );
		$this->submit( $id );

		$this->assertArrayNotHasKey( 'X-DSGo-Signature', $this->requests[0][1]['headers'] );
		$this->assertSame( 'no', get_post_meta( $id, '_dsg_webhook_signed', true ) );
		$this->assertSame( 'delivered', get_post_meta( $id, '_dsg_webhook_status', true ) );
	}

	/**
	 * @dataProvider rejected_urls
	 *
	 * @param string $url Rejected URL.
	 */
	public function test_rejected_url_fails_without_request_or_retry( $url ) {
		$id = $this->make_submission( array( 'a' => array( 'value' => 'x', 'type' => 'text' ) ) );
		$this->submit( $id, $url );

		$this->assertCount( 0, $this->requests );
		$this->assertSame( 'failed', get_post_meta( $id, '_dsg_webhook_status', true ) );
		$this->assertNotEmpty( get_post_meta( $id, '_dsg_webhook_last_error', true ) );
		$this->assertFalse( wp_next_scheduled( Form_Webhooks::RETRY_HOOK, array( $id ) ) );
	}

	public function rejected_urls() {
		return array(
			'loopback'    => array( 'http://127.0.0.1/hook' ),
			'private'     => array( 'http://192.168.1.5/hook' ),
			'not a url'   => array( 'javascript:alert(1)' ),
			'file scheme' => array( 'file:///etc/passwd' ),
		);
	}

	public function test_url_filter_can_block_a_destination() {
		$block = function () {
			return '';
		};
		add_filter( 'designsetgo_form_webhook_url', $block );

		$id = $this->make_submission( array( 'a' => array( 'value' => 'x', 'type' => 'text' ) ) );
		$this->submit( $id );

		remove_filter( 'designsetgo_form_webhook_url', $block );
		$this->assertCount( 0, $this->requests );
		$this->assertSame( 'failed', get_post_meta( $id, '_dsg_webhook_status', true ) );
	}

	/**
	 * @dataProvider failing_responses
	 *
	 * @param mixed $response Fake transport response.
	 */
	public function test_failure_schedules_a_retry( $response ) {
		$this->response = $response;
		$id             = $this->make_submission( array( 'a' => array( 'value' => 'x', 'type' => 'text' ) ) );

		$before = time();
		$this->submit( $id );

		$this->assertSame( 'pending', get_post_meta( $id, '_dsg_webhook_status', true ) );
		$this->assertSame( '1', (string) get_post_meta( $id, '_dsg_webhook_attempts', true ) );
		$next = wp_next_scheduled( Form_Webhooks::RETRY_HOOK, array( $id ) );
		$this->assertGreaterThanOrEqual( $before + 60, $next );
		$this->assertLessThanOrEqual( time() + 60, $next );
	}

	public function failing_responses() {
		return array(
			'500'      => array( array( 'response' => array( 'code' => 500, 'message' => 'Error' ), 'body' => '', 'headers' => array() ) ),
			'redirect' => array( array( 'response' => array( 'code' => 302, 'message' => 'Found' ), 'body' => '', 'headers' => array( 'location' => 'http://127.0.0.1/' ) ) ),
			'timeout'  => array( new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) ),
		);
	}

	public function test_final_attempt_marks_failed_and_fires_action() {
		$this->response = array( 'response' => array( 'code' => 503, 'message' => 'Unavailable' ), 'body' => '', 'headers' => array() );
		$failed         = array();
		$listener       = function ( $submission_id, $form_id, $error ) use ( &$failed ) {
			$failed[] = array( $submission_id, $form_id, $error );
		};
		add_action( 'designsetgo_form_webhook_failed', $listener, 10, 3 );

		$id = $this->make_submission( array( 'a' => array( 'value' => 'x', 'type' => 'text' ) ) );
		$this->submit( $id );
		for ( $i = 0; $i < 4; $i++ ) {
			$this->webhooks->retry( $id );
		}

		remove_action( 'designsetgo_form_webhook_failed', $listener, 10 );
		$this->assertCount( 5, $this->requests );
		$this->assertSame( 'failed', get_post_meta( $id, '_dsg_webhook_status', true ) );
		$this->assertSame( '503', (string) get_post_meta( $id, '_dsg_webhook_last_code', true ) );
		$this->assertCount( 1, $failed );
		$this->assertSame( 'contact-a1', $failed[0][1] );
		$this->assertSame( 'Failed', Form_Webhooks::status_label( $id ) );

		// A stray retry after failure does nothing.
		$this->webhooks->retry( $id );
		$this->assertCount( 5, $this->requests );
	}

	public function test_all_retries_share_the_delivery_id_and_stored_url() {
		$this->response = array( 'response' => array( 'code' => 500, 'message' => 'Error' ), 'body' => '', 'headers' => array() );
		$id             = $this->make_submission( array( 'a' => array( 'value' => 'x', 'type' => 'text' ) ) );
		$this->submit( $id );

		$this->response = array( 'response' => array( 'code' => 204, 'message' => 'No Content' ), 'body' => '', 'headers' => array() );
		$this->webhooks->retry( $id );

		$this->assertSame( self::URL, $this->requests[1][0] );
		$this->assertSame( $this->requests[0][1]['headers']['X-DSGo-Delivery'], $this->requests[1][1]['headers']['X-DSGo-Delivery'] );
		$this->assertSame( 'delivered', get_post_meta( $id, '_dsg_webhook_status', true ) );
	}

	public function test_retry_for_deleted_submission_is_a_noop() {
		$this->response = array( 'response' => array( 'code' => 500, 'message' => 'Error' ), 'body' => '', 'headers' => array() );
		$id             = $this->make_submission( array( 'a' => array( 'value' => 'x', 'type' => 'text' ) ) );
		$this->submit( $id );
		wp_delete_post( $id, true );

		$this->webhooks->retry( $id );
		$this->assertCount( 1, $this->requests );
	}

	public function test_resend_resets_attempts_and_clears_scheduled_retry() {
		$this->response = array( 'response' => array( 'code' => 500, 'message' => 'Error' ), 'body' => '', 'headers' => array() );
		$id             = $this->make_submission( array( 'a' => array( 'value' => 'x', 'type' => 'text' ) ) );
		$this->submit( $id );
		$this->assertNotFalse( wp_next_scheduled( Form_Webhooks::RETRY_HOOK, array( $id ) ) );

		$this->response = array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => '', 'headers' => array() );
		$this->assertSame( 'delivered', $this->webhooks->resend( $id ) );
		$this->assertSame( '1', (string) get_post_meta( $id, '_dsg_webhook_attempts', true ) );
		$this->assertFalse( wp_next_scheduled( Form_Webhooks::RETRY_HOOK, array( $id ) ) );
	}

	public function test_resend_without_stored_url_is_invalid() {
		$id = $this->make_submission( array( 'a' => array( 'value' => 'x', 'type' => 'text' ) ) );
		$this->assertSame( 'invalid', $this->webhooks->resend( $id ) );
		$this->assertCount( 0, $this->requests );
	}

	public function test_retry_delays_are_filterable() {
		$delays = function () {
			return array( 10 );
		};
		add_filter( 'designsetgo_form_webhook_retry_delays', $delays );
		$this->response = array( 'response' => array( 'code' => 500, 'message' => 'Error' ), 'body' => '', 'headers' => array() );

		$id = $this->make_submission( array( 'a' => array( 'value' => 'x', 'type' => 'text' ) ) );
		$this->submit( $id );
		$this->webhooks->retry( $id );

		remove_filter( 'designsetgo_form_webhook_retry_delays', $delays );
		$this->assertCount( 2, $this->requests );
		$this->assertSame( 'failed', get_post_meta( $id, '_dsg_webhook_status', true ) );
	}
}
