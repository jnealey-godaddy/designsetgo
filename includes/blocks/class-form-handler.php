<?php
/**
 * Form Handler Class
 *
 * Handles form submissions via REST API endpoint with validation,
 * spam protection, and data storage.
 *
 * Security Monitoring Hooks
 * -------------------------
 * This class provides several action hooks for monitoring security events:
 *
 * 1. designsetgo_form_spam_detected
 *    Fired when spam is detected (honeypot or time-based)
 *
 *    @param string $form_id     Form identifier
 *    @param string $reason      Detection method: 'honeypot' or 'too_fast'
 *    @param string $ip_address  Client IP address
 *    @param array  $data        Additional data (optional, e.g., elapsed time)
 *
 * 2. designsetgo_form_rate_limit_exceeded
 *    Fired when rate limit is exceeded
 *    @param string $form_id         Form identifier
 *    @param string $ip_address      Client IP address
 *    @param int    $current_count   Current submission count
 *    @param int    $max_submissions Maximum allowed submissions
 *
 * 3. designsetgo_form_validation_failed
 *    Fired when field validation fails
 *    @param string $form_id     Form identifier
 *    @param string $field_name  Field that failed validation
 *    @param string $field_type  Field type (email, url, etc.)
 *    @param string $error_code  Validation error code
 *    @param string $ip_address  Client IP address
 *
 * 4. designsetgo_form_submitted
 *    Fired when form is successfully submitted
 *    @param int    $submission_id   Submission post ID
 *    @param string $form_id         Form identifier
 *    @param array  $sanitized_fields Sanitized form fields
 *
 * 5. designsetgo_form_email_sent
 *    Fired after email notification is sent (or attempted)
 *    @param int    $submission_id   Submission post ID
 *    @param string $form_id         Form identifier
 *    @param bool   $email_sent      Whether email was sent successfully
 *    @param string $email_to        Recipient email address
 *    @param string $email_subject   Email subject line
 *
 * 6. designsetgo_form_turnstile_failed
 *    Fired when Cloudflare Turnstile verification fails
 *    @param string $form_id     Form identifier
 *    @param string $ip_address  Client IP address
 *    @param string $error_code  Error code from verification
 *
 * Example Usage:
 * ```php
 * // Log spam attempts
 * add_action( 'designsetgo_form_spam_detected', function( $form_id, $reason, $ip ) {
 *     error_log( "Spam detected on form {$form_id}: {$reason} from {$ip}" );
 * }, 10, 3 );
 *
 * // Block IPs after multiple rate limit violations
 * add_action( 'designsetgo_form_rate_limit_exceeded', function( $form_id, $ip, $count ) {
 *     if ( $count > 10 ) {
 *         // Add to blocklist
 *         update_option( 'blocked_ips', array_merge(
 *             get_option( 'blocked_ips', [] ),
 *             [ $ip ]
 *         ) );
 *     }
 * }, 10, 3 );
 * ```
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

namespace DesignSetGo\Blocks;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form_Handler class.
 */
class Form_Handler {

	/**
	 * Security module for spam checks, rate limiting, and verification.
	 *
	 * @var Form_Security
	 */
	private Form_Security $security;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->security = new Form_Security();

		add_action( 'rest_api_init', array( $this, 'register_rest_endpoint' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'localize_form_script' ) );

		// Admin-ajax fallback for hosts that rate-limit /wp-json/ (e.g. Cloudflare/GoDaddy).
		add_action( 'wp_ajax_designsetgo_form_submit', array( $this, 'handle_ajax_submission' ) );
		add_action( 'wp_ajax_nopriv_designsetgo_form_submit', array( $this, 'handle_ajax_submission' ) );

		// Non-AJAX form POST handler.
		add_action( 'admin_post_designsetgo_form_submit', array( $this, 'handle_post_submission' ) );
		add_action( 'admin_post_nopriv_designsetgo_form_submit', array( $this, 'handle_post_submission' ) );

		// Register cron callback (scheduling handled by activation hook).
		add_action( 'designsetgo_cleanup_old_submissions', array( $this, 'cleanup_old_submissions' ) );

