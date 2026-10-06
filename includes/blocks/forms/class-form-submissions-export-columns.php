<?php
/**
 * Column map and cell values for the form submissions CSV export.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Submissions_Export_Columns.
 */
class Form_Submissions_Export_Columns {

	/**
	 * Column map: key => header. Fields are keyed "field:{name}".
	 *
	 * A field header that appears more than once in the row (two fields with
	 * the same label, a label matching a built-in column, an unlabeled field
	 * named like another label) gets " (field_name)" appended.
	 *
	 * @param string[] $names  Field names in first-seen order.
	 * @param array    $labels Latest label per field.
	 * @param array    $args   Filter args.
	 * @return array<string, string> Columns.
	 */
	public static function build( array $names, array $labels, array $args ): array {
		$leading  = array(
			'id'         => __( 'Submission ID', 'designsetgo' ),
			'date'       => __( 'Date', 'designsetgo' ),
			'form_id'    => __( 'Form ID', 'designsetgo' ),
			'source_url' => __( 'Source URL', 'designsetgo' ),
		);
		$trailing = array(
			'email_status'   => __( 'Email status', 'designsetgo' ),
			'webhook_status' => __( 'Webhook status', 'designsetgo' ),
		);

		$headers = array();
		foreach ( $names as $name ) {
			$headers[ $name ] = isset( $labels[ $name ] ) ? $labels[ $name ] : (string) $name;
		}
		$counts = array_count_values( array_merge( array_values( $leading ), array_values( $trailing ), array_map( 'strval', $headers ) ) );

		$fields = array();
		foreach ( $headers as $name => $header ) {
			$header                    = (string) $header;
			$fields[ 'field:' . $name ] = $counts[ $header ] > 1 ? sprintf( '%s (%s)', $header, $name ) : $header;
		}

		return (array) apply_filters( 'designsetgo_form_export_columns', array_merge( $leading, $fields, $trailing ), $args );
	}

	/**
	 * One cell's final value, after the `designsetgo_form_export_cell` filter.
	 *
	 * @param string $key    Column key.
	 * @param int    $id     Submission ID.
	 * @param array  $fields Stored fields.
	 * @return string Value.
	 */
	public static function value( string $key, int $id, array $fields ): string {
		return (string) apply_filters( 'designsetgo_form_export_cell', self::cell( $key, $id, $fields ), $key, $id );
	}

	/**
	 * One cell's raw value.
	 *
	 * @param string $key    Column key.
	 * @param int    $id     Submission ID.
	 * @param array  $fields Stored fields.
	 * @return string Value.
	 */
	private static function cell( string $key, int $id, array $fields ): string {
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
				return Form_Webhook_Status::label( $id );
			case 'ip':
				return (string) get_post_meta( $id, '_dsg_submission_ip', true );
			case 'user_agent':
				return (string) get_post_meta( $id, '_dsg_submission_user_agent', true );
			default:
				return '';
		}
	}
}
