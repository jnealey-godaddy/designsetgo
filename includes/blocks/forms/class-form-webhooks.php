<?php
/**
 * Form submission webhook delivery.
 *
 * Posts each new submission as signed JSON to the form's webhook URL. The URL
 * comes from the form's saved block attributes, never from the request, and
 * is copied onto the submission so retries can't be redirected by later edits.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Webhooks.
 */
class Form_Webhooks {

	/**
	 * WP-Cron hook for scheduled retries.
	 */
	const RETRY_HOOK = 'designsetgo_form_webhook_retry';

	/**
	 * Admin-post action for a manual resend.
	 */
	const RESEND_ACTION = 'designsetgo_resend_webhook';

	/**
	 * Seconds to wait before attempts 2, 3, 4 and 5.
	 */
	const DEFAULT_RETRY_DELAYS = array( 60, 300, 1800, 7200 );

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'designsetgo_form_submitted', array( $this, 'handle_submission' ), 10, 4 );
		add_action( self::RETRY_HOOK, array( $this, 'retry' ) );
	}

	/**
	 * Start delivery for a new submission when its form has a webhook URL.
	 *
	 * @param int    $submission_id Submission post ID.
	 * @param string $form_id       Form ID.
	 * @param array  $fields        Sanitized fields (unused; the payload is rebuilt from storage).
	 * @param array  $block_attrs   Server-resolved form-builder attributes.
	 */
	public function handle_submission( $submission_id, $form_id, $fields, $block_attrs = array() ) {
		$url = is_array( $block_attrs ) && isset( $block_attrs['webhookUrl'] ) && is_string( $block_attrs['webhookUrl'] )
			? trim( $block_attrs['webhookUrl'] )
			: '';

		if ( '' === $url ) {
			return;
		}

		$submission_id = (int) $submission_id;
		update_post_meta( $submission_id, '_dsg_webhook_url', esc_url_raw( $url ) );
		update_post_meta( $submission_id, '_dsg_webhook_delivery_id', wp_generate_uuid4() );
		update_post_meta( $submission_id, '_dsg_webhook_attempts', 0 );
		update_post_meta( $submission_id, '_dsg_webhook_status', 'pending' );

		$this->deliver( $submission_id );
	}

	/**
	 * WP-Cron retry handler.
	 *
	 * @param int $submission_id Submission post ID.
	 */
	public function retry( $submission_id ) {
		$submission_id = absint( $submission_id );

		if ( ! self::is_submission( $submission_id ) || 'pending' !== self::get_status( $submission_id ) || 'trash' === get_post_status( $submission_id ) ) {
			return;
		}

		$this->deliver( $submission_id );
	}

	/**
	 * Manually resend: reset attempts and deliver now.
	 *
	 * @param int $submission_id Submission post ID.
	 * @return string 'delivered', 'pending', 'failed', or 'invalid' when there is nothing to resend.
	 */
	public function resend( int $submission_id ): string {
		if ( ! self::is_submission( $submission_id ) || '' === (string) get_post_meta( $submission_id, '_dsg_webhook_url', true ) ) {
			return 'invalid';
		}

		wp_clear_scheduled_hook( self::RETRY_HOOK, array( $submission_id ) );
		update_post_meta( $submission_id, '_dsg_webhook_attempts', 0 );
		update_post_meta( $submission_id, '_dsg_webhook_status', 'pending' );

		return $this->deliver( $submission_id );
	}

	/**
	 * Make one delivery attempt and record the outcome.
	 *
	 * @param int $submission_id Submission post ID.
	 * @return string 'delivered', 'pending' (retry scheduled) or 'failed'.
	 */
	public function deliver( int $submission_id ): string {
		$form_id = (string) get_post_meta( $submission_id, '_dsg_form_id', true );
		$url     = $this->allowed_url( (string) get_post_meta( $submission_id, '_dsg_webhook_url', true ), $form_id, $submission_id );

		if ( '' === $url ) {
			return $this->record_failure( $submission_id, $form_id, 0, __( 'The webhook URL is not a valid public http(s) address, or it was blocked by a filter.', 'designsetgo' ), false );
		}

		$body      = (string) wp_json_encode( $this->build_payload( $submission_id ) );
		$timestamp = time();
		$secret    = self::get_secret();
		$headers   = array(
			'Content-Type'     => 'application/json',
			'User-Agent'       => 'DesignSetGo/' . DESIGNSETGO_VERSION . '; ' . home_url( '/' ),
			'X-DSGo-Event'     => 'form.submitted',
			'X-DSGo-Delivery'  => (string) get_post_meta( $submission_id, '_dsg_webhook_delivery_id', true ),
			'X-DSGo-Timestamp' => (string) $timestamp,
		);
		if ( '' !== $secret ) {
			$headers['X-DSGo-Signature'] = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
		}

		update_post_meta( $submission_id, '_dsg_webhook_attempts', (int) get_post_meta( $submission_id, '_dsg_webhook_attempts', true ) + 1 );
		update_post_meta( $submission_id, '_dsg_webhook_signed', '' !== $secret ? 'yes' : 'no' );

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => (int) apply_filters( 'designsetgo_form_webhook_timeout', 5 ),
				'redirection' => 0,
				'headers'     => $headers,
				'body'        => $body,
			)
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		update_post_meta( $submission_id, '_dsg_webhook_last_code', $code );

		if ( $code >= 200 && $code < 300 ) {
			update_post_meta( $submission_id, '_dsg_webhook_status', 'delivered' );
			update_post_meta( $submission_id, '_dsg_webhook_delivered_date', current_time( 'mysql' ) );
			delete_post_meta( $submission_id, '_dsg_webhook_last_error' );
			do_action( 'designsetgo_form_webhook_delivered', $submission_id, $form_id, $code );
			return 'delivered';
		}

		$error = is_wp_error( $response )
			? $response->get_error_message()
			/* translators: %d: HTTP status code */
			: sprintf( __( 'The receiver responded with HTTP %d.', 'designsetgo' ), $code );

		return $this->record_failure( $submission_id, $form_id, $code, $error, true );
	}

	/**
	 * Build the JSON payload from the stored submission.
	 *
	 * @param int $submission_id Submission post ID.
	 * @return array Payload.
	 */
	public function build_payload( int $submission_id ): array {
		$form_id = (string) get_post_meta( $submission_id, '_dsg_form_id', true );
		$stored  = get_post_meta( $submission_id, '_dsg_form_fields', true );
		$fields  = array();
		$labels  = array();

		foreach ( is_array( $stored ) ? $stored : array() as $name => $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}
			$fields[ $name ] = isset( $data['value'] ) ? $data['value'] : '';
			$labels[ $name ] = isset( $data['label'] ) && '' !== $data['label'] ? (string) $data['label'] : (string) $name;
		}

		$payload = array(
			'event'         => 'form.submitted',
			'form_id'       => $form_id,
			'submission_id' => $submission_id,
			'submitted_at'  => (string) get_post_time( 'c', true, $submission_id ),
			'source_url'    => (string) get_post_meta( $submission_id, '_dsg_submission_referer', true ),
			'fields'        => (object) $fields,
			'labels'        => (object) $labels,
		);

		return (array) apply_filters( 'designsetgo_form_webhook_payload', $payload, $submission_id, $form_id );
	}

	/**
	 * The site-wide signing secret.
	 *
	 * @return string Secret, or '' when none is configured.
	 */
	public static function get_secret(): string {
		if ( ! class_exists( '\DesignSetGo\Admin\Settings' ) ) {
			return '';
		}
		$settings = \DesignSetGo\Admin\Settings::get_settings();
		$secret   = isset( $settings['integrations']['form_webhook_secret'] ) ? $settings['integrations']['form_webhook_secret'] : '';

		return is_string( $secret ) ? trim( $secret ) : '';
	}

	/**
	 * Delivery status.
	 *
	 * @param int $submission_id Submission post ID.
	 * @return string '', 'pending', 'delivered' or 'failed'.
	 */
	public static function get_status( int $submission_id ): string {
		return (string) get_post_meta( $submission_id, '_dsg_webhook_status', true );
	}

	/**
	 * Human-readable delivery status.
	 *
	 * @param int $submission_id Submission post ID.
	 * @return string Label, or '' when the submission has no webhook.
	 */
	public static function status_label( int $submission_id ): string {
		switch ( self::get_status( $submission_id ) ) {
			case 'delivered':
				return __( 'Delivered', 'designsetgo' );
			case 'failed':
				return __( 'Failed', 'designsetgo' );
			case 'pending':
				$attempts = (int) get_post_meta( $submission_id, '_dsg_webhook_attempts', true );
				/* translators: %d: number of delivery attempts so far */
				return sprintf( _n( 'Pending (%d attempt)', 'Pending (%d attempts)', $attempts, 'designsetgo' ), $attempts );
			default:
				return '';
		}
	}

	/**
	 * Nonced admin URL that resends a submission's webhook.
	 *
	 * @param int $submission_id Submission post ID.
	 * @return string URL.
	 */
	public static function resend_url( int $submission_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'     => self::RESEND_ACTION,
					'submission' => $submission_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::RESEND_ACTION . '_' . $submission_id
		);
	}

	/**
	 * Record a failed attempt; schedule a retry when attempts remain.
	 *
	 * @param int    $submission_id Submission post ID.
	 * @param string $form_id       Form ID.
	 * @param int    $code          HTTP code (0 when there was no response).
	 * @param string $error         Error message.
	 * @param bool   $retryable     Whether another attempt could succeed.
	 * @return string 'pending' or 'failed'.
	 */
	private function record_failure( int $submission_id, string $form_id, int $code, string $error, bool $retryable ): string {
		update_post_meta( $submission_id, '_dsg_webhook_last_error', wp_slash( mb_substr( $error, 0, 500 ) ) );

		$delays   = $this->retry_delays();
		$attempts = (int) get_post_meta( $submission_id, '_dsg_webhook_attempts', true );

		if ( $retryable && $attempts >= 1 && $attempts <= count( $delays ) ) {
			$scheduled = wp_schedule_single_event( time() + $delays[ $attempts - 1 ], self::RETRY_HOOK, array( $submission_id ), true );
			if ( true === $scheduled ) {
				update_post_meta( $submission_id, '_dsg_webhook_status', 'pending' );
				return 'pending';
			}

			$reason = $scheduled->get_error_message();
			/* translators: %s: reason the scheduler gave, may be empty */
			$error = trim( sprintf( __( 'Could not schedule a retry. %s', 'designsetgo' ), $reason ) );
			update_post_meta( $submission_id, '_dsg_webhook_last_error', wp_slash( mb_substr( $error, 0, 500 ) ) );
		}

		update_post_meta( $submission_id, '_dsg_webhook_status', 'failed' );
		do_action( 'designsetgo_form_webhook_failed', $submission_id, $form_id, $error, $code );
		return 'failed';
	}

	/**
	 * Retry delays in seconds; the count sets how many retries happen.
	 *
	 * @return int[] Delays.
	 */
	private function retry_delays(): array {
		$delays = apply_filters( 'designsetgo_form_webhook_retry_delays', self::DEFAULT_RETRY_DELAYS );

		return array_values( array_filter( array_map( 'absint', (array) $delays ) ) );
	}

	/**
	 * Validate the stored URL and run it through the allowlist filter.
	 *
	 * @param string $url           Stored URL.
	 * @param string $form_id       Form ID.
	 * @param int    $submission_id Submission post ID.
	 * @return string URL, or '' when not allowed.
	 */
	private function allowed_url( string $url, string $form_id, int $submission_id ): string {
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return '';
		}

		$url = apply_filters( 'designsetgo_form_webhook_url', $url, $form_id, $submission_id );

		return ( is_string( $url ) && '' !== $url && wp_http_validate_url( $url ) ) ? $url : '';
	}

	/**
	 * Whether an ID is a stored form submission.
	 *
	 * @param int $submission_id Post ID.
	 * @return bool
	 */
	private static function is_submission( int $submission_id ): bool {
		return $submission_id > 0 && 'dsgo_form_submission' === get_post_type( $submission_id );
	}
}
