<?php
/**
 * Form Builder conditional field logic.
 *
 * Decides which form fields are visible for a set of answers. Mirrors
 * src/blocks/form-builder/conditions.js exactly; both are tested against
 * tests/fixtures/form-conditions-cases.json, so change them together.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Conditions.
 */
class Form_Conditions {

	/**
	 * Supported rule operators.
	 */
	const OPS = array( 'is', 'is_not', 'contains', 'empty', 'not_empty', 'gt', 'lt' );

	/**
	 * Field blocks that can carry and be the source of conditions.
	 */
	const FIELD_BLOCKS = array(
		'designsetgo/form-text-field',
		'designsetgo/form-email-field',
		'designsetgo/form-textarea-field',
		'designsetgo/form-number-field',
		'designsetgo/form-phone-field',
		'designsetgo/form-url-field',
		'designsetgo/form-date-field',
		'designsetgo/form-time-field',
		'designsetgo/form-select-field',
		'designsetgo/form-checkbox-field',
		'designsetgo/form-hidden-field',
	);

	/**
	 * Characters trimmed from values (matches the JS evaluator).
	 */
	const TRIM_CHARS = " \t\n\r\f\v";

	const NUMERIC_PATTERN = '/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/';
	const DATE_PATTERN    = '/^\d{4}-\d{2}-\d{2}$/';
	const TIME_PATTERN    = '/^\d{2}:\d{2}(:\d{2})?$/';

	/**
	 * Normalize a raw dsgoConditions value.
	 *
	 * @param mixed $raw Attribute value.
	 * @return array{operator: string, rules: array<int, array{field: string, op: string, value: string}>}|null
	 */
	public static function normalize_rules( $raw ): ?array {
		if ( ! is_array( $raw ) || ! isset( $raw['rules'] ) || ! is_array( $raw['rules'] ) ) {
			return null;
		}

		$rules = array();
		foreach ( $raw['rules'] as $rule ) {
			if ( ! is_array( $rule ) || ! isset( $rule['field'], $rule['op'] ) || ! is_string( $rule['field'] ) || '' === $rule['field'] ) {
				continue;
			}
			if ( ! in_array( $rule['op'], self::OPS, true ) ) {
				continue;
			}
			$rules[] = array(
				'field' => sanitize_text_field( $rule['field'] ),
				'op'    => $rule['op'],
				'value' => self::to_text( isset( $rule['value'] ) ? $rule['value'] : '' ),
			);
		}

		if ( empty( $rules ) ) {
			return null;
		}

		$operator = isset( $raw['operator'] ) && is_string( $raw['operator'] ) && 'OR' === strtoupper( $raw['operator'] ) ? 'OR' : 'AND';

		return array(
			'operator' => $operator,
			'rules'    => $rules,
		);
	}

	/**
	 * Evaluate one normalized rule against a source value.
	 *
	 * @param array $rule   Normalized rule.
	 * @param mixed $actual Source field value.
	 * @return bool
	 */
	public static function evaluate_rule( array $rule, $actual ): bool {
		$a = self::to_text( $actual );
		$e = self::to_text( isset( $rule['value'] ) ? $rule['value'] : '' );

		switch ( isset( $rule['op'] ) ? $rule['op'] : '' ) {
			case 'is':
				return self::lower( $a ) === self::lower( $e );
			case 'is_not':
				return self::lower( $a ) !== self::lower( $e );
			case 'contains':
				return '' !== $e && false !== mb_strpos( self::lower( $a ), self::lower( $e ), 0, 'UTF-8' );
			case 'empty':
				return '' === $a;
			case 'not_empty':
				return '' !== $a;
			case 'gt':
			case 'lt':
				$diff = self::compare( $a, $e );
				if ( null === $diff ) {
					return false;
				}
				return 'gt' === $rule['op'] ? $diff > 0 : $diff < 0;
			default:
				return false;
		}
	}