		// Invalidate cached form block attributes when posts are saved.
		add_action( 'save_post', array( $this, 'clear_form_attributes_cache' ) );
	}

	/**
	 * Get form settings.
	 *
	 * @return array Form settings with defaults applied.
	 */
	private function get_form_settings() {
		$settings = get_option( 'designsetgo_settings', array() );
		$defaults = array(
			'enable_honeypot'      => true,
			'enable_rate_limiting' => true,
			'retention_days'       => 30,
		);

		return isset( $settings['forms'] ) ? wp_parse_args( $settings['forms'], $defaults ) : $defaults;
	}

	/**
	 * Register REST API endpoint for form submission.
	 *
	 * SECURITY NOTE: This is a public endpoint (permission_callback = __return_true)
	 * because it needs to accept form submissions from non-logged-in users.
	 *
	 * Security measures in place:
	 * - Honeypot field check (detects spam bots) - configurable via settings
	 * - Time-based submission check (< 3 seconds = likely bot)
	 * - Rate limiting (3 submissions per 60 seconds per IP address) - configurable via settings
	 * - Comprehensive field validation (email, url, phone, number types)
	 * - Type-specific sanitization for all field values
	 * - Server-side email configuration lookup (email settings are never sent from the client)
	 * - Email header injection prevention (defense in depth)
	 *
	 * Additional protection available:
	 * - Cloudflare Turnstile integration (configurable per-form)
	 * - More aggressive rate limiting via filters
	 * - IP blocklist functionality via security monitoring hooks
	 *
	 * Extensibility:
	 * - Use 'designsetgo_form_rate_limit_count' filter to adjust rate limit
	 * - Use 'designsetgo_form_rate_limit_window' filter to adjust time window
	 * - Use 'designsetgo_form_submitted' action to hook into successful submissions
	 */
	public function register_rest_endpoint() {
		register_rest_route(
			'designsetgo/v1',
			'/form/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_form_submission' ),
				'permission_callback' => '__return_true', // Public endpoint - see DocBlock above for security measures.
				'args'                => array(
					'formId'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
					),
					'fields'           => array(
						'required'          => true,
						'type'              => 'array',
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					),
					'honeypot'         => array(
						'required' => false,
						'type'     => 'string',
						'default'  => '',
					),
					'timestamp'        => array(
						'required' => false,
						'type'     => 'string',
						'default'  => '',
					),
					'turnstile_token'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							// Empty is valid (graceful degradation).
							if ( empty( $value ) ) {
								return true;
							}
							// Turnstile tokens are alphanumeric with hyphens/underscores.
							if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $value ) ) {
								return new \WP_Error(
									'invalid_turnstile_token',
									__( 'Invalid Turnstile token format.', 'designsetgo' )
								);
							}
							return true;
						},
					),
				),
			)
		);
	}

	/**
	 * Handle form submission.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function handle_form_submission( $request ) {
		// CSRF Protection: Verify nonce for logged-in users.
		// For non-logged-in users, rely on honeypot + rate limiting + time-based checks.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Security verification failed. Please refresh the page and try again.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$form_id       = $request->get_param( 'formId' );
		$fields        = $request->get_param( 'fields' );
		$honeypot      = $request->get_param( 'honeypot' );
		$timestamp     = $request->get_param( 'timestamp' );
		$form_settings = $this->get_form_settings();

		// Look up per-block attributes for rate limiting and Turnstile enforcement.
		$block_attrs      = $this->get_form_block_attributes( $form_id );
		$rate_limit_count = isset( $block_attrs['rateLimitCount'] ) ? absint( $block_attrs['rateLimitCount'] ) : 3;
		$rate_limit_window = isset( $block_attrs['rateLimitWindow'] ) ? absint( $block_attrs['rateLimitWindow'] ) : 60;
		$turnstile_required = ! empty( $block_attrs['enableTurnstile'] );

		// Honeypot spam check (only if enabled in settings).
		if ( $form_settings['enable_honeypot'] ) {
			$honeypot_check = $this->security->check_honeypot( $honeypot, $form_id );
			if ( is_wp_error( $honeypot_check ) ) {
				return $honeypot_check;
			}
		}

		// Time-based spam check.
		$timing_check = $this->security->check_submission_timing( $timestamp, $form_id );
		if ( is_wp_error( $timing_check ) ) {
			return $timing_check;
		}

		// Rate limiting check (only if enabled in settings).
		// Uses per-block rateLimitCount/rateLimitWindow attributes as defaults for the filter.
		if ( $form_settings['enable_rate_limiting'] ) {
			$rate_limit_check = $this->security->check_rate_limit( $form_id, $rate_limit_count );
			if ( is_wp_error( $rate_limit_check ) ) {
				return $rate_limit_check;
			}
		}

		// Turnstile verification.
		// If the block requires Turnstile, reject submissions without a valid token.
		$turnstile_token = $request->get_param( 'turnstile_token' );
		if ( $turnstile_required && empty( $turnstile_token ) ) {
			return new WP_Error(
				'turnstile_required',
				__( 'Security verification is required. Please complete the challenge and try again.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}
		if ( ! empty( $turnstile_token ) ) {
			$turnstile_result = $this->security->verify_turnstile( $turnstile_token );
			if ( is_wp_error( $turnstile_result ) ) {
				/**
				 * Fires when Cloudflare Turnstile verification fails.
				 *
				 * @since 1.0.0
				 * @param string $form_id     Form identifier.
				 * @param string $ip_address  Client IP address.
				 * @param string $error_code  Error code from verification.
				 */
				do_action( 'designsetgo_form_turnstile_failed', $form_id, $this->security->get_client_ip(), $turnstile_result->get_error_code() );
				return $turnstile_result;
			}
		}

		// Sanitize and validate all fields.
		$form_field_types = $this->get_form_field_types( $form_id );
		$sanitized_fields = array();
		foreach ( $fields as $field ) {
			if ( ! isset( $field['name'] ) || ! isset( $field['value'] ) ) {
				continue;
			}

			$field_name          = sanitize_text_field( $field['name'] );
			$field_value         = $field['value'];
			$submitted_field_type = isset( $field['type'] ) ? sanitize_text_field( $field['type'] ) : 'text';
			$field_type          = isset( $form_field_types[ $field_name ] )
				? $form_field_types[ $field_name ]
				: $submitted_field_type;

			// Type-specific validation.
			$validation_result = $this->validate_field( $field_value, $field_type );
			if ( is_wp_error( $validation_result ) ) {
				// Security monitoring hook for validation failures.
				do_action( 'designsetgo_form_validation_failed', $form_id, $field_name, $field_type, $validation_result->get_error_code(), $this->security->get_client_ip() );

				return new WP_Error(
					'validation_error',
					sprintf(
						/* translators: %1$s: field name, %2$s: error message */
						__( 'Field "%1$s": %2$s', 'designsetgo' ),
						$field_name,
						$validation_result->get_error_message()
					),
					array( 'status' => 400 )
				);
			}

			// Type-specific sanitization.
			$sanitized_value = $this->sanitize_field( $field_value, $field_type );

			$sanitized_fields[ $field_name ] = array(
				'value' => $sanitized_value,
				'type'  => $field_type,
			);
		}

		// Store submission.
		$submission_id = $this->store_submission( $form_id, $sanitized_fields );

		if ( is_wp_error( $submission_id ) ) {
			return new WP_Error(
				'submission_failed',
				__( 'Failed to save form submission. Please try again.', 'designsetgo' ),
				array( 'status' => 500 )
			);
		}

		// Send email notification if enabled (settings looked up server-side from block attributes).
		$this->send_email_notification( $form_id, $sanitized_fields, $submission_id );

		// Increment rate limit counter ONLY after successful submission.
		if ( $form_settings['enable_rate_limiting'] ) {
			$this->security->increment_rate_limit( $form_id, $rate_limit_window );
		}

		// Trigger action hook for email notifications, integrations, etc.
		do_action( 'designsetgo_form_submitted', $submission_id, $form_id, $sanitized_fields );

		return new WP_REST_Response(
			array(
				'success'      => true,
				'message'      => __( 'Form submitted successfully!', 'designsetgo' ),
				'submissionId' => $submission_id,
			),
			200
		);
	}

	/**
	 * Handle form submission via admin-ajax.php (fallback for rate-limited REST API).
	 *
	 * Wraps the REST handler by building a WP_REST_Request from $_POST data.
	 */
	public function handle_ajax_submission() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'designsetgo_form_submit', '_ajax_nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security verification failed. Please refresh the page and try again.', 'designsetgo' ) ),
				403
			);
		}

		$request = new \WP_REST_Request( 'POST' );
		$request->set_header( 'Content-Type', 'application/json' );

		// Read JSON form data from the form_data POST field (form-encoded).
		// Form-encoded is used because some hosts (GoDaddy/Cloudflare) block
		// application/json POST requests with 429 errors.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by handle_form_submission.
		$raw_data = isset( $_POST['form_data'] ) ? wp_unslash( $_POST['form_data'] ) : '';
		$data     = json_decode( $raw_data, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid request data.', 'designsetgo' ) ),
				400
			);
		}

		$request->set_param( 'formId', isset( $data['formId'] ) ? sanitize_text_field( $data['formId'] ) : '' );
		$request->set_param( 'fields', isset( $data['fields'] ) ? $data['fields'] : array() );
		$request->set_param( 'honeypot', isset( $data['honeypot'] ) ? $data['honeypot'] : '' );
		$request->set_param( 'timestamp', isset( $data['timestamp'] ) ? $data['timestamp'] : '' );
		$request->set_param( 'turnstile_token', isset( $data['turnstile_token'] ) ? sanitize_text_field( $data['turnstile_token'] ) : '' );

		$result = $this->handle_form_submission( $request );

		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data() && isset( $result->get_error_data()['status'] )
				? $result->get_error_data()['status']
				: 400;
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				$status
			);
		}

		wp_send_json_success( $result->get_data() );
	}

	/**
	 * Handle non-AJAX form submission via admin_post.
	 *
	 * Processes standard form POST and redirects back with a status query param.
	 */
	public function handle_post_submission() {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'designsetgo_form_submit' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'designsetgo' ), '', array( 'response' => 403 ) );
		}

		$referer = wp_get_referer();
		if ( ! $referer ) {
			wp_die( esc_html__( 'Invalid form submission.', 'designsetgo' ), '', array( 'response' => 400 ) );
		}

		// Build fields array from POST data.
		$form_id = isset( $_POST['dsg_form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['dsg_form_id'] ) ) : '';
		$fields  = array();

		// Read field type map (JSON mapping of field name => type).
		$field_types = array();
		if ( isset( $_POST['dsg_field_types'] ) ) {
			$decoded = json_decode( sanitize_text_field( wp_unslash( $_POST['dsg_field_types'] ) ), true );
			if ( is_array( $decoded ) ) {
				$field_types = $decoded;
			}
		}

		$system_fields = array( 'dsg_website', 'dsg_form_id', 'dsg_timestamp', 'dsg_turnstile_token', 'dsg_field_types', '_wpnonce', 'action' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		foreach ( $_POST as $key => $value ) {
			// Skip system fields.
			if ( in_array( $key, $system_fields, true ) ) {
				continue;
			}

			$field_type = isset( $field_types[ $key ] ) ? sanitize_text_field( $field_types[ $key ] ) : 'text';

			$fields[] = array(
				'name'  => $key,
				'value' => sanitize_text_field( wp_unslash( $value ) ),
				'type'  => $field_type,
			);
		}

		$request = new \WP_REST_Request( 'POST' );
		$request->set_param( 'formId', $form_id );
		$request->set_param( 'fields', $fields );
		$request->set_param( 'honeypot', isset( $_POST['dsg_website'] ) ? sanitize_text_field( wp_unslash( $_POST['dsg_website'] ) ) : '' );
		$request->set_param( 'timestamp', isset( $_POST['dsg_timestamp'] ) ? sanitize_text_field( wp_unslash( $_POST['dsg_timestamp'] ) ) : '' );
		$request->set_param( 'turnstile_token', isset( $_POST['dsg_turnstile_token'] ) ? sanitize_text_field( wp_unslash( $_POST['dsg_turnstile_token'] ) ) : '' );

		$result = $this->handle_form_submission( $request );

		if ( is_wp_error( $result ) ) {
			$redirect = add_query_arg(
				array(
					'dsgo_form_status' => 'error',
					'dsgo_form_id'     => $form_id,
				),
				$referer
			);
		} else {
			$redirect = add_query_arg(
				array(
					'dsgo_form_status' => 'success',
					'dsgo_form_id'     => $form_id,
				),
				$referer
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Validate field based on type.
	 *
	 * @param mixed  $value Field value.
	 * @param string $type Field type.
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_field( $value, $type ) {
		// Skip validation for empty values (optional fields).
		// Required fields are validated by HTML5 on the frontend.
		if ( empty( $value ) && '0' !== $value ) {
			return true;
		}

		switch ( $type ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					return new WP_Error(
						'invalid_email',
						__( 'Invalid email address.', 'designsetgo' )
					);
				}
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) || ! preg_match( '/^https?:\/\//i', $value ) ) {
					return new WP_Error(
						'invalid_url',
						__( 'Invalid URL.', 'designsetgo' )
					);
				}
				break;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return new WP_Error(
						'invalid_number',
						__( 'Invalid number.', 'designsetgo' )
					);
				}
				break;

			case 'tel':
				// Basic phone validation - numbers, spaces, dashes, parentheses, plus.
				if ( ! preg_match( '/^[0-9\s\-\(\)\+]+$/', $value ) ) {
					return new WP_Error(
						'invalid_phone',
						__( 'Invalid phone number.', 'designsetgo' )
					);
				}
				break;
		}

		return true;
	}

	/**
	 * Sanitize field based on type.
	 *
	 * @param mixed  $value Field value.
	 * @param string $type Field type.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_field( $value, $type ) {
		switch ( $type ) {
			case 'email':
				return sanitize_email( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'number':
				return is_numeric( $value ) ? floatval( $value ) : 0;

			case 'tel':
				return preg_replace( '/[^0-9\s\-\(\)\+]/', '', $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Store form submission as custom post type.
	 *
	 * @param string $form_id Form ID.
	 * @param array  $fields Sanitized fields array.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	private function store_submission( $form_id, $fields ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'private',
				'post_title'  => sprintf(
					/* translators: %s: form ID */
					__( 'Form Submission - %s', 'designsetgo' ),
					$form_id
				),
				'post_date'   => current_time( 'mysql' ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Store form data as post meta.
		update_post_meta( $post_id, '_dsg_form_id', $form_id );
		update_post_meta( $post_id, '_dsg_form_fields', $fields );
		update_post_meta( $post_id, '_dsg_submission_ip', $this->security->get_client_ip() );
		update_post_meta( $post_id, '_dsg_submission_user_agent', $this->get_user_agent() );
		update_post_meta( $post_id, '_dsg_submission_referer', wp_get_referer() );
		update_post_meta( $post_id, '_dsg_submission_date', current_time( 'mysql' ) );

		// Clear cached form submission count.
		delete_transient( 'dsgo_form_submissions_count' );

		return $post_id;
	}

	/**
	 * Get user agent string.
	 *
	 * @return string User agent.
	 */
	private function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: 'unknown';
	}

	/**
	 * Localize script with nonce and REST URL.
	 */
	public function localize_form_script() {
		// Only enqueue if form block is present on the page.
		if ( ! has_block( 'designsetgo/form-builder' ) ) {
			return;
		}

		// Get the form-builder view script handle.
		$asset_file = include DESIGNSETGO_PATH . 'build/blocks/form-builder/view.asset.php';
		$handle     = 'designsetgo-form-builder-view-script';

		// Localize with nonce and REST URL.
		wp_localize_script(
			$handle,
			'designsetgoForm',
			array(
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'restUrl'      => rest_url( 'designsetgo/v1/form/submit' ),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'adminPostUrl' => admin_url( 'admin-post.php' ),
				'ajaxNonce'    => wp_create_nonce( 'designsetgo_form_submit' ),
			)
		);

		// Localize integrations settings for Turnstile.
		$settings              = get_option( 'designsetgo_settings', array() );
		$integrations_settings = isset( $settings['integrations'] ) ? $settings['integrations'] : array();

		wp_localize_script(
			$handle,
			'dsgoIntegrations',
			array(
				'turnstileSiteKey' => ! empty( $integrations_settings['turnstile_site_key'] ) ? esc_js( $integrations_settings['turnstile_site_key'] ) : '',
			)
		);
	}

	/**
	 * Send email notification with form submission data.
	 *
	 * Email configuration is read from the server-side block attributes (stored
	 * in post content), NOT from the client request. This prevents attackers from
	 * manipulating email recipients, sender addresses, or body content.
	 *
	 * @param string $form_id Form ID.
	 * @param array  $fields Sanitized form fields.
	 * @param int    $submission_id Submission post ID.
	 */
	private function send_email_notification( $form_id, $fields, $submission_id ) {
		// Look up email settings from the form block attributes (server-side only).
		// This prevents client-side manipulation of email configuration.
		$block_attrs = $this->get_form_block_attributes( $form_id );

		if ( ! $block_attrs || empty( $block_attrs['enableEmail'] ) ) {
			if ( ! $block_attrs && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging.
					sprintf( 'DesignSetGo Form: Could not find block attributes for form ID "%s".', $form_id )
				);
			}
			return;
		}

		// Extract raw values from block attributes.
		$email_to        = isset( $block_attrs['emailTo'] ) ? $block_attrs['emailTo'] : '';
		$email_subject   = isset( $block_attrs['emailSubject'] ) ? $block_attrs['emailSubject'] : '';
		$email_from_name = isset( $block_attrs['emailFromName'] ) ? $block_attrs['emailFromName'] : '';
		$email_from      = isset( $block_attrs['emailFromEmail'] ) ? $block_attrs['emailFromEmail'] : '';
		$email_body      = isset( $block_attrs['emailBody'] ) ? $block_attrs['emailBody'] : '';
		// emailReplyTo is a field NAME reference (e.g., "email"), not an email address.
		// The actual email value is extracted from submitted fields and sanitized below (line ~650).
		$email_reply_to  = isset( $block_attrs['emailReplyTo'] ) ? $block_attrs['emailReplyTo'] : '';

		// Strip newlines from header-used values to prevent injection (defense in depth).
		$newline_chars   = array( "\r", "\n", '%0a', '%0d' );
		$email_to        = str_replace( $newline_chars, '', $email_to );
		$email_subject   = str_replace( $newline_chars, '', $email_subject );
		$email_from_name = str_replace( $newline_chars, '', $email_from_name );
		$email_from      = str_replace( $newline_chars, '', $email_from );
		$email_reply_to  = str_replace( $newline_chars, '', $email_reply_to );

		// Validate and set defaults (single sanitization pass).
		if ( empty( $email_to ) || ! is_email( $email_to ) ) {
			$email_to = get_option( 'admin_email' );
		} else {
			$email_to = sanitize_email( $email_to );
		}

		if ( empty( $email_subject ) ) {
			$email_subject = __( 'New Form Submission', 'designsetgo' );
		} else {
			$email_subject = sanitize_text_field( $email_subject );
		}

		if ( empty( $email_from_name ) ) {
			$email_from_name = get_bloginfo( 'name' );
		} else {
			$email_from_name = sanitize_text_field( $email_from_name );
		}

		if ( empty( $email_from ) ) {
			// Use domain-matched email address for better deliverability.
			// This matches WordPress core and other plugins like CoBlocks.
			$sitename = wp_parse_url( network_home_url(), PHP_URL_HOST );
			if ( null !== $sitename ) {
				// Remove www prefix if present.
				if ( 'www.' === substr( $sitename, 0, 4 ) ) {
					$sitename = substr( $sitename, 4 );
				}
				$email_from = 'wordpress@' . $sitename;
			} else {
				// Fallback to admin email if we can't parse the domain.
				$email_from = get_option( 'admin_email' );
			}
		} else {
			$email_from = sanitize_email( $email_from );
			if ( ! is_email( $email_from ) ) {
				// Use domain-matched email address as fallback.
				$sitename = wp_parse_url( network_home_url(), PHP_URL_HOST );
				if ( null !== $sitename ) {
					if ( 'www.' === substr( $sitename, 0, 4 ) ) {
						$sitename = substr( $sitename, 4 );
					}
					$email_from = 'wordpress@' . $sitename;
				} else {
					$email_from = get_option( 'admin_email' );
				}
			}
		}

		// Prepare merge tags.
		$current_url = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$current_url = esc_url_raw(
				home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) )
			);
		}

		$merge_tags = array(
			'{form_id}'       => $form_id,
			'{submission_id}' => $submission_id,
			'{page_url}'      => $current_url,
			'{site_name}'     => get_bloginfo( 'name' ),
			'{date}'          => current_time( 'mysql' ),
		);

		// Add field values to merge tags.
		foreach ( $fields as $field_name => $field_data ) {
			$merge_tags[ '{' . $field_name . '}' ] = is_array( $field_data ) ? $field_data['value'] : $field_data;
		}

		// Build all_fields list.
		$all_fields_html = '';
		foreach ( $fields as $field_name => $field_data ) {
			$value            = is_array( $field_data ) ? $field_data['value'] : $field_data;
			$label            = ucwords( str_replace( array( '_', '-' ), ' ', $field_name ) );
			$all_fields_html .= sprintf( "<strong>%s:</strong> %s<br>\n", esc_html( $label ), esc_html( $value ) );
		}
		$merge_tags['{all_fields}'] = $all_fields_html;

		// Default email body if empty.
		if ( empty( $email_body ) ) {
			$email_body = __( "New form submission:\n\n{all_fields}\n\nSubmitted from: {page_url}", 'designsetgo' );
		} else {
			$email_body = sanitize_textarea_field( $email_body );
		}

		// Replace merge tags in subject and body.
		$email_subject = str_replace( array_keys( $merge_tags ), array_values( $merge_tags ), $email_subject );
		$email_body    = str_replace( array_keys( $merge_tags ), array_values( $merge_tags ), $email_body );

		// Convert line breaks to <br> for HTML email.
		$email_body = nl2br( $email_body );

		// Set up headers.
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $email_from_name, $email_from ),
		);

		// Add Reply-To if specified and field exists.
		if ( ! empty( $email_reply_to ) && isset( $fields[ $email_reply_to ] ) ) {
			$reply_to_value = is_array( $fields[ $email_reply_to ] ) ? $fields[ $email_reply_to ]['value'] : $fields[ $email_reply_to ];

			// Strip newlines to prevent email header injection.
			$reply_to_value = str_replace( array( "\r", "\n", '%0a', '%0d' ), '', $reply_to_value );
			$reply_to_value = sanitize_email( $reply_to_value );

			if ( is_email( $reply_to_value ) ) {
				$headers[] = sprintf( 'Reply-To: %s', $reply_to_value );
			}
		}

		// Send email.
		$email_sent = wp_mail( $email_to, $email_subject, $email_body, $headers );

		// Store email delivery status in submission meta.
		update_post_meta( $submission_id, '_dsg_email_sent', $email_sent ? 'yes' : 'no' );
		update_post_meta( $submission_id, '_dsg_email_to', $email_to );
		update_post_meta( $submission_id, '_dsg_email_sent_date', current_time( 'mysql' ) );

		// Log email delivery if enabled in settings.
		$form_settings        = $this->get_form_settings();
		$enable_email_logging = isset( $form_settings['enable_email_logging'] ) ? $form_settings['enable_email_logging'] : false;

		if ( $enable_email_logging ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging when enabled in settings.
				sprintf(
					'DesignSetGo Form: Email %s for submission #%d (To: %s, Subject: %s)',
					$email_sent ? 'sent successfully' : 'FAILED to send',
					$submission_id,
					$email_to,
					$email_subject
				)
			);
		}

		// Fire action hook for email monitoring/integration.
		do_action( 'designsetgo_form_email_sent', $submission_id, $form_id, $email_sent, $email_to, $email_subject );
	}

	/**
	 * Clean up old form submissions based on retention settings.
	 *
	 * Called daily by cron job to delete submissions older than the configured retention period.
	 * Respects the retention_days setting (default: 30 days).
	 *
	 * Processes in batches to prevent timeout issues on sites with large numbers of submissions.
	 * Use 'designsetgo_cleanup_batch_size' filter to adjust batch size (default: 100).
	 *
	 * @since 1.2.0
	 */
	public function cleanup_old_submissions() {
		$form_settings  = $this->get_form_settings();
		$retention_days = absint( $form_settings['retention_days'] );

		// If retention is 0, keep submissions indefinitely (disable cleanup).
		if ( 0 === $retention_days ) {
			return;
		}

		global $wpdb;

		// Calculate cutoff date.
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// Batch size to prevent timeout (filterable).
		$batch_size = apply_filters( 'designsetgo_cleanup_batch_size', 100 );

		// Find old submissions (limited batch to prevent timeout).
		$old_submissions = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_date < %s
				LIMIT %d",
				'dsgo_form_submission',
				$cutoff_date,
				$batch_size
			)
		);

		if ( empty( $old_submissions ) ) {
			return;
		}

		// Delete submissions and their metadata.
		foreach ( $old_submissions as $submission_id ) {
			wp_delete_post( $submission_id, true ); // Force delete (bypass trash).
		}

		// Clear form submissions count cache.
		delete_transient( 'dsgo_form_submissions_count' );

		// Log cleanup for debugging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging.
				sprintf(
					'DesignSetGo: Deleted %d form submissions older than %d days.',
					count( $old_submissions ),
					$retention_days
				)
			);
		}
	}

	/**
	 * Look up form block attributes from post content by form ID.
	 *
	 * Searches published posts for a form-builder block with the matching formId
	 * attribute. This ensures email configuration is read from the server-side
	 * block definition, not from client-submitted data.
	 *
	 * @param string $form_id Form identifier to look up.
	 * @return array|null Block attributes array, or null if not found.
	 */
	private function get_form_block_attributes( $form_id ) {
		// Check transient cache first to avoid LIKE queries on every submission.
		$cache_key = 'dsgo_form_attrs_' . md5( $form_id );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// Note: esc_like() wraps the entire concatenated string including $form_id,
		// so LIKE wildcards (%, _) in $form_id are escaped. prepare() handles SQL injection.
		// LIMIT 5 accounts for edge cases like revisions or duplicate formIds.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached via transient above.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
				WHERE post_content LIKE %s
				AND post_content LIKE %s
				AND post_status IN ('publish', 'private')
				LIMIT 5",
				'%' . $wpdb->esc_like( 'designsetgo/form-builder' ) . '%',
				'%' . $wpdb->esc_like( '"formId":"' . $form_id . '"' ) . '%'
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		foreach ( $posts as $post ) {
			$blocks = parse_blocks( $post->post_content );
			$attrs  = $this->find_form_block_attributes( $blocks, $form_id );
			if ( null !== $attrs ) {
				// Cache for 1 hour. Invalidated on save_post via clear_form_attributes_cache().
				set_transient( $cache_key, $attrs, HOUR_IN_SECONDS );
				return $attrs;
			}
		}

		return null;
	}

	/**
	 * Recursively search parsed blocks for a form-builder block with matching formId.
	 *
	 * @param array  $blocks  Parsed blocks array.
	 * @param string $form_id Form identifier to match.
	 * @return array|null Block attributes if found, null otherwise.
	 */
	private function find_form_block_attributes( $blocks, $form_id ) {
		foreach ( $blocks as $block ) {
			if (
				'designsetgo/form-builder' === $block['blockName'] &&
				isset( $block['attrs']['formId'] ) &&
				$block['attrs']['formId'] === $form_id
			) {
				return $block['attrs'];
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$result = $this->find_form_block_attributes( $block['innerBlocks'], $form_id );
				if ( null !== $result ) {
					return $result;
				}
			}
		}

		return null;
	}

	/**
	 * Look up server-defined field types for a form by form ID.
	 *
	 * Uses parsed block content so validation/sanitization does not rely on
	 * client-supplied field types.
	 *
	 * @param string $form_id Form identifier to look up.
	 * @return array<string, string> Field types keyed by field name.
	 */
	private function get_form_field_types( $form_id ) {
		$cache_key = 'dsgo_form_field_types_' . md5( $form_id );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached via transient above.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
				WHERE post_content LIKE %s
				AND post_content LIKE %s
				AND post_status IN ('publish', 'private')
				LIMIT 5",
				'%' . $wpdb->esc_like( 'designsetgo/form-builder' ) . '%',
				'%' . $wpdb->esc_like( '"formId":"' . $form_id . '"' ) . '%'
			)
		);

		if ( empty( $posts ) ) {
			return array();
		}

		foreach ( $posts as $post ) {
			$blocks      = parse_blocks( $post->post_content );
			$field_types = $this->find_form_field_types( $blocks, $form_id );

			if ( ! empty( $field_types ) ) {
				set_transient( $cache_key, $field_types, HOUR_IN_SECONDS );
				return $field_types;
			}
		}

		return array();
	}

	/**
	 * Recursively search parsed blocks for a form-builder block and extract field types.
	 *
	 * @param array  $blocks  Parsed blocks array.
	 * @param string $form_id Form identifier to match.
	 * @return array<string, string> Field types keyed by field name.
	 */
	private function find_form_field_types( $blocks, $form_id ) {
		foreach ( $blocks as $block ) {
			if (
				'designsetgo/form-builder' === $block['blockName'] &&
				isset( $block['attrs']['formId'] ) &&
				$block['attrs']['formId'] === $form_id
			) {
				return $this->extract_field_types_from_blocks(
					isset( $block['innerBlocks'] ) ? $block['innerBlocks'] : array()
				);
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$result = $this->find_form_field_types( $block['innerBlocks'], $form_id );
				if ( ! empty( $result ) ) {
					return $result;
				}
			}
		}

		return array();
	}

	/**
	 * Extract field types from a form block's inner blocks.
	 *
	 * @param array $blocks Parsed inner blocks.
	 * @return array<string, string> Field types keyed by field name.
	 */
	private function extract_field_types_from_blocks( $blocks ) {
		$field_types = array();

		foreach ( $blocks as $block ) {
			$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
			$field_name = isset( $block['attrs']['fieldName'] ) ? sanitize_text_field( $block['attrs']['fieldName'] ) : '';
			$field_type = $this->map_block_name_to_field_type( $block_name );

			if ( $field_name && $field_type ) {
				$field_types[ $field_name ] = $field_type;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$field_types = array_merge(
					$field_types,
					$this->extract_field_types_from_blocks( $block['innerBlocks'] )
				);
			}
		}

		return $field_types;
	}

	/**
	 * Map form field block names to server-side field types.
	 *
	 * @param string $block_name Block name.
	 * @return string|null Server-side field type, or null when unsupported.
	 */
	private function map_block_name_to_field_type( $block_name ) {
		switch ( $block_name ) {
			case 'designsetgo/form-text-field':
				return 'text';

			case 'designsetgo/form-email-field':
				return 'email';

			case 'designsetgo/form-textarea-field':
				return 'textarea';

			case 'designsetgo/form-number-field':
				return 'number';

			case 'designsetgo/form-phone-field':
				return 'tel';

			case 'designsetgo/form-url-field':
				return 'url';

			case 'designsetgo/form-date-field':
				return 'date';

			case 'designsetgo/form-time-field':
				return 'time';

			case 'designsetgo/form-select-field':
				return 'select';

			case 'designsetgo/form-checkbox-field':
				return 'checkbox';

			case 'designsetgo/form-hidden-field':
				return 'hidden';

			default:
				return null;
		}
	}

	/**
	 * Clear cached form block attributes when a post is saved.
	 *
	 * Hooked to save_post to ensure email config changes take effect immediately.
	 *
	 * @param int $post_id Post ID being saved.
	 */
	public function clear_form_attributes_cache( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || false === strpos( $post->post_content, 'designsetgo/form-builder' ) ) {
			return;
		}

		$blocks = parse_blocks( $post->post_content );
		$this->invalidate_form_block_transients( $blocks );
	}

	/**
	 * Recursively delete cached transients for form blocks.
	 *
	 * @param array $blocks Parsed blocks array.
	 */
	private function invalidate_form_block_transients( $blocks ) {
		foreach ( $blocks as $block ) {
			if (
				'designsetgo/form-builder' === $block['blockName'] &&
				isset( $block['attrs']['formId'] )
			) {
				delete_transient( 'dsgo_form_attrs_' . md5( $block['attrs']['formId'] ) );
				delete_transient( 'dsgo_form_field_types_' . md5( $block['attrs']['formId'] ) );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->invalidate_form_block_transients( $block['innerBlocks'] );
			}
		}
	}
}
