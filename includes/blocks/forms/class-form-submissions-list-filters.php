<?php
/**
 * Form submission list screen filters.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Submissions_List_Filters.
 *
 * Form / date filters and the Export button above the submissions list.
 */
class Form_Submissions_List_Filters {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'restrict_manage_posts', array( $this, 'render_filters' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_list' ) );
	}

	/**
	 * Form / date filters and the Export button above the list table.
	 *
	 * @param string $post_type Current list's post type.
	 */
	public function render_filters( $post_type ): void {
		if ( Form_Submissions_Export::POST_TYPE !== $post_type ) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only distinct list; no API for distinct meta values.
		$forms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS form_id, COUNT(*) AS total FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s GROUP BY pm.meta_value ORDER BY pm.meta_value",
				'_dsg_form_id',
				Form_Submissions_Export::POST_TYPE
			)
		);
		$current = Form_Submissions_Export::request_args( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.

		echo '<label class="screen-reader-text" for="dsgo-form-filter">' . esc_html__( 'Filter by form', 'designsetgo' ) . '</label>';
		echo '<select name="dsgo_form" id="dsgo-form-filter"><option value="">' . esc_html__( 'All forms', 'designsetgo' ) . '</option>';
		foreach ( (array) $forms as $form ) {
			printf(
				'<option value="%1$s"%2$s>%3$s (%4$d)</option>',
				esc_attr( $form->form_id ),
				selected( $current['form_id'], $form->form_id, false ),
				esc_html( $form->form_id ),
				(int) $form->total
			);
		}
		echo '</select>';

		printf(
			'<label for="dsgo-from">%1$s</label> <input type="date" id="dsgo-from" name="dsgo_from" value="%2$s"> <label for="dsgo-to">%3$s</label> <input type="date" id="dsgo-to" name="dsgo_to" value="%4$s">',
			esc_html__( 'From', 'designsetgo' ),
			esc_attr( $current['from'] ),
			esc_html__( 'To', 'designsetgo' ),
			esc_attr( $current['to'] )
		);

		wp_nonce_field( Form_Submissions_Export::NONCE_ACTION, Form_Submissions_Export::NONCE_NAME, false );
		echo '<button type="submit" name="dsgo_export" value="1" class="button">' . esc_html__( 'Export CSV', 'designsetgo' ) . '</button>';
	}

	/**
	 * Apply the filters to the submissions list.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function filter_list( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || Form_Submissions_Export::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		foreach ( Form_Submissions_Export::query_args( Form_Submissions_Export::request_args( $_GET ) ) as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
			$query->set( $key, $value );
		}
	}
}