	/**
	 * Work out which fields are visible.
	 *
	 * @param string[] $field_names Field names in document order.
	 * @param array    $conditions  Raw dsgoConditions keyed by field name.
	 * @param array    $values      Current values keyed by field name.
	 * @return string[] Visible field names, document order.
	 */
	public static function visible_fields( array $field_names, array $conditions, array $values ): array {
		$known   = array_flip( $field_names );
		$visible = array();
		$active  = array();

		foreach ( $field_names as $name ) {
			$visible[ $name ] = true;
			$normalized       = self::normalize_rules( isset( $conditions[ $name ] ) ? $conditions[ $name ] : null );
			if ( null === $normalized ) {
				continue;
			}
			$rules = array_values(
				array_filter(
					$normalized['rules'],
					function ( $rule ) use ( $name, $known ) {
						return $rule['field'] !== $name && isset( $known[ $rule['field'] ] );
					}
				)
			);
			if ( ! empty( $rules ) ) {
				$active[ $name ] = array(
					'operator' => $normalized['operator'],
					'rules'    => $rules,
				);
			}
		}

		$passes = count( $field_names );
		for ( $pass = 0; $pass < $passes; $pass++ ) {
			$changed = false;
			foreach ( $field_names as $name ) {
				if ( ! isset( $active[ $name ] ) ) {
					continue;
				}
				$results = array();
				foreach ( $active[ $name ]['rules'] as $rule ) {
					$source    = $rule['field'];
					$actual    = ( $visible[ $source ] && isset( $values[ $source ] ) ) ? $values[ $source ] : '';
					$results[] = self::evaluate_rule( $rule, $actual );
				}
				$next = 'OR' === $active[ $name ]['operator'] ? in_array( true, $results, true ) : ! in_array( false, $results, true );
				if ( $visible[ $name ] !== $next ) {
					$visible[ $name ] = $next;
					$changed          = true;
				}
			}
			if ( ! $changed ) {
				break;
			}
		}

		return array_values(
			array_filter(
				$field_names,
				function ( $name ) use ( $visible ) {
					return $visible[ $name ];
				}
			)
		);
	}

	/**
	 * Read field names (document order) and their conditions from a form's inner blocks.
	 *
	 * @param array $blocks Parsed inner blocks.
	 * @return array{fields: string[], conditions: array<string, array>}
	 */
	public static function extract( array $blocks ): array {
		$result = array(
			'fields'     => array(),
			'conditions' => array(),
		);
		self::collect( $blocks, $result );
		return $result;
	}

	/**
	 * Recursive walker for extract().
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $result Accumulator, by reference.
	 */
	private static function collect( array $blocks, array &$result ): void {
		foreach ( $blocks as $block ) {
			$name  = isset( $block['blockName'] ) ? $block['blockName'] : '';
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$field = isset( $attrs['fieldName'] ) ? sanitize_text_field( (string) $attrs['fieldName'] ) : '';

			if ( '' !== $field && in_array( $name, self::FIELD_BLOCKS, true ) ) {
				$result['fields'][] = $field;
				$rules              = self::normalize_rules( isset( $attrs['dsgoConditions'] ) ? $attrs['dsgoConditions'] : null );
				if ( null !== $rules ) {
					$result['conditions'][ $field ] = $rules;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::collect( $block['innerBlocks'], $result );
			}
		}
	}

	/**
	 * Scalar → trimmed string; anything else → ''.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function to_text( $value ): string {
		if ( is_string( $value ) ) {
			return trim( $value, self::TRIM_CHARS );
		}
		if ( is_int( $value ) || ( is_float( $value ) && is_finite( $value ) ) ) {
			return (string) $value;
		}
		return '';
	}

	/**
	 * Lower-case for case-insensitive comparison.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( string $text ): string {
		return mb_strtolower( $text, 'UTF-8' );
	}

	/**
	 * Compare for gt/lt.
	 *
	 * @param string $a Actual.
	 * @param string $e Expected.
	 * @return float|int|null Negative/zero/positive, or null when not comparable.
	 */
	private static function compare( string $a, string $e ) {
		if ( preg_match( self::NUMERIC_PATTERN, $a ) && preg_match( self::NUMERIC_PATTERN, $e ) ) {
			return (float) $a - (float) $e;
		}
		$both_dates = preg_match( self::DATE_PATTERN, $a ) && preg_match( self::DATE_PATTERN, $e );
		$both_times = preg_match( self::TIME_PATTERN, $a ) && preg_match( self::TIME_PATTERN, $e );
		if ( ! $both_dates && ! $both_times ) {
			return null;
		}
		if ( $both_times ) {
			$a = 5 === strlen( $a ) ? $a . ':00' : $a;
			$e = 5 === strlen( $e ) ? $e . ':00' : $e;
		}
		return strcmp( $a, $e );
	}
}
