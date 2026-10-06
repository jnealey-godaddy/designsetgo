<?php
/**
 * Form webhook delivery status helpers.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Webhook_Status.
 */
class Form_Webhook_Status {

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
	public static function label( int $submission_id ): string {
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
					'action'     => Form_Webhooks::RESEND_ACTION,
					'submission' => $submission_id,
				),
				admin_url( 'admin-post.php' )
			),
			Form_Webhooks::RESEND_ACTION . '_' . $submission_id
		);
	}
}
