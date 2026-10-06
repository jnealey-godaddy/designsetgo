<?php
/**
 * Form submission list filters and CSV export.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Submissions_Export.
 */
class Form_Submissions_Export {

	const POST_TYPE    = 'dsgo_form_submission';
	const NONCE_ACTION = 'designsetgo_export_submissions';
	const NONCE_NAME   = '_dsgo_export_nonce';
	const BATCH        = 200;

	/**
	 * Characters a spreadsheet may treat as the start of a formula.
	 */
	const FORMULA_PREFIXES = array( '=', '+', '-', '@', "\t", "\r" );

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'load-edit.php', array( $this, 'maybe_export' ) );
	}

	/**
	 * Read and sanitise filter values from a request array.
	 *
	 * @param array $source Usually $_GET.
	 * @return array{form_id: string, from: string, to: string}
	 */
	public static function request_args( array $source ): array {
		return array(
			'form_id' => isset( $source['dsgo_form'] ) && is_string( $source['dsgo_form'] ) ? sanitize_text_field( wp_unslash( $source['dsgo_form'] ) ) : '',
			'from'    => self::valid_date( isset( $source['dsgo_from'] ) ? $source['dsgo_from'] : '' ),
			'to'      => self::valid_date( isset( $source['dsgo_to'] ) ? $source['dsgo_to'] : '' ),
		);
	}

	/**
	 * WP_Query arguments for the filters.
	 *
	 * Dates are inclusive whole days in the site's timezone (post_date is local).
	 *
	 * @param array $args Filter args from request_args().
	 * @return array Query args.
	 */
	public static function query_args( array $args ): array {
		$query = array();

		if ( ! empty( $args['form_id'] ) ) {
			$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only filter.
				array(
					'key'   => '_dsg_form_id',
					'value' => $args['form_id'],
				),
			);
		}

		$date = array( 'inclusive' => true );
		if ( ! empty( $args['from'] ) ) {
			$date['after'] = self::date_parts( $args['from'] );
		}
		if ( ! empty( $args['to'] ) ) {
			// Array form so WP_Date_Query fills the time with 23:59:59 for an inclusive "before".
			$date['before'] = self::date_parts( $args['to'] );
		}
		if ( count( $date ) > 1 ) {
			$query['date_query'] = array( $date );
		}

		return $query;
	}

	/**
	 * Neutralise spreadsheet formula injection.
	 *
	 * @param mixed $value Cell value.
	 * @return string Safe cell.
	 */
	public static function escape_cell( $value ): string {
		$value = (string) $value;

		return ( '' !== $value && in_array( $value[0], self::FORMULA_PREFIXES, true ) ) ? "'" . $value : $value;
	}

	/**
	 * Write the CSV for the given filters to a stream.
	 *
	 * Two passes over the matching IDs, in batches: the first collects every
	 * field name (so the header is complete), the second writes rows. Memory
	 * stays flat however many submissions there are.
	 *
	 * @param resource $handle Writable stream.
	 * @param array    $args   Filter args from request_args().
	 */
	public function write_csv( $handle, array $args ): void {
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite -- Streaming to the response.

		$names  = array();
		$labels = array();
		$this->each_submission(
			$args,
			function ( $id ) use ( &$names, &$labels ) {
				foreach ( $this->stored_fields( $id ) as $name => $data ) {
					$names[ $name ] = true;
					if ( is_array( $data ) && isset( $data['label'] ) && '' !== $data['label'] ) {
						$labels[ $name ] = (string) $data['label'];
					}
				}
			}
		);

		$columns = $this->columns( array_keys( $names ), $labels, $args );
		$this->put_row( $handle, array_values( $columns ) );

		$keys = array_keys( $columns );
		$this->each_submission(
			$args,
			function ( $id ) use ( $handle, $keys ) {
				$fields = $this->stored_fields( $id );
				$row    = array();
				foreach ( $keys as $key ) {
					$row[] = (string) apply_filters( 'designsetgo_form_export_cell', $this->cell( $key, $id, $fields ), $key, $id );
				}
				$this->put_row( $handle, $row );
			}
		);
	}

	/**
	 * Stream the CSV download when the Export button was used.
	 */
	public function maybe_export(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Presence checks only; nonce verified below.
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( self::POST_TYPE !== $post_type || empty( $_GET['dsgo_export'] ) ) {
			return;
		}
		// phpcs:enable

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export submissions.', 'designsetgo' ), '', array( 'response' => 403 ) );
		}

		$args     = self::request_args( $_GET );
		$filename = 'submissions-' . ( '' !== $args['form_id'] ? sanitize_file_name( $args['form_id'] ) : 'all' ) . '-' . wp_date( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming to the response.
		$this->write_csv( $out, $args );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Column map: key => header. Fields are keyed "field:{name}".
	 *
	 * @param string[] $names  Field names in first-seen order.
	 * @param array    $labels Latest label per field.
	 * @param array    $args   Filter args.
	 * @return array<string, string> Columns.
	 */
	private function columns( array $names, array $labels, array $args ): array {
		$counts = array_count_values( array_values( $labels ) );

		$columns = array(
			'id'         => __( 'Submission ID', 'designsetgo' ),
			'date'       => __( 'Date', 'designsetgo' ),
			'form_id'    => __( 'Form ID', 'designsetgo' ),
			'source_url' => __( 'Source URL', 'designsetgo' ),
		);
		foreach ( $names as $name ) {
			$label = isset( $labels[ $name ] ) ? $labels[ $name ] : (string) $name;
			if ( isset( $labels[ $name ] ) && $counts[ $label ] > 1 ) {
				$label = sprintf( '%s (%s)', $label, $name );
			}
			$columns[ 'field:' . $name ] = $label;
		}
		$columns['email_status']   = __( 'Email status', 'designsetgo' );
		$columns['webhook_status'] = __( 'Webhook status', 'designsetgo' );

		return (array) apply_filters( 'designsetgo_form_export_columns', $columns, $args );
	}

	/**
	 * One cell's value.
	 *
	 * @param string $key    Column key.
	 * @param int    $id     Submission ID.
	 * @param array  $fields Stored fields.
	 * @return string Value.
	 */
	private function cell( string $key, int $id, array $fields ): string {
		if ( 0 === strpos( $key, 'field:' ) ) {
			$name = substr( $key, 6 );
			return isset( $fields[ $name ]['value'] ) ? Form_Submissions::format_field_value( $fields[ $name ]['value'] ) : '';
		}

		switch ( $key ) {
			case 'id':
				return (string) $id;
			case 'date':
				return (string) get_post_field( 'post_date', $id );
			case 'form_id':
				return (string) get_post_meta( $id, '_dsg_form_id', true );
			case 'source_url':
				return (string) get_post_meta( $id, '_dsg_submission_referer', true );
			case 'email_status':
				$sent = get_post_meta( $id, '_dsg_email_sent', true );
				if ( '' === $sent ) {
					return '';
				}
				return 'yes' === $sent ? __( 'Sent', 'designsetgo' ) : __( 'Not sent', 'designsetgo' );
			case 'webhook_status':
				return Form_Webhooks::status_label( $id );
			case 'ip':
				return (string) get_post_meta( $id, '_dsg_submission_ip', true );
			case 'user_agent':
				return (string) get_post_meta( $id, '_dsg_submission_user_agent', true );
			default:
				return '';
		}
	}

	/**
	 * Call $callback for each matching submission ID, oldest first, in batches.
	 *
	 * @param array    $args     Filter args.
	 * @param callable $callback Receives an int ID.
	 */
	private function each_submission( array $args, callable $callback ): void {
		$paged = 1;
		$found = 0;
		do {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- Admin-only export; bounded batches.
			$ids = get_posts(
				array_merge(
					self::query_args( $args ),
					array(
						'post_type'      => self::POST_TYPE,
						'post_status'    => 'any',
						'fields'         => 'ids',
						'posts_per_page' => self::BATCH,
						'paged'          => $paged,
						'orderby'        => array(
							'date' => 'ASC',
							'ID'   => 'ASC',
						),
						'no_found_rows'  => true,
					)
				)
			);

			update_meta_cache( 'post', $ids );
			_prime_post_caches( $ids, false, false ); // One query for the post rows instead of one per cell lookup.
			foreach ( $ids as $id ) {
				$callback( (int) $id );
				wp_cache_delete( (int) $id, 'post_meta' );
				wp_cache_delete( (int) $id, 'posts' );
			}
			++$paged;
			$found = count( $ids );
		} while ( self::BATCH === $found );
	}

	/**
	 * Stored field map for a submission.
	 *
	 * @param int $id Submission ID.
	 * @return array Fields.
	 */
	private function stored_fields( int $id ): array {
		$fields = get_post_meta( $id, '_dsg_form_fields', true );
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Write one escaped row.
	 *
	 * @param resource $handle Stream.
	 * @param array    $cells  Cells.
	 */
	private function put_row( $handle, array $cells ): void {
		fputcsv( $handle, array_map( array( __CLASS__, 'escape_cell' ), $cells ), ',', '"', '' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv -- Streaming to the response.
	}

	/**
	 * A Y-m-d string, or '' when invalid.
	 *
	 * @param mixed $value Raw value.
	 * @return string Date.
	 */
	private static function valid_date( $value ): string {
		$value = is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';
		$date  = \DateTime::createFromFormat( '!Y-m-d', $value );

		return ( $date && $date->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	/**
	 * Split Y-m-d into WP_Date_Query parts.
	 *
	 * @param string $ymd Date.
	 * @return array{year: int, month: int, day: int}
	 */
	private static function date_parts( string $ymd ): array {
		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $ymd ) );

		return array(
			'year'  => $year,
			'month' => $month,
			'day'   => $day,
		);
	}
}
