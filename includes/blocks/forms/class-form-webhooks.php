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

	/** WP-Cron hook for scheduled retries. */
	const RETRY_HOOK = 'designsetgo_form_webhook_retry';

	/** Admin-post action for a manual resend. */
	const RESEND_ACTION = 'designsetgo_resend_webhook';

	/** Seconds to wait before attempts 2, 3, 4 and 5. */
	const DEFAULT_RETRY_DELAYS = array( 60, 300, 1800, 7200 );

	/**
	 * Submission IDs whose first attempt is waiting for the shutdown hook.
	 *
	 * @var int[]
	 */
	private $queue = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'designsetgo_form_submitted', array( $this, 'handle_submission' ), 10, 4 );
		add_action( self::RETRY_HOOK, array( $this, 'retry' ) );
		add_action(
			'designsetgo_cleanup_old_submissions',
			function () {
				$this->reschedule_stranded(); // Wrapped: action callbacks must not return a value.
			}
		);
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

		$this->queue[] = $submission_id;
		if ( ! has_action( 'shutdown', array( $this, 'deliver_queued' ) ) ) {
			add_action( 'shutdown', array( $this, 'deliver_queued' ), PHP_INT_MAX );
		}
	}

	/**
	 * Make the first attempt for queued submissions, after the response is sent.
	 * Deferred to `shutdown` so a slow receiver never delays the visitor. The
	 * status stays `pending` until then; if PHP dies first, reschedule_stranded()
	 * recovers it. The response is flushed early on PHP-FPM and LiteSpeed only.
	 */
	public function deliver_queued(): void {
		if ( empty( $this->queue ) ) {
			return;
		}

		$queued      = $this->queue;
		$this->queue = array(); // Cleared first so a re-entrant call can't double-send.

		ignore_user_abort( true );
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}

		foreach ( $queued as $submission_id ) {
			$this->deliver( $submission_id );
		}
	}

	/**
	 * Schedule a retry for submissions stuck in `pending` with none scheduled.
	 *
	 * Recovers deliveries lost to PHP dying mid-call or a deactivated plugin.
	 * Runs on the daily cleanup cron; submissions under ten minutes old are
	 * skipped so an in-flight delivery isn't double-queued.
	 *
	 * @return int Number of retries scheduled.
	 */
	public function reschedule_stranded(): int {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- Bounded daily sweep.
		$ids = get_posts(
			array(
				'post_type'      => 'dsgo_form_submission',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
				'meta_key'       => '_dsg_webhook_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded daily sweep.
				'meta_value'     => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded daily sweep.
				'date_query'     => array(
					array(
						'column' => 'post_date_gmt',
						'before' => '10 minutes ago',
					),
				),
			)
		);

		$scheduled = 0;
		foreach ( $ids as $id ) {
			$args = array( (int) $id );
			if ( false === wp_next_scheduled( self::RETRY_HOOK, $args ) && wp_schedule_single_event( time(), self::RETRY_HOOK, $args ) ) {
				++$scheduled;
			}
		}

		return $scheduled;
	}

	/**
	 * WP-Cron retry handler.
	 *
	 * @param int $submission_id Submission post ID.
	 */
	public function retry( $submission_id ) {
		$submission_id = absint( $submission_id );

		if ( ! self::is_submission( $submission_id ) || 'pending' !== Form_Webhook_Status::get_status( $submission_id ) || 'trash' === get_post_status( $submission_id ) ) {
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

		$request = Form_Webhook_Request::build( $submission_id );

		update_post_meta( $submission_id, '_dsg_webhook_attempts', (int) get_post_meta( $submission_id, '_dsg_webhook_attempts', true ) + 1 );
		update_post_meta( $submission_id, '_dsg_webhook_signed', $request['signed'] ? 'yes' : 'no' );

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => (int) apply_filters( 'designsetgo_form_webhook_timeout', 5 ),
				'redirection' => 0,
				'headers'     => $request['headers'],
				'body'        => $request['body'],
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
	 * Seconds before each retry; 0 retries on the next cron run. The count sets how many retries happen.
	 *
	 * @return int[] Delays.
	 */
	private function retry_delays(): array {
		$delays = apply_filters( 'designsetgo_form_webhook_retry_delays', self::DEFAULT_RETRY_DELAYS );

		return array_values( array_map( 'absint', (array) $delays ) );
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
