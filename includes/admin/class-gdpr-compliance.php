<?php
/**
 * GDPR Compliance Class
 *
 * Handles GDPR data privacy compliance for form submissions.
 * Integrates with WordPress's built-in privacy tools (WP 4.9.6+).
 *
 * Features:
 * - Personal data export (JSON format)
 * - Personal data erasure (right to be forgotten)
 * - Privacy policy text suggestions
 * - Data retention settings
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

namespace DesignSetGo\Admin;

use WP_Query;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GDPR_Compliance class.
 */
class GDPR_Compliance {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register privacy exporters and erasers.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_data_eraser' ) );

		// Add privacy policy text.
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );

		// Add REST API endpoints for manual data requests.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Add admin notices for data retention.
		add_action( 'admin_notices', array( $this, 'data_retention_notice' ) );
	}

	/**
	 * Register data exporter for WordPress privacy tools.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array Modified exporters.
	 */
	public function register_data_exporter( $exporters ) {
		$exporters['designsetgo-form-submissions'] = array(
			'exporter_friendly_name' => __( 'DesignSetGo Form Submissions', 'designsetgo' ),
			'callback'               => array( $this, 'export_form_submissions' ),
		);

		return $exporters;
	}

	/**
	 * Register data eraser for WordPress privacy tools.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array Modified erasers.
	 */
	public function register_data_eraser( $erasers ) {
		$erasers['designsetgo-form-submissions'] = array(
			'eraser_friendly_name' => __( 'DesignSetGo Form Submissions', 'designsetgo' ),
			'callback'             => array( $this, 'erase_form_submissions' ),
		);

		return $erasers;
	}

	/**
	 * Export form submissions for a given email address.
	 *
	 * @param string $email_address Email address to export data for.
	 * @param int    $page          Page number (for pagination).
	 * @return array Export data.
	 */
	public function export_form_submissions( $email_address, $page = 1 ) {
		$page           = (int) $page;
		$export_items   = array();
		$items_per_page = 100;

		// Find form submissions that contain this email address.
		$args = array(
			'post_type'      => 'dsgo_form_submission',
			'posts_per_page' => $items_per_page,
			'paged'          => $page,
			'post_status'    => 'private',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$submissions = new WP_Query( $args );

		if ( $submissions->have_posts() ) {
			while ( $submissions->have_posts() ) {
				$submissions->the_post();
				$post_id = get_the_ID();

				// Get form fields.
				$fields = get_post_meta( $post_id, '_dsg_form_fields', true );

				// Check if this submission contains the email address.
				$contains_email = $this->submission_contains_email( $fields, $email_address );

				if ( $contains_email ) {
					$item_id = "form-submission-{$post_id}";

					// Prepare data for export.
					$data = array(
						array(
							'name'  => __( 'Submission ID', 'designsetgo' ),
							'value' => $post_id,
						),
						array(
							'name'  => __( 'Form ID', 'designsetgo' ),
							'value' => get_post_meta( $post_id, '_dsg_form_id', true ),
						),
						array(
							'name'  => __( 'Submission Date', 'designsetgo' ),
							'value' => get_post_meta( $post_id, '_dsg_submission_date', true ),
						),
					);

					// Add form fields to export.
					if ( is_array( $fields ) ) {
						foreach ( $fields as $field_name => $field_data ) {
							$value  = is_array( $field_data ) ? $field_data['value'] : $field_data;
							$data[] = array(
								'name'  => ucwords( str_replace( array( '_', '-' ), ' ', $field_name ) ),
								'value' => $value,
							);
						}
					}

					// Add metadata.
					$ip_address = get_post_meta( $post_id, '_dsg_submission_ip', true );
					if ( ! empty( $ip_address ) ) {
						$data[] = array(
							'name'  => __( 'IP Address', 'designsetgo' ),
							'value' => $ip_address,
						);
					}

					$user_agent = get_post_meta( $post_id, '_dsg_submission_user_agent', true );
					if ( ! empty( $user_agent ) ) {
						$data[] = array(
							'name'  => __( 'User Agent', 'designsetgo' ),
							'value' => $user_agent,
						);
					}

					$referer = get_post_meta( $post_id, '_dsg_submission_referer', true );
					if ( ! empty( $referer ) ) {
						$data[] = array(
							'name'  => __( 'Referrer', 'designsetgo' ),
							'value' => $referer,
						);
					}

					$export_items[] = array(
						'group_id'    => 'designsetgo-form-submissions',
						'group_label' => __( 'Form Submissions', 'designsetgo' ),
						'item_id'     => $item_id,
						'data'        => $data,
					);
				}
			}

			wp_reset_postdata();
		}

		return array(
			'data' => $export_items,
			'done' => count( $export_items ) < $items_per_page,
		);
	}

	/**
	 * Erase form submissions for a given email address.
	 *
	 * @param string $email_address Email address to erase data for.
	 * @param int    $page          Page number (for pagination).
	 * @return array Erasure results.
	 */
	public function erase_form_submissions( $email_address, $page = 1 ) {
		$page           = (int) $page;
		$items_removed  = false;
		$items_retained = false;
		$messages       = array();
		$items_per_page = 100;

		// Find form submissions that contain this email address.
		$args = array(
			'post_type'      => 'dsgo_form_submission',
			'posts_per_page' => $items_per_page,
			'paged'          => $page,
			'post_status'    => 'private',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$submissions = new WP_Query( $args );

		if ( $submissions->have_posts() ) {
			while ( $submissions->have_posts() ) {
				$submissions->the_post();
				$post_id = get_the_ID();

				// Get form fields.
				$fields = get_post_meta( $post_id, '_dsg_form_fields', true );

				// Check if this submission contains the email address.
				$contains_email = $this->submission_contains_email( $fields, $email_address );

				if ( $contains_email ) {
					// Allow plugins to prevent deletion.
					$can_delete = apply_filters( 'designsetgo_can_delete_form_submission', true, $post_id, $email_address );

					if ( $can_delete ) {
						// Delete the submission.
						$deleted = wp_delete_post( $post_id, true );

						if ( $deleted ) {
							$items_removed = true;
							do_action( 'designsetgo_form_submission_erased', $post_id, $email_address );
						} else {
							$items_retained = true;
							$messages[]     = sprintf(
								/* translators: %d: submission ID */
								__( 'Form submission %d could not be deleted.', 'designsetgo' ),
								$post_id
							);
						}
					} else {
						$items_retained = true;
						$messages[]     = sprintf(
							/* translators: %d: submission ID */
							__( 'Form submission %d was retained by plugin filter.', 'designsetgo' ),
							$post_id
						);
					}
				}
			}

			wp_reset_postdata();
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Check if a submission contains a specific email address.
	 *
	 * @param array  $fields        Form fields.
	 * @param string $email_address Email address to search for.
	 * @return bool True if email found.
	 */
	private function submission_contains_email( $fields, $email_address ) {
		if ( ! is_array( $fields ) ) {
			return false;
		}

		foreach ( $fields as $field_data ) {
			$value = is_array( $field_data ) ? $field_data['value'] : $field_data;
			$type  = is_array( $field_data ) && isset( $field_data['type'] ) ? $field_data['type'] : 'text';

			// Check email fields or any field containing the email.
			if ( 'email' === $type && strtolower( $value ) === strtolower( $email_address ) ) {
				return true;
			}

			// Also check if email appears in any other field.
			if ( is_string( $value ) && false !== stripos( $value, $email_address ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add suggested privacy policy text.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = $this->get_privacy_policy_text();

		wp_add_privacy_policy_content(
			__( 'DesignSetGo Forms', 'designsetgo' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	/**
	 * Get suggested privacy policy text.
	 *
	 * @return string Privacy policy text.
	 */
	private function get_privacy_policy_text() {
		$content = sprintf(
			/* translators: %s: plugin name */
			__(
				'## What data we collect when you submit a form

When you submit a form on this website using %s, we collect the following information:

**Information you provide:**
- All data you enter into form fields (name, email address, message, etc.)
- The date and time of your submission

**Automatically collected information:**
- Your IP address (if enabled in settings)
- Your browser user agent string (if enabled in settings)
- The page URL where you submitted the form (if enabled in settings)

## How we use your data

We use the information you provide to:
- Respond to your inquiries
- Process your requests
- Improve our services
- Comply with legal obligations

## How long we retain your data

Form submissions are retained indefinitely unless you request deletion. You can request deletion of your personal data at any time using the contact information below or through the automated privacy tools on this site.

## Your rights under GDPR

If you are located in the European Union, you have the following rights:
- **Right to access**: You can request a copy of your personal data
- **Right to rectification**: You can request correction of inaccurate data
- **Right to erasure**: You can request deletion of your personal data
- **Right to data portability**: You can request your data in a portable format
- **Right to object**: You can object to processing of your personal data

To exercise these rights, please use our privacy tools or contact us directly.

## Automated spam prevention

Our forms use automated spam prevention measures including:
- Honeypot fields (invisible to users, visible to bots)
- Time-based submission checks
- Rate limiting by IP address

These features help protect you and us from spam and abuse.

## Data security

We implement appropriate security measures to protect your personal data from unauthorized access, alteration, disclosure, or destruction. However, no method of transmission over the internet is 100%% secure.',
				'designsetgo'
			),
			'DesignSetGo'
		);

		return $content;
	}

	/**
	 * Register REST API routes for GDPR functionality.
	 */
	public function register_rest_routes() {
		// Export data endpoint.
		register_rest_route(
			'designsetgo/v1',
			'/gdpr/export',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_data_export_request' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'email' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
					),
				),
			)
		);

		// Delete data endpoint.
		register_rest_route(
			'designsetgo/v1',
			'/gdpr/delete',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'handle_data_deletion_request' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'email' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Handle data export request via REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function handle_data_export_request( $request ) {
		$email = $request->get_param( 'email' );

		// Export all pages of data.
		$all_data = array();
		$page     = 1;
		$done     = false;

		while ( ! $done ) {
			$result   = $this->export_form_submissions( $email, $page );
			$all_data = array_merge( $all_data, $result['data'] );
			$done     = $result['done'];
			++$page;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'email'   => $email,
				'count'   => count( $all_data ),
				'data'    => $all_data,
			)
		);
	}

	/**
	 * Handle data deletion request via REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function handle_data_deletion_request( $request ) {
		$email = $request->get_param( 'email' );

		// Delete all submissions.
		$result = $this->erase_form_submissions( $email, 1 );

		return rest_ensure_response(
			array(
				'success'        => $result['items_removed'],
				'email'          => $email,
				'items_removed'  => $result['items_removed'],
				'items_retained' => $result['items_retained'],
				'messages'       => $result['messages'],
			)
		);
	}

	/**
	 * Check admin permission for REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error True if user has permission, WP_Error on failure.
	 */
	public function check_admin_permission( $request ) {
		// Check capability first.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Then check nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'invalid_nonce',
				__( 'Invalid security token.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Display admin notice about data retention.
	 */
	public function data_retention_notice() {
		$screen = get_current_screen();

		// Only show on form submissions list.
		if ( ! $screen || 'edit-dsgo_form_submission' !== $screen->id ) {
			return;
		}

		// Check if notice has been dismissed.
		$dismissed = get_user_meta( get_current_user_id(), 'dsg_gdpr_notice_dismissed', true );
		if ( $dismissed ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible" data-dsgo-notice="gdpr">
			<p>
				<strong><?php esc_html_e( 'GDPR Compliance:', 'designsetgo' ); ?></strong>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %1$s: privacy tools URL, %2$s: privacy policy URL */
						__( 'Form submissions may contain personal data. Use the <a href="%1$s">Privacy Tools</a> to export or erase user data. Add our suggested text to your <a href="%2$s">Privacy Policy</a>.', 'designsetgo' ),
						admin_url( 'tools.php?page=export_personal_data' ),
						admin_url( 'privacy.php' )
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get form submission statistics.
	 *
	 * @return array Statistics.
	 */
	public function get_statistics() {
		$total_query = new WP_Query(
			array(
				'post_type'      => 'dsgo_form_submission',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);
		$total = $total_query->found_posts;

		// Get submissions older than 30 days.
		$thirty_days_ago   = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$old_query = new WP_Query(
			array(
				'post_type'      => 'dsgo_form_submission',
				'post_status'    => 'any',
				'date_query'     => array(
					array(
						'before' => $thirty_days_ago,
					),
				),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);
		$old_submissions = $old_query->found_posts;

		return array(
			'total'           => (int) $total,
			'old_submissions' => (int) $old_submissions,
		);
	}
}
