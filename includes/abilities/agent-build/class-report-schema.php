<?php
/**
 * JSON-shape contract for a build report's `invalid`/`findings` entries.
 *
 * Pinned by the engine spec (Task 3/17's `{path, block, reason, code?}`
 * invalid-entry shape and Task 9/12's `{rule, severity, path, message,
 * suggestion?}` finding-entry shape) so a report POSTed by the browser
 * round-trips through REST with the exact same field names the engine and
 * CLI already use. Split out of Build_REST purely to keep both files under
 * the plugin's 300-line cap - there is no independent lifecycle here.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.6.0
 */

namespace DesignSetGo\Abilities\Agent_Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Report_Schema class.
 *
 * A plain helper, not an Abstract_Ability - see Build_Store's docblock for
 * why this directory holds non-ability classes.
 */
class Report_Schema {

	/** Max length for short identifier-like fields (path, block, rule, code). */
	const MAX_SHORT_FIELD_LEN = 200;

	/** Max length for free-text fields (reason, message, suggestion). */
	const MAX_LONG_FIELD_LEN = 1000;

	/**
	 * REST arg schema for Build_REST's POST body. `invalid`/`findings` item
	 * shapes are pinned below to match the engine/CLI contract exactly.
	 *
	 * @param array<int, string> $statuses Statuses a report may declare.
	 * @return array<string, mixed>
	 */
	public static function report_args( array $statuses ): array {
		return array(
			'id'       => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => __( 'The post ID the report is for.', 'designsetgo' ),
			),
			'buildId'  => array(
				'required'    => true,
				'type'        => 'string',
				'description' => __( 'The buildId from the GET response for the build this report describes.', 'designsetgo' ),
			),
			'status'   => array(
				'required'    => true,
				'type'        => 'string',
				'enum'        => $statuses,
				'description' => __( 'Outcome of assembling and saving the pending build.', 'designsetgo' ),
			),
			'invalid'  => array(
				'required'          => false,
				'type'              => 'array',
				'default'           => array(),
				'items'             => self::invalid_item_schema(),
				// Explicit validate_callback: WP only auto-validates schema
				// (enum/items/required/additionalProperties) via the default
				// rest_parse_request_arg() sanitize_callback, but declaring
				// our own sanitize_callback below replaces that default, so
				// schema validation has to be requested back explicitly.
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => array( self::class, 'sanitize_invalid_list' ),
				'description'       => __( 'Blocks the browser could not place or serialize: {path, block, reason, code?}.', 'designsetgo' ),
			),
			'findings' => array(
				'required'          => false,
				'type'              => 'array',
				'default'           => array(),
				'items'             => self::findings_item_schema(),
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => array( self::class, 'sanitize_findings_list' ),
				'description'       => __( 'Non-fatal issues surfaced while assembling the build: {rule, severity, path, message, suggestion?}.', 'designsetgo' ),
			),
		);
	}

	/**
	 * REST `items` schema for one `invalid` entry: `{path, block, reason, code?}`.
	 *
	 * `additionalProperties: false` makes an entry with an unrecognized key
	 * fail schema validation (`rest_validate_value_from_schema()`, run via
	 * `rest_validate_request_arg()` before sanitization) - WordPress rejects
	 * the whole request with 400 rest_invalid_param rather than silently
	 * stripping the unknown key.
	 *
	 * @return array<string, mixed>
	 */
	public static function invalid_item_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'path'   => array( 'type' => 'string' ),
				'block'  => array( 'type' => 'string' ),
				'reason' => array( 'type' => 'string' ),
				'code'   => array( 'type' => 'string' ),
			),
			'required'             => array( 'path', 'block', 'reason' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * REST `items` schema for one `findings` entry:
	 * `{rule, severity: 'error'|'warning', path, message, suggestion?}`.
	 *
	 * @return array<string, mixed>
	 */
	public static function findings_item_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'rule'       => array( 'type' => 'string' ),
				'severity'   => array(
					'type' => 'string',
					'enum' => array( 'error', 'warning' ),
				),
				'path'       => array( 'type' => 'string' ),
				'message'    => array( 'type' => 'string' ),
				'suggestion' => array( 'type' => 'string' ),
			),
			'required'             => array( 'rule', 'severity', 'path', 'message' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Sanitize an `invalid` list. Only ever called on a list that already
	 * passed invalid_item_schema() validation, so this is defense-in-depth
	 * field cleanup (length caps, sanitize_text_field()), not shape policing.
	 *
	 * @param mixed $value Raw (schema-valid) value from the request.
	 * @return array<int, array{path: string, block: string, reason: string, code?: string}>
	 */
	public static function sanitize_invalid_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values(
			array_map(
				static function ( $entry ) {
					$entry = is_array( $entry ) ? $entry : array();
					$out   = array(
						'path'   => self::sanitize_short( $entry['path'] ?? '' ),
						'block'  => self::sanitize_short( $entry['block'] ?? '' ),
						'reason' => self::sanitize_long( $entry['reason'] ?? '' ),
					);
					if ( isset( $entry['code'] ) ) {
						$out['code'] = self::sanitize_short( $entry['code'] );
					}
					return $out;
				},
				$value
			)
		);
	}

	/**
	 * Sanitize a `findings` list. See sanitize_invalid_list() for why this
	 * is cleanup rather than shape policing.
	 *
	 * @param mixed $value Raw (schema-valid) value from the request.
	 * @return array<int, array{rule: string, severity: string, path: string, message: string, suggestion?: string}>
	 */
	public static function sanitize_findings_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values(
			array_map(
				static function ( $entry ) {
					$entry    = is_array( $entry ) ? $entry : array();
					$severity = $entry['severity'] ?? '';
					$out      = array(
						'rule'     => self::sanitize_short( $entry['rule'] ?? '' ),
						'severity' => in_array( $severity, array( 'error', 'warning' ), true ) ? $severity : 'error',
						'path'     => self::sanitize_short( $entry['path'] ?? '' ),
						'message'  => self::sanitize_long( $entry['message'] ?? '' ),
					);
					if ( isset( $entry['suggestion'] ) ) {
						$out['suggestion'] = self::sanitize_long( $entry['suggestion'] );
					}
					return $out;
				},
				$value
			)
		);
	}

	/**
	 * Sanitize + cap a short identifier-like field.
	 *
	 * @param mixed $value Raw field value.
	 * @return string
	 */
	private static function sanitize_short( $value ): string {
		return mb_substr( sanitize_text_field( (string) $value ), 0, self::MAX_SHORT_FIELD_LEN );
	}

	/**
	 * Sanitize + cap a free-text field.
	 *
	 * @param mixed $value Raw field value.
	 * @return string
	 */
	private static function sanitize_long( $value ): string {
		return mb_substr( sanitize_text_field( (string) $value ), 0, self::MAX_LONG_FIELD_LEN );
	}
}
