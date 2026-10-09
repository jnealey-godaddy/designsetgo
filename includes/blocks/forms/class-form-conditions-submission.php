<?php
/**
 * Form Builder conditional field logic: submission filtering.
 *
 * Drops hidden fields from a submission so they neither block it nor carry
 * values into storage, email, webhooks or exports.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Conditions_Submission.
 */
class Form_Conditions_Submission {

	/**
	 * Drop hidden fields from a submission and from the required list.
	 *
	 * Runs before validation, so a hidden field neither blocks the submission
	 * nor carries a value into storage, email, webhooks or exports. The phone
	 * field's `{name}_country_code` companion follows its phone field.
	 *
	 * Rules read each value the way it will be stored: `$sanitize` (the submit
	 * loop's per-type sanitizer) is applied first, so markup or %xx octets in a
	 * text source can't make a rule see one answer while another is stored.
	 *
	 * @param array         $definition Server-resolved form definition.
	 * @param array         $fields     Submitted list of { name, value, type }.
	 * @param callable|null $sanitize   Sanitizer `( $value, $type ): mixed`; null reads raw values.
	 * @return array{0: array, 1: string[]} Filtered fields and required field names.
	 */
	public static function filter_submission( array $definition, array $fields, ?callable $sanitize = null ): array {
		$required   = isset( $definition['required_fields'] ) ? (array) $definition['required_fields'] : array();
		$conditions = isset( $definition['conditions'] ) && is_array( $definition['conditions'] ) ? $definition['conditions'] : array();
		if ( empty( $conditions['conditions'] ) || empty( $conditions['fields'] ) ) {
			return array( $fields, $required );
		}

		// Canonicalize first, so the value evaluated is the value the submit
		// loop validates and stores: valid entries only, last one per name wins.
		$canonical = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['name'], $field['value'] ) || ! is_scalar( $field['name'] ) ) {
				continue;
			}
			if ( ! is_string( $field['value'] ) && ! is_int( $field['value'] ) && ! is_float( $field['value'] ) ) {
				continue;
			}
			$name = sanitize_text_field( $field['name'] );
			unset( $canonical[ $name ] );
			$canonical[ $name ] = $field;
		}

		$types  = isset( $definition['field_types'] ) ? (array) $definition['field_types'] : array();
		$values = array();
		foreach ( $canonical as $name => $field ) {
			$type            = isset( $types[ $name ] ) ? $types[ $name ] : 'text';
			$values[ $name ] = null !== $sanitize ? call_user_func( $sanitize, $field['value'], $type ) : $field['value'];
		}
		$fields = array_values( $canonical );

		// A hidden-type field's value is fixed by the form, so rules read the
		// saved value: a client can't blank or change it to flip visibility.
		$constraints = isset( $definition['constraints'] ) ? (array) $definition['constraints'] : array();
		foreach ( $types as $name => $type ) {
			if ( 'hidden' === $type && isset( $constraints[ $name ][0] ) ) {
				$values[ $name ] = (string) $constraints[ $name ][0];
			}
		}

		$hidden = array_flip(
			array_diff( $conditions['fields'], Form_Conditions::visible_fields( $conditions['fields'], $conditions['conditions'], $values ) )
		);
		if ( empty( $hidden ) ) {
			return array( $fields, $required );
		}

		$is_hidden = function ( $name ) use ( $hidden, $types ) {
			if ( isset( $types[ $name ] ) && 'country_code' === $types[ $name ] ) {
				$name = substr( $name, 0, -strlen( '_country_code' ) );
			}
			return isset( $hidden[ $name ] );
		};

		$kept = array();
		foreach ( $fields as $field ) {
			$name = sanitize_text_field( $field['name'] );
			if ( '' === $name || ! $is_hidden( $name ) ) {
				$kept[] = $field;
			}
		}

		return array(
			$kept,
			array_values(
				array_filter(
					$required,
					function ( $name ) use ( $is_hidden ) {
						return ! $is_hidden( $name );
					}
				)
			),
		);
	}
}
