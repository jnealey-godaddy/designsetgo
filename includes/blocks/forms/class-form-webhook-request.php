<?php
/**
 * Builds and sends the signed HTTP request for a form webhook delivery.
 *
 * Receiver contract (signature, headers, payload, retries): docs/api/FORM-WEBHOOKS.md.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Webhook_Request.
 */
class Form_Webhook_Request {

	/** Payload format version, sent as `version`. Bump only for breaking changes. */
	const VERSION = 1;

	/**
	 * Body and headers for one delivery attempt.
	 *
	 * @param int $submission_id Submission post ID.
	 * @return array{body: string, headers: array<string, string>, signed: bool}
	 */
	public static function build( int $submission_id ): array {
		$body      = (string) wp_json_encode( self::build_payload( $submission_id ) );
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

		return array(
			'body'    => $body,
			'headers' => $headers,
			'signed'  => '' !== $secret,
		);
	}

	/**
	 * Build the JSON payload from the stored submission.
	 *
	 * @param int $submission_id Submission post ID.
	 * @return array Payload.
	 */
	public static function build_payload( int $submission_id ): array {
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
			'version'       => self::VERSION,
			'event'         => 'form.submitted',
			'form_id'       => $form_id,
			'submission_id' => $submission_id,
			'submitted_at'  => (string) get_post_time( 'c', true, $submission_id ),
			'source_url'    => (string) get_post_meta( $submission_id, '_dsg_submission_referer', true ),
			'fields'        => (object) $fields,
			'labels'        => (object) $labels,
		);

		/**
		 * Filters the webhook payload before it is encoded and signed.
		 *
		 * Receivers rely on `version`, `event` and `submission_id`; keep them.
		 * A non-array return is ignored.
		 *
		 * @since 2.10.0
		 *
		 * @param array  $payload       Payload.
		 * @param int    $submission_id Submission post ID.
		 * @param string $form_id       Form ID.
		 */
		$filtered = apply_filters( 'designsetgo_form_webhook_payload', $payload, $submission_id, $form_id );

		return is_array( $filtered ) ? $filtered : $payload; // @phpstan-ignore ternary.elseUnreachable (a third-party filter can return anything)
	}

	/**
	 * Validate the stored URL and run it through the allowlist filter.
	 *
	 * @param string $url           Stored URL.
	 * @param string $form_id       Form ID.
	 * @param int    $submission_id Submission post ID.
	 * @return string URL, or '' when not allowed.
	 */
	public static function allowed_url( string $url, string $form_id, int $submission_id ): string {
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return '';
		}

		/**
		 * Filters the receiver URL for one attempt; return '' to block it. The
		 * result is validated again, so it can't point at a private address.
		 *
		 * @since 2.10.0
		 *
		 * @param string $url           Stored URL.
		 * @param string $form_id       Form ID.
		 * @param int    $submission_id Submission post ID.
		 */
		$url = apply_filters( 'designsetgo_form_webhook_url', $url, $form_id, $submission_id );

		return ( is_string( $url ) && '' !== $url && wp_http_validate_url( $url ) ) ? $url : '';
	}

	/**
	 * POST a built request. Redirects are not followed: a redirect target was
	 * never validated, so a 3xx is reported as the response.
	 *
	 * @param string $url     Validated receiver URL.
	 * @param array  $request Result of build().
	 * @return array{code: int, error: string} HTTP code (0 without a response) and error message ('' on 2xx).
	 */
	public static function send( string $url, array $request ): array {
		/**
		 * Filters the webhook request timeout in seconds, clamped to 1–30.
		 *
		 * @since 2.10.0
		 *
		 * @param int $timeout Timeout. Default 5.
		 */
		$timeout = max( 1, min( 30, (int) apply_filters( 'designsetgo_form_webhook_timeout', 5 ) ) );

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => $timeout,
				'redirection' => 0,
				'headers'     => $request['headers'],
				'body'        => $request['body'],
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'code'  => 0,
				'error' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return array(
			'code'  => $code,
			/* translators: %d: HTTP status code */
			'error' => ( $code >= 200 && $code < 300 ) ? '' : sprintf( __( 'The receiver responded with HTTP %d.', 'designsetgo' ), $code ),
		);
	}

	/**
	 * Whether another attempt could succeed. No response, 408, 425, 429 and
	 * 5xx are transient; a redirect or any other 4xx will repeat until the
	 * receiver or URL is fixed, so it fails at once (Resend retries it).
	 *
	 * @param int $code HTTP code, 0 when there was no response.
	 * @return bool
	 */
	public static function is_retryable( int $code ): bool {
		return 0 === $code || $code >= 500 || in_array( $code, array( 408, 425, 429 ), true );
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
}
