<?php
/**
 * Form Submissions Custom Post Type
 *
 * Registers and manages form submission post type and admin UI.
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

namespace DesignSetGo\Blocks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form_Submissions class.
 */
class Form_Submissions {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_filter( 'manage_dsgo_form_submission_posts_columns', array( $this, 'custom_columns' ) );
		add_action( 'manage_dsgo_form_submission_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );
	}

	/**
	 * Register custom post type for form submissions.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Form Submissions', 'post type general name', 'designsetgo' ),
			'singular_name'      => _x( 'Form Submission', 'post type singular name', 'designsetgo' ),
			'menu_name'          => _x( 'Form Submissions', 'admin menu', 'designsetgo' ),
			'name_admin_bar'     => _x( 'Form Submission', 'add new on admin bar', 'designsetgo' ),
			'all_items'          => __( 'All Submissions', 'designsetgo' ),
			'view_item'          => __( 'View Submission', 'designsetgo' ),
			'search_items'       => __( 'Search Submissions', 'designsetgo' ),
			'not_found'          => __( 'No submissions found', 'designsetgo' ),
			'not_found_in_trash' => __( 'No submissions found in Trash', 'designsetgo' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'designsetgo', // Show under DesignSetGo menu.
			'query_var'          => true,
			'rewrite'            => false,
			'show_in_rest'       => false, // Prevent REST API access.
			'capability_type'    => 'dsgo_form_submission',
			'capabilities'       => array(
				'edit_post'              => 'manage_options',
				'read_post'              => 'manage_options',
				'delete_post'            => 'manage_options',
				'edit_posts'             => 'manage_options',
				'edit_others_posts'      => 'manage_options',
				'edit_private_posts'     => 'manage_options',
				'edit_published_posts'   => 'manage_options',
				'delete_posts'           => 'manage_options',
				'delete_others_posts'    => 'manage_options',
				'delete_private_posts'   => 'manage_options',
				'delete_published_posts' => 'manage_options',
				'publish_posts'          => 'manage_options',
				'read_private_posts'     => 'manage_options',
				'create_posts'           => 'do_not_allow', // Prevent manual creation - only via form submission.
			),
			'map_meta_cap'       => false, // Disabled - all capabilities explicitly mapped to manage_options.
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title' ),
		);

		register_post_type( 'dsgo_form_submission', $args );
	}

	/**
	 * Add meta boxes to submission edit screen.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'dsg_submission_details',
			__( 'Submission Details', 'designsetgo' ),
			array( $this, 'render_submission_details' ),
			'dsgo_form_submission',
			'normal',
			'high'
		);

		add_meta_box(
			'dsg_submission_meta',
			__( 'Submission Information', 'designsetgo' ),
			array( $this, 'render_submission_meta' ),
			'dsgo_form_submission',
			'side',
			'default'
		);
	}

	/**
	 * Render submission details meta box.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_submission_details( $post ) {
		$fields = get_post_meta( $post->ID, '_dsg_form_fields', true );

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			echo '<p>' . esc_html__( 'No form data available.', 'designsetgo' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Field Name', 'designsetgo' ) . '</th>';
		echo '<th>' . esc_html__( 'Value', 'designsetgo' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'designsetgo' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $fields as $field_name => $field_data ) {
			$value = isset( $field_data['value'] ) ? $field_data['value'] : '';
			$type  = isset( $field_data['type'] ) ? $field_data['type'] : 'text';

			// Format value based on type.
			if ( 'email' === $type ) {
				$value = '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
			} elseif ( 'url' === $type ) {
				$value = '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener">' . esc_html( $value ) . '</a>';
			} elseif ( 'textarea' === $type ) {
				$value = '<div style="white-space: pre-wrap;">' . esc_html( $value ) . '</div>';
			} else {
				$value = esc_html( $value );
			}

			echo '<tr>';
			echo '<td><strong>' . esc_html( $field_name ) . '</strong></td>';
			echo '<td>' . $value . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td><code>' . esc_html( $type ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Render submission meta information.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_submission_meta( $post ) {
		$form_id         = get_post_meta( $post->ID, '_dsg_form_id', true );
		$ip_address      = get_post_meta( $post->ID, '_dsg_submission_ip', true );
		$user_agent      = get_post_meta( $post->ID, '_dsg_submission_user_agent', true );
		$referer         = get_post_meta( $post->ID, '_dsg_submission_referer', true );
		$date            = get_post_meta( $post->ID, '_dsg_submission_date', true );
		$email_sent      = get_post_meta( $post->ID, '_dsg_email_sent', true );
		$email_to        = get_post_meta( $post->ID, '_dsg_email_to', true );
		$email_sent_date = get_post_meta( $post->ID, '_dsg_email_sent_date', true );

		echo '<div style="margin-bottom: 1em;">';
		echo '<strong>' . esc_html__( 'Form ID:', 'designsetgo' ) . '</strong><br>';
		echo '<code>' . esc_html( $form_id ) . '</code>';
		echo '</div>';

		if ( $date ) {
			echo '<div style="margin-bottom: 1em;">';
			echo '<strong>' . esc_html__( 'Submitted:', 'designsetgo' ) . '</strong><br>';
			echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date ) ) );
			echo '</div>';
		}

		// Email delivery status.
		if ( '' !== $email_sent ) {
			$is_sent      = ( 'yes' === $email_sent );
			$status_class = $is_sent ? 'dsgo-submission-status--success' : 'dsgo-submission-status--error';

			// Emit the status styles once per page load via a static guard.
			static $dsgo_status_styles_printed = false;
			if ( ! $dsgo_status_styles_printed ) {
				$dsgo_status_styles_printed = true;
				echo '<style>
.dsgo-submission-status{margin-bottom:1em;padding:10px;border-left:3px solid}
.dsgo-submission-status--success{background:#d4edda;border-color:#28a745}
.dsgo-submission-status--success .dsgo-submission-status__label{color:#155724}
.dsgo-submission-status--error{background:#f8d7da;border-color:#dc3545}
.dsgo-submission-status--error .dsgo-submission-status__label{color:#721c24}
</style>';
			}

			printf( '<div class="dsgo-submission-status %s">', esc_attr( $status_class ) );
			echo '<strong>' . esc_html__( 'Email Status:', 'designsetgo' ) . '</strong><br>';
			if ( $is_sent ) {
				echo '<span class="dsgo-submission-status__label">&#10003; ' . esc_html__( 'Sent Successfully', 'designsetgo' ) . '</span>';
				if ( $email_to ) {
					echo '<br><small>' . esc_html__( 'To:', 'designsetgo' ) . ' ' . esc_html( $email_to ) . '</small>';
				}
				if ( $email_sent_date ) {
					echo '<br><small>' . esc_html__( 'Sent:', 'designsetgo' ) . ' ' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $email_sent_date ) ) . '</small>';
				}
			} else {
				echo '<span class="dsgo-submission-status__label">&#10007; ' . esc_html__( 'Failed to Send', 'designsetgo' ) . '</span>';
				if ( $email_to ) {
					echo '<br><small>' . esc_html__( 'Attempted to:', 'designsetgo' ) . ' ' . esc_html( $email_to ) . '</small>';
				}
			}
			echo '</div>';
		}

		if ( $ip_address ) {
			echo '<div style="margin-bottom: 1em;">';
			echo '<strong>' . esc_html__( 'IP Address:', 'designsetgo' ) . '</strong><br>';
			echo '<code>' . esc_html( $ip_address ) . '</code>';
			echo '</div>';
		}

		if ( $referer ) {
			echo '<div style="margin-bottom: 1em;">';
			echo '<strong>' . esc_html__( 'Referrer:', 'designsetgo' ) . '</strong><br>';
			echo '<a href="' . esc_url( $referer ) . '" target="_blank" rel="noopener">' . esc_html( $referer ) . '</a>';
			echo '</div>';
		}

		if ( $user_agent ) {
			echo '<div style="margin-bottom: 1em;">';
			echo '<strong>' . esc_html__( 'User Agent:', 'designsetgo' ) . '</strong><br>';
			echo '<code style="word-break: break-all;">' . esc_html( $user_agent ) . '</code>';
			echo '</div>';
		}
	}

	/**
	 * Customize admin columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function custom_columns( $columns ) {
		$new_columns = array(
			'cb'           => $columns['cb'],
			'title'        => __( 'Submission', 'designsetgo' ),
			'form_id'      => __( 'Form ID', 'designsetgo' ),
			'email_status' => __( 'Email', 'designsetgo' ),
			'ip_address'   => __( 'IP Address', 'designsetgo' ),
			'date'         => __( 'Date', 'designsetgo' ),
		);

		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 */
	public function custom_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'form_id':
				$form_id = get_post_meta( $post_id, '_dsg_form_id', true );
				echo $form_id ? '<code>' . esc_html( $form_id ) . '</code>' : '—';
				break;

			case 'email_status':
				$email_sent = get_post_meta( $post_id, '_dsg_email_sent', true );
				if ( '' === $email_sent ) {
					echo '<span style="color: #999;">—</span>';
				} elseif ( 'yes' === $email_sent ) {
					echo '<span style="color: #46b450;" title="' . esc_attr__( 'Email sent successfully', 'designsetgo' ) . '">✓ ' . esc_html__( 'Sent', 'designsetgo' ) . '</span>';
				} else {
					echo '<span style="color: #dc3232;" title="' . esc_attr__( 'Email failed to send', 'designsetgo' ) . '">✗ ' . esc_html__( 'Failed', 'designsetgo' ) . '</span>';
				}
				break;

			case 'ip_address':
				$ip_address = get_post_meta( $post_id, '_dsg_submission_ip', true );
				echo $ip_address ? '<code>' . esc_html( $ip_address ) . '</code>' : '—';
				break;
		}
	}
}
