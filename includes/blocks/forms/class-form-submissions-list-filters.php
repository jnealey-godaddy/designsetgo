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

		$forms   = self::forms();
		$current = Form_Submissions_Export::request_args( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.

		echo '<label class="screen-reader-text" for="dsgo-form-filter">' . esc_html__( 'Filter by form', 'designsetgo' ) . '</label>';
		echo '<select name="dsgo_form" id="dsgo-form-filter"><option value="">' . esc_html__( 'All forms', 'designsetgo' ) . '</option>';
		foreach ( $forms as $form ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $form->form_id ),
				selected( $current['form_id'], $form->form_id, false ),
				esc_html( self::option_label( $form ) )
			);
		}
		echo '</select>';

		printf(
			'<label for="dsgo-from">%1$s</label> <input type="date" id="dsgo-from" name="dsgo_from" value="%2$s"> <label for="dsgo-to">%3$s</label> <input type="date" id="dsgo-to" name="dsgo_to" value="%4$s">',
			esc_html__( 'From date', 'designsetgo' ),
			esc_attr( $current['from'] ),
			esc_html__( 'To date', 'designsetgo' ),
			esc_attr( $current['to'] )
		);

		wp_nonce_field( Form_Submissions_Export::NONCE_ACTION, Form_Submissions_Export::NONCE_NAME, false );
		echo '<button type="submit" name="dsgo_export" value="1" class="button">' . esc_html__( 'Export CSV', 'designsetgo' ) . '</button>';
	}

	/**
	 * "Contact (b4ae2351) — 4" when the form's page is known, else "b4ae2351 — 4".
	 *
	 * Form IDs alone are opaque, and two pages can hold forms with the same
	 * title, so the ID stays in the label.
	 *
	 * @param object $form Row from forms().
	 * @return string Option label.
	 */
	private static function option_label( $form ): string {
		if ( '' !== $form->page_title ) {
			return sprintf(
				/* translators: 1: page title, 2: form ID, 3: number of submissions. */
				__( '%1$s (%2$s) — %3$d', 'designsetgo' ),
				$form->page_title,
				$form->form_id,
				(int) $form->total
			);
		}
		return sprintf(
			/* translators: 1: form ID, 2: number of submissions. */
			__( '%1$s — %2$d', 'designsetgo' ),
			$form->form_id,
			(int) $form->total
		);
	}

	/**
	 * Title of the page a submission came from, or '' when it can't be found.
	 *
	 * @param int $submission_id Submission post ID.
	 * @return string Page title.
	 */
	private static function source_page_title( int $submission_id ): string {
		$referer = get_post_meta( $submission_id, '_dsg_submission_referer', true );
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid -- Not a VIP site; admin-only, once per form, cached with the list in forms().
		$page_id = is_string( $referer ) && '' !== $referer ? url_to_postid( $referer ) : 0;
		return $page_id ? wp_strip_all_tags( get_the_title( $page_id ) ) : '';
	}

	/**
	 * Form IDs with their submission counts and the title of the page the
	 * latest submission came from. Cached until any post or post meta changes
	 * (core bumps the `posts` last-changed stamp for both).
	 *
	 * @return object[] Rows with form_id, total and page_title.
	 */
	private static function forms(): array {
		global $wpdb;

		// v2: rows gained latest_id and page_title. Rows an older release
		// cached under the unversioned key lack them.
		$key   = 'dsgo_submission_forms:v2:' . wp_cache_get_last_changed( 'posts' );
		$forms = wp_cache_get( $key, 'designsetgo' );
		if ( is_array( $forms ) ) {
			return $forms;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- No API for distinct meta values; cached above.
		$forms = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS form_id, COUNT(*) AS total, MAX(p.ID) AS latest_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s GROUP BY pm.meta_value ORDER BY pm.meta_value",
				'_dsg_form_id',
				Form_Submissions_Export::POST_TYPE
			)
		);
		foreach ( $forms as $form ) {
			$form->page_title = self::source_page_title( (int) $form->latest_id );
		}
		wp_cache_set( $key, $forms, 'designsetgo' );

		return $forms;
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
