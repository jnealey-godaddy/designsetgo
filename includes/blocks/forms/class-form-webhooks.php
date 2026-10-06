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
		add_action( 'designsetgo_cleanup_old_submissions', array( $this, 'run_stranded_sweep' ) );
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
	 * status stays `pending` until then; if PHP dies first, Form_Webhook_Recovery
	 * recovers it. Only PHP-FPM and LiteSpeed can flush the response early;
	 * elsewhere the attempt is handed to WP-Cron instead. After the flush,
	 * output from later shutdown callbacks no longer reaches the visitor.
	 */
	public function deliver_queued(): void {
		if ( empty( $this->queue ) ) {
			return;
		}

		$queued      = $this->queue;
		$this->queue = array(); // Cleared first so a re-entrant call can't double-send.

		/**
		 * Filters whether the first attempt is sent at shutdown, after the
		 * response is flushed. When false it is handed to WP-Cron.
		 *
		 * @since 2.10.0
		 *
		 * @param bool $after_response Default: whether the server can flush early.
		 */
		if ( ! apply_filters( 'designsetgo_form_webhook_send_after_response', function_exists( 'fastcgi_finish_request' ) || function_exists( 'litespeed_finish_request' ) ) ) {
			$this->hand_off_to_cron( $queued );
			return;
		}

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
	 * Schedule first attempts on WP-Cron so the visitor never waits on the receiver.
	 *
	 * @param int[] $queued Submission IDs.
	 */
	private function hand_off_to_cron( array $queued ): void {
		$spawn = false;
		foreach ( $queued as $submission_id ) {
			if ( true === wp_schedule_single_event( time(), self::RETRY_HOOK, array( $submission_id ), true ) ) {
				$spawn = true;
				continue;
			}
			$this->deliver( $submission_id ); // The scheduler refused: send now rather than lose it.
		}

		// spawn_cron() redirects under ALTERNATE_WP_CRON, which can't work after output.
		$alternate = defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON;
		$disabled  = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		if ( $spawn && ! $alternate && ! $disabled && ! wp_doing_cron() ) {
			spawn_cron();
		}
	}

	/** Daily cron entry point; action callbacks must not return a value. */
	public function run_stranded_sweep(): void {
		Form_Webhook_Recovery::reschedule_stranded();
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
	 * Manually resend: reset attempts and deliver now. Gets a new delivery ID,
	 * so a receiver that de-duplicates on X-DSGo-Delivery accepts it.
	 *
	 * @param int $submission_id Submission post ID.
	 * @return string 'delivered', 'pending', 'failed', or 'invalid' when there is nothing to resend.
	 */
	public function resend( int $submission_id ): string {
		if ( ! self::is_submission( $submission_id ) || '' === (string) get_post_meta( $submission_id, '_dsg_webhook_url', true ) ) {
			return 'invalid';
		}

		wp_clear_scheduled_hook( self::RETRY_HOOK, array( $submission_id ) );
		update_post_meta( $submission_id, '_dsg_webhook_delivery_id', wp_generate_uuid4() );
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
		$url     = Form_Webhook_Request::allowed_url( (string) get_post_meta( $submission_id, '_dsg_webhook_url', true ), $form_id, $submission_id );

		if ( '' === $url ) {
			return $this->record_failure( $submission_id, $form_id, 0, __( 'The webhook URL is not a valid public http(s) address, or it was blocked by a filter.', 'designsetgo' ), false );
		}

		$request = Form_Webhook_Request::build( $submission_id );

		update_post_meta( $submission_id, '_dsg_webhook_attempts', (int) get_post_meta( $submission_id, '_dsg_webhook_attempts', true ) + 1 );
		update_post_meta( $submission_id, '_dsg_webhook_last_attempt', time() );
		update_post_meta( $submission_id, '_dsg_webhook_signed', $request['signed'] ? 'yes' : 'no' );

		$result = Form_Webhook_Request::send( $url, $request );
		$code   = $result['code'];
		update_post_meta( $submission_id, '_dsg_webhook_last_code', $code );

		if ( $code >= 200 && $code < 300 ) {
			update_post_meta( $submission_id, '_dsg_webhook_status', 'delivered' );
			update_post_meta( $submission_id, '_dsg_webhook_delivered_date', current_time( 'mysql' ) );
			delete_post_meta( $submission_id, '_dsg_webhook_last_error' );
			/**
			 * Fires after a webhook delivery succeeds.
			 *
			 * @since 2.10.0
			 *
			 * @param int    $submission_id Submission post ID.
			 * @param string $form_id       Form ID.
			 * @param int    $code          HTTP status code.
			 */
			do_action( 'designsetgo_form_webhook_delivered', $submission_id, $form_id, $code );
			return 'delivered';
		}

		return $this->record_failure( $submission_id, $form_id, $code, $result['error'], Form_Webhook_Request::is_retryable( $code ) );
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
		/**
		 * Fires when a webhook delivery gives up: retries are exhausted, the
		 * error is permanent, or the URL is not allowed.
		 *
		 * @since 2.10.0
		 *
		 * @param int    $submission_id Submission post ID.
		 * @param string $form_id       Form ID.
		 * @param string $error         Last error message.
		 * @param int    $code          Last HTTP code, 0 without a response.
		 */
		do_action( 'designsetgo_form_webhook_failed', $submission_id, $form_id, $error, $code );
		return 'failed';
	}

	/**
	 * Seconds before each retry; 0 retries on the next cron run. The count sets how many retries happen.
	 *
	 * @return int[] Delays.
	 */
	private function retry_delays(): array {
		/**
		 * Filters the seconds before each retry. The count is the number of
		 * retries; an empty array disables them.
		 *
		 * @since 2.10.0
		 *
		 * @param int[] $delays Delays. Default 60, 300, 1800, 7200.
		 */
		$delays = apply_filters( 'designsetgo_form_webhook_retry_delays', self::DEFAULT_RETRY_DELAYS );

		return array_values( array_map( 'absint', (array) $delays ) );
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
