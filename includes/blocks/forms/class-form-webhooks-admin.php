<?php
/**
 * Webhook delivery UI on the form submissions screen.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Webhooks_Admin.
 */
class Form_Webhooks_Admin {

	/**
	 * Delivery service.
	 *
	 * @var Form_Webhooks
	 */
	private $webhooks;

	/**
	 * Constructor.
	 *
	 * @param Form_Webhooks $webhooks Delivery service.
	 */
	public function __construct( Form_Webhooks $webhooks ) {
		$this->webhooks = $webhooks;

		add_filter( 'manage_dsgo_form_submission_posts_columns', array( $this, 'add_column' ), 20 );
		add_action( 'manage_dsgo_form_submission_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'add_meta_boxes_dsgo_form_submission', array( $this, 'maybe_add_meta_box' ) );
		add_action( 'admin_post_' . Form_Webhooks::RESEND_ACTION, array( $this, 'handle_resend' ) );
		add_action( 'admin_notices', array( $this, 'resend_notice' ) );
		add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
	}

	/**
	 * Insert the Webhook column before Date.
	 *
	 * @param array $columns Columns.
	 * @return array Columns.
	 */
	public function add_column( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$out['dsgo_webhook'] = __( 'Webhook', 'designsetgo' );
			}
			$out[ $key ] = $label;
		}
		if ( ! isset( $out['dsgo_webhook'] ) ) {
			$out['dsgo_webhook'] = __( 'Webhook', 'designsetgo' );
		}
		return $out;
	}

	/**
	 * Render the Webhook column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Submission ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'dsgo_webhook' !== $column ) {
			return;
		}
		$label = Form_Webhook_Status::label( (int) $post_id );
		if ( '' === $label ) {
			echo '<span style="color: #999;">—</span>';
			return;
		}
		$colors = array(
			'delivered' => '#46b450',
			'failed'    => '#dc3232',
			'pending'   => '#996800',
		);
		$status = Form_Webhook_Status::get_status( (int) $post_id );
		$color  = isset( $colors[ $status ] ) ? $colors[ $status ] : 'inherit';
		echo '<span style="color: ' . esc_attr( $color ) . ';">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Add a Resend webhook row action.
	 *
	 * @param array    $actions Row actions.
	 * @param \WP_Post $post    Post.
	 * @return array Row actions.
	 */
	public function row_actions( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || 'dsgo_form_submission' !== $post->post_type || ! $this->has_webhook( $post->ID ) ) {
			return $actions;
		}
		$actions['dsgo_resend_webhook'] = '<a href="' . esc_url( Form_Webhook_Status::resend_url( $post->ID ) ) . '">' . esc_html__( 'Resend webhook', 'designsetgo' ) . '</a>';
		return $actions;
	}

	/**
	 * Register the delivery meta box for submissions that have a webhook.
	 *
	 * @param \WP_Post $post Submission.
	 */
	public function maybe_add_meta_box( $post ) {
		if ( ! $this->has_webhook( $post->ID ) ) {
			return;
		}
		add_meta_box( 'dsgo_webhook_delivery', __( 'Webhook delivery', 'designsetgo' ), array( $this, 'render_meta_box' ), 'dsgo_form_submission', 'side', 'default' );
	}

	/**
	 * Render delivery details. Shows only the receiver's host: some webhook URLs are credentials.
	 *
	 * @param \WP_Post $post Submission.
	 */
	public function render_meta_box( $post ) {
		$id     = (int) $post->ID;
		$host   = (string) wp_parse_url( (string) get_post_meta( $id, '_dsg_webhook_url', true ), PHP_URL_HOST );
		$code   = (int) get_post_meta( $id, '_dsg_webhook_last_code', true );
		$error  = (string) get_post_meta( $id, '_dsg_webhook_last_error', true );
		$date   = (string) get_post_meta( $id, '_dsg_webhook_delivered_date', true );
		$rows   = array(
			__( 'Status', 'designsetgo' )   => Form_Webhook_Status::label( $id ),
			__( 'Receiver', 'designsetgo' ) => $host,
			__( 'Attempts', 'designsetgo' ) => (string) (int) get_post_meta( $id, '_dsg_webhook_attempts', true ),
		);
		$signed = (string) get_post_meta( $id, '_dsg_webhook_signed', true );
		if ( '' !== $signed ) {
			$rows[ __( 'Signature', 'designsetgo' ) ] = 'yes' === $signed
				? __( 'Signed', 'designsetgo' )
				: __( 'Unsigned — no webhook secret configured', 'designsetgo' );
		}
		if ( $code ) {
			/* translators: %d: HTTP status code */
			$rows[ __( 'Last response', 'designsetgo' ) ] = sprintf( __( 'HTTP %d', 'designsetgo' ), $code );
		}
		if ( '' !== $error ) {
			$rows[ __( 'Last error', 'designsetgo' ) ] = $error;
		}
		if ( '' !== $date ) {
			$rows[ __( 'Delivered', 'designsetgo' ) ] = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date );
		}

		echo '<table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><a class="button" href="' . esc_url( Form_Webhook_Status::resend_url( $id ) ) . '">' . esc_html__( 'Resend webhook', 'designsetgo' ) . '</a></p>';
	}

	/**
	 * Handle the admin-post Resend request.
	 */
	public function handle_resend() {
		$submission_id = isset( $_GET['submission'] ) ? absint( wp_unslash( $_GET['submission'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified on the next line.
		check_admin_referer( Form_Webhooks::RESEND_ACTION . '_' . $submission_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to resend webhooks.', 'designsetgo' ), '', array( 'response' => 403 ) );
		}

		$result   = $this->webhooks->resend( $submission_id );
		$fallback = admin_url( 'edit.php?post_type=dsgo_form_submission' );
		$referer  = wp_get_referer();

		wp_safe_redirect( add_query_arg( 'dsgo_webhook_resent', $result, $referer ? $referer : $fallback ) );
		exit;
	}

	/**
	 * Let WordPress strip the resend flag from the URL so the notice does not persist.
	 *
	 * @param string[] $args Removable query args.
	 * @return string[] Args.
	 */
	public function removable_query_args( $args ) {
		$args   = (array) $args;
		$args[] = 'dsgo_webhook_resent';
		return $args;
	}

	/**
	 * Notice after a resend.
	 */
	public function resend_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag set by our own redirect.
		$result = isset( $_GET['dsgo_webhook_resent'] ) ? sanitize_key( wp_unslash( $_GET['dsgo_webhook_resent'] ) ) : '';
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( '' === $result || ! $screen || 'dsgo_form_submission' !== $screen->post_type ) {
			return;
		}

		$messages = array(
			'delivered' => array( 'success', __( 'Webhook delivered.', 'designsetgo' ) ),
			'pending'   => array( 'warning', __( 'Webhook delivery failed; it will be retried automatically.', 'designsetgo' ) ),
			'failed'    => array( 'error', __( 'Webhook delivery failed. See the submission for details.', 'designsetgo' ) ),
			'invalid'   => array( 'error', __( 'This submission has no webhook to resend.', 'designsetgo' ) ),
		);
		if ( ! isset( $messages[ $result ] ) ) {
			return;
		}
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $result ][0] ), esc_html( $messages[ $result ][1] ) );
	}

	/**
	 * Whether a submission has a webhook URL recorded.
	 *
	 * @param int $post_id Submission ID.
	 * @return bool
	 */
	private function has_webhook( $post_id ) {
		return '' !== (string) get_post_meta( (int) $post_id, '_dsg_webhook_url', true );
	}
}
