<?php
/**
 * Form Security Class
 *
 * Handles security checks for form submissions: honeypot detection,
 * time-based spam checks, rate limiting, Turnstile verification,
 * and client IP detection with trusted proxy support.
 *
 * Extracted from Form_Handler to separate security concerns.
 *
 * @package DesignSetGo
 * @subpackage Blocks
 * @since 2.2.0
 */

namespace DesignSetGo\Blocks;

use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form_Security class.
 */
class Form_Security {

	/**
	 * Check honeypot field for spam.
	 *
	 * @param string $honeypot Honeypot field value.
	 * @param string $form_id  Form identifier.
	 * @return true|WP_Error True if clean, WP_Error if spam detected.
	 */
	public function check_honeypot( string $honeypot, string $form_id ) {
		if ( ! empty( $honeypot ) ) {
			do_action( 'designsetgo_form_spam_detected', $form_id, 'honeypot', $this->get_client_ip() );

			return new WP_Error(
				'spam_detected',
				__( 'Spam submission rejected.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check time-based spam detection.
	 *
	 * @param string $timestamp Submission timestamp.
	 * @param string $form_id   Form identifier.
	 * @return true|WP_Error True if valid, WP_Error if too fast.
	 */
	public function check_submission_timing( string $timestamp, string $form_id ) {
		if ( empty( $timestamp ) ) {
			return true;
		}

		$elapsed = ( time() * 1000 ) - intval( $timestamp );
		if ( $elapsed < 3000 ) {
			do_action( 'designsetgo_form_spam_detected', $form_id, 'too_fast', $this->get_client_ip(), array( 'elapsed_ms' => $elapsed ) );

			return new WP_Error(
				'too_fast',
				__( 'Submission too fast. Please try again.', 'designsetgo' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Check rate limiting for form submissions.
	 *
	 * @param string $form_id   Form ID.
	 * @param int    $block_max Max submissions from block attributes. Default 3.
	 * @return true|WP_Error True if allowed, WP_Error if rate limited.
	 */
	public function check_rate_limit( string $form_id, int $block_max = 3 ) {
		$ip_address = $this->get_client_ip();
		$key        = 'dsgo_form_submit_' . md5( $form_id ) . '_' . md5( $ip_address );
		$count      = get_transient( $key );

		$max_submissions = apply_filters( 'designsetgo_form_rate_limit_count', $block_max, $form_id );

		if ( false !== $count && $count >= $max_submissions ) {
			do_action( 'designsetgo_form_rate_limit_exceeded', $form_id, $ip_address, $count, $max_submissions );

			return new WP_Error(
				'rate_limit',
				__( 'Too many submissions. Please try again later.', 'designsetgo' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Increment rate limit counter after successful submission.
	 *
	 * @param string $form_id      Form ID.
	 * @param int    $block_window Time window in seconds from block attributes. Default 60.
	 */
	public function increment_rate_limit( string $form_id, int $block_window = 60 ): void {
		$ip_address = $this->get_client_ip();
		$key        = 'dsgo_form_submit_' . md5( $form_id ) . '_' . md5( $ip_address );
		$count      = get_transient( $key );

		$time_window = apply_filters( 'designsetgo_form_rate_limit_window', $block_window, $form_id );

		if ( false === $count ) {
			set_transient( $key, 1, $time_window );
		} else {
			set_transient( $key, $count + 1, $time_window );
		}
	}

	/**
	 * Apply a shared IP limit across every public form.
	 *
	 * Per-form counters can be bypassed by inventing IDs or rotating through a
	 * site owner's forms. This coarse limit bounds total write attempts while
	 * leaving individual forms free to use their own stricter controls.
	 *
	 * @return true|WP_Error True if allowed, WP_Error if rate limited.
	 */
	public function check_global_rate_limit() {
		$ip_address      = $this->get_client_ip();
		$key             = 'dsgo_form_submit_ip_' . md5( $ip_address );
		$count           = get_transient( $key );
		$max_submissions = (int) apply_filters( 'designsetgo_form_global_rate_limit_count', 20, $ip_address );

		if ( false !== $count && $count >= $max_submissions ) {
			do_action( 'designsetgo_form_global_rate_limit_exceeded', $ip_address, $count, $max_submissions );

			return new WP_Error(
				'global_rate_limit',
				__( 'Too many submissions. Please try again later.', 'designsetgo' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Increment the shared IP limit after a successful submission.
	 *
	 * @return void
	 */
	public function increment_global_rate_limit(): void {
		$ip_address  = $this->get_client_ip();
		$key         = 'dsgo_form_submit_ip_' . md5( $ip_address );
		$count       = get_transient( $key );
		$time_window = (int) apply_filters( 'designsetgo_form_global_rate_limit_window', 60, $ip_address );

		if ( false === $count ) {
			set_transient( $key, 1, $time_window );
			return;
		}

		set_transient( $key, $count + 1, $time_window );
	}

	/**
	 * Whether both Turnstile keys are configured.
	 *
	 * The widget needs the site key and verification needs the secret, so a
	 * form is only protected when both are set. The editor, the frontend and
	 * the submission handler all ask this one question.
	 *
	 * @return bool
	 */
	public static function is_turnstile_configured(): bool {
		$keys = self::turnstile_keys();

		return '' !== $keys['site'] && '' !== $keys['secret'];
	}

	/**
	 * The stored Turnstile keys, trimmed.
	 *
	 * @return array{site:string,secret:string}
	 */
	private static function turnstile_keys(): array {
		$settings     = get_option( 'designsetgo_settings', array() );
		$integrations = is_array( $settings ) && isset( $settings['integrations'] ) && is_array( $settings['integrations'] )
			? $settings['integrations']
			: array();

		return array(
			'site'   => isset( $integrations['turnstile_site_key'] ) && is_string( $integrations['turnstile_site_key'] ) ? trim( $integrations['turnstile_site_key'] ) : '',
			'secret' => isset( $integrations['turnstile_secret_key'] ) && is_string( $integrations['turnstile_secret_key'] ) ? trim( $integrations['turnstile_secret_key'] ) : '',
		);
	}

	/**
	 * Verify Cloudflare Turnstile token.
	 *
	 * Fails closed: a token is accepted only when Cloudflare explicitly
	 * reports success. A missing secret, an HTTP error, a timeout and an
	 * unreadable response are all failures, since none of them proves the
	 * visitor passed the challenge.
	 *
	 * @param string $token The Turnstile response token from the frontend.
	 * @return true|WP_Error True on success, WP_Error on verification failure.
	 */
	public function verify_turnstile( string $token ) {
		$secret_key = self::turnstile_keys()['secret'];

		if ( '' === $secret_key ) {
			return self::turnstile_error( 'turnstile_not_configured', 'Secret key is not configured' );
		}

		// 3s timeout is intentional. Turnstile runs on Cloudflare's edge network and
		// should respond in well under a second; this verification call blocks the
		// form submission response, so a generous timeout directly penalises customer
		// experience.
		$response = wp_remote_post(
			// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Server-side Turnstile verification API endpoint, not an offloaded asset. The sniff matches any `cloudflare.com` host in any string; no image, script, style or other content is loaded from it.
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 3,
				'body'    => array(
					'secret'   => $secret_key,
					'response' => $token,
					'remoteip' => $this->get_client_ip(),
				),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		$data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );

		if ( is_wp_error( $response ) || $code >= 500 || ! is_array( $data ) ) {
			/**
			 * Whether to accept a submission when Turnstile can't be reached.
			 *
			 * Off by default: an HTTP error, a timeout or an unreadable reply
			 * proves nothing about the visitor, so the submission is rejected.
			 * A site that prefers availability during a Cloudflare outage can
			 * return true. A missing secret or a token Cloudflare rejects is
			 * never accepted.
			 *
			 * @since 2.8.3
			 * @param bool           $accept   Default false.
			 * @param array|WP_Error $response The siteverify response.
			 */
			if ( apply_filters( 'designsetgo_turnstile_accept_when_unavailable', false, $response ) ) {
				return true;
			}

			$detail = is_wp_error( $response ) ? 'HTTP error - ' . $response->get_error_message() : 'Invalid response from Cloudflare (HTTP ' . $code . ')';

			return self::turnstile_error( 'turnstile_unavailable', $detail );
		}

		if ( ! isset( $data['success'] ) || true !== $data['success'] ) {
			$error_codes = isset( $data['error-codes'] ) && is_array( $data['error-codes'] ) ? implode( ', ', $data['error-codes'] ) : 'unknown';

			return self::turnstile_error( 'turnstile_failed', 'Verification failed - ' . $error_codes );
		}

		return true;
	}

	/**
	 * A failed Turnstile verification, logged when debugging.
	 *
	 * Visitors get one generic message for every cause, so the response does
	 * not reveal whether the site's keys or Cloudflare are at fault.
	 *
	 * @param string $code   Error code.
	 * @param string $detail Debug log detail.
	 * @return WP_Error
	 */
	private static function turnstile_error( string $code, string $detail ): WP_Error {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'DesignSetGo Turnstile: ' . $detail ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return new WP_Error(
			$code,
			__( 'Security verification failed. Please try again.', 'designsetgo' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Get client IP address with trusted proxy support.
	 *
	 * @return string IP address.
	 */
	public function get_client_ip(): string {
		// REMOTE_ADDR is set by the web server from the TCP peer. Validate it as a
		// real IP address so we never return a spoofed or malformed value.
		$remote_addr = $this->read_valid_ip( 'REMOTE_ADDR' );

		if ( null === $remote_addr ) {
			return 'unknown';
		}

		$trusted_proxies = apply_filters( 'designsetgo_trusted_proxies', array() );

		// Forwarded headers are only trustworthy when the connecting host is a
		// known proxy; otherwise a client could set them to spoof its address.
		if ( empty( $trusted_proxies ) || ! is_array( $trusted_proxies )
			|| ! $this->is_trusted_proxy( $remote_addr, $trusted_proxies ) ) {
			return $remote_addr;
		}

		// Ordered most-specific-first. The first header yielding a public,
		// routable address wins; comma-separated chains are walked left to right.
		$proxy_headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_CLIENT_IP',
		);

		foreach ( $proxy_headers as $header ) {
			$ip = $this->read_forwarded_ip( $header );

			if ( null !== $ip ) {
				return $ip;
			}
		}

		return $remote_addr;
	}

	/**
	 * Read a $_SERVER key and return it only if it is a valid IP address.
	 *
	 * Accepts both IPv4 and IPv6. Returns null when the key is absent, empty,
	 * or not a syntactically valid IP.
	 *
	 * @param string $key $_SERVER key to read (e.g. 'REMOTE_ADDR').
	 * @return string|null Validated IP address, or null.
	 */
	private function read_valid_ip( string $key ): ?string {
		if ( empty( $_SERVER[ $key ] ) ) {
			return null;
		}

		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders -- Value is validated with filter_var( FILTER_VALIDATE_IP ) before it is returned or used.
		$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

		return filter_var( $value, FILTER_VALIDATE_IP ) ? $value : null;
	}

	/**
	 * Extract the first public, routable IP from a forwarded proxy header.
	 *
	 * Handles single values and comma-separated chains (X-Forwarded-For style),
	 * skipping private and reserved ranges so we return the real client address.
	 *
	 * @param string $key $_SERVER key to read (e.g. 'HTTP_X_FORWARDED_FOR').
	 * @return string|null First valid public IP, or null.
	 */
	private function read_forwarded_ip( string $key ): ?string {
		if ( empty( $_SERVER[ $key ] ) ) {
			return null;
		}

		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders -- Each candidate is validated with filter_var( FILTER_VALIDATE_IP ) below before use.
		$raw = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

		foreach ( explode( ',', $raw ) as $candidate ) {
			$candidate = trim( $candidate );

			if ( filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Check if IP address is in trusted proxy list (supports CIDR notation).
	 *
	 * @param string $ip IP address to check.
	 * @param array  $trusted_proxies List of trusted IPs/CIDR ranges.
	 * @return bool True if trusted, false otherwise.
	 */
	private function is_trusted_proxy( string $ip, array $trusted_proxies ): bool {
		foreach ( $trusted_proxies as $trusted ) {
			if ( strpos( $trusted, '/' ) !== false ) {
				if ( $this->ip_in_range( $ip, $trusted ) ) {
					return true;
				}
			} elseif ( $ip === $trusted ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if IP is in CIDR range.
	 *
	 * @param string $ip IP address to check.
	 * @param string $cidr CIDR notation (e.g., '192.168.1.0/24').
	 * @return bool True if IP is in range.
	 */
	private function ip_in_range( string $ip, string $cidr ): bool {
		$parts = explode( '/', $cidr );
		if ( count( $parts ) !== 2 ) {
			return false;
		}
		list( $subnet, $mask ) = $parts;

		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );

		if ( false === $ip_long || false === $subnet_long ) {
			return false;
		}

		$mask = (int) $mask;
		if ( $mask < 0 || $mask > 32 ) {
			return false;
		}
		if ( 0 === $mask ) {
			return true;
		}

		$mask_long = -1 << ( 32 - $mask );

		return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
	}
}
