<?php
/**
 * Builds the signed HTTP request for a form webhook delivery.
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
}
