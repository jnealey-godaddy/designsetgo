<?php
/**
 * Form Builder: what a visitor without JavaScript sees after a failed submit.
 *
 * Without JavaScript the browser posts the form to admin-post.php, which
 * redirects back to the page. A failure used to land on the form's generic
 * error message with every field empty, and no hint whether a required
 * field was missing, an answer was invalid or the form was sent too fast.
 *
 * This carries two things across that redirect:
 *
 * - The reason: an allowlisted error code (and the field it concerns) in the
 *   query string. Only fixed, translated sentences are ever printed from it;
 *   a field is named by the label in the page's own markup, never by text
 *   from the URL.
 * - The answers: a signed, HttpOnly cookie that lives for ten minutes and is
 *   cleared on the next page view. It stays in the visitor's own browser,
 *   so their answers are never written to the database or the URL.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_No_JS_Retry.
 */
class Form_No_JS_Retry {

	/**
	 * Cookie holding the answers of the last failed no-JS submission.
	 */
	const COOKIE = 'dsgo_form_retry';

	/**
	 * Cookie lifetime in seconds.
	 */
	const TTL = 600;

	/**
	 * Largest cookie value written, in bytes. Browsers cap a cookie near 4KB.
	 */
	const MAX_BYTES = 3000;

	/**
	 * Answers read from this request's cookie: form ID => field name => value.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static $captured = array();

	/**
	 * Query args naming why a submission failed, for the redirect back.
	 *
	 * @param \WP_Error $error Submission error.
	 * @return array<string, string> Args to add ('' values are dropped by add_query_arg()).
	 */
	public static function redirect_args( \WP_Error $error ): array {
		$code = (string) $error->get_error_code();
		if ( '' === self::reason( $code, '' ) ) {
			return array();
		}
		$data  = $error->get_error_data();
		$field = is_array( $data ) && isset( $data['field'] ) && is_string( $data['field'] ) ? $data['field'] : '';

		return array_filter(
			array(
				'dsgo_form_error' => $code,
				'dsgo_form_field' => $field,
			)
		);
	}

	/**
	 * The sentence explaining an error code, or '' for codes that get none.
	 *
	 * Spam and server errors get no detail: it would only help a bot, or say
	 * nothing the visitor can act on.
	 *
	 * @param string $code  Error code.
	 * @param string $label Label of the field concerned, or ''.
	 * @return string Translated sentence.
	 */
	public static function reason( string $code, string $label ): string {
		switch ( $code ) {
			case 'required_field_missing':
				/* translators: %s: form field label. */
				return '' === $label ? __( 'Please fill in every required field.', 'designsetgo' ) : sprintf( __( 'Please fill in “%s”.', 'designsetgo' ), $label );
			case 'validation_error':
				/* translators: %s: form field label. */
				return '' === $label ? __( 'Please check your answers.', 'designsetgo' ) : sprintf( __( 'Please check your answer to “%s”.', 'designsetgo' ), $label );
			case 'too_fast':
				return __( 'The form was sent too quickly. Please wait a few seconds and send it again.', 'designsetgo' );
			case 'rate_limit':
			case 'global_rate_limit':
				return __( 'Too many submissions were sent. Please wait a few minutes and try again.', 'designsetgo' );
			case 'invalid_nonce':
				return __( 'Your session expired. Please send the form again.', 'designsetgo' );
		}
		return '';
	}

	/**
	 * Keep a failed submission's answers for the page the visitor returns to.
	 *
	 * @param string                           $form_id Form ID.
	 * @param array<int, array<string, mixed>> $fields  Submitted fields (name, value, type).
	 */
	public static function remember( string $form_id, array $fields ): void {
		$value = self::encode( $form_id, $fields );
		if ( '' !== $value ) {
			self::send_cookie( $value, time() + self::TTL );
		}
	}

	/**
	 * Signed cookie value for a form's answers, or '' when there are none.
	 *
	 * Hidden fields are left out: the page renders their values itself. When
	 * the answers are too big for a cookie, the longest are dropped first, so
	 * the short ones a visitor would retype are the ones kept.
	 *
	 * @param string                           $form_id Form ID.
	 * @param array<int, array<string, mixed>> $fields  Submitted fields (name, value, type).
	 * @return string Cookie value.
	 */
	public static function encode( string $form_id, array $fields ): string {
		$values = array();
		foreach ( $fields as $field ) {
			$name  = isset( $field['name'] ) && is_string( $field['name'] ) ? $field['name'] : '';
			$value = isset( $field['value'] ) && is_string( $field['value'] ) ? $field['value'] : '';
			if ( '' !== $name && '' !== $value && 'hidden' !== ( $field['type'] ?? '' ) ) {
				$values[ $name ] = $value;
			}
		}
		uasort( $values, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );
		while ( $values ) {
			$payload = base64_encode( (string) wp_json_encode( array( $form_id, $values ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Cookie-safe transport, not obfuscation.
			$cookie  = $payload . '.' . wp_hash( $payload );
			if ( strlen( $cookie ) <= self::MAX_BYTES ) {
				return $cookie;
			}
			unset( $values[ array_key_first( $values ) ] );
		}
		return '';
	}

	/**
	 * Read and clear this request's answers cookie.
	 *
	 * Runs on template_redirect, before any output, so the cookie can still
	 * be expired: the answers are offered once, then forgotten.
	 */
	public static function capture(): void {
		if ( ! isset( $_COOKIE[ self::COOKIE ] ) || ! is_string( $_COOKIE[ self::COOKIE ] ) ) {
			return;
		}
		$decoded = self::decode( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );
		if ( null !== $decoded ) {
			self::$captured[ $decoded[0] ] = $decoded[1];
		}
		self::send_cookie( '', time() - HOUR_IN_SECONDS );
	}

	/**
	 * Verify and unpack a cookie value.
	 *
	 * @param string $cookie Cookie value.
	 * @return array{0: string, 1: array<string, string>}|null Form ID and answers, or null when invalid.
	 */
	public static function decode( string $cookie ): ?array {
		$parts = explode( '.', $cookie );
		if ( 2 !== count( $parts ) || ! hash_equals( wp_hash( $parts[0] ), $parts[1] ) ) {
			return null;
		}
		$data = json_decode( (string) base64_decode( $parts[0], true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- See encode().
		if ( ! is_array( $data ) || ! isset( $data[0], $data[1] ) || ! is_string( $data[0] ) || ! is_array( $data[1] ) ) {
			return null;
		}
		return array( $data[0], array_filter( $data[1], 'is_string' ) );
	}

	/**
	 * Answers captured for a form on this request.
	 *
	 * @param string $form_id Form ID.
	 * @return array<string, string> Field name => value.
	 */
	public static function values( string $form_id ): array {
		return self::$captured[ $form_id ] ?? array();
	}

	/**
	 * Forget captured answers. For tests.
	 */
	public static function reset(): void {
		self::$captured = array();
	}

	/**
	 * Write the cookie, unless output has already started.
	 *
	 * @param string $value   Cookie value ('' to clear).
	 * @param int    $expires Expiry timestamp.
	 */
	private static function send_cookie( string $value, int $expires ): void {
		if ( headers_sent() ) {
			return;
		}
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
}
