<?php
/**
 * Server-side mirror of the constraints each form field renders.
 *
 * Every field's render.php turns its attributes into HTML constraint
 * attributes (`minlength`, `maxlength`, `pattern`, `min`, `max`, `step`).
 * The browser enforces those, but only the browser: a devtools edit or a
 * direct POST skips them. This class reads the same attributes from the
 * saved form and re-applies them, using HTML's own semantics so the server
 * never rejects a value the browser accepted.
 *
 * @package DesignSetGo
 * @since   2.8.3
 */

namespace DesignSetGo\Blocks;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts and enforces per-field value rules for form submissions.
 */
class Form_Field_Rules {

	/**
	 * Collect value rules for every field in a form's inner blocks.
	 *
	 * Keep each case in step with that field's render.php: a rule here that the
	 * field does not render would reject submissions the browser allowed.
	 *
	 * @param array $blocks Parsed inner blocks.
	 * @return array<string, array> Rules keyed by field name. Fields without rules are omitted.
	 */
	public static function extract( $blocks ) {
		$rules = array();

		foreach ( (array) $blocks as $block ) {
			$attrs      = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$field_name = isset( $attrs['fieldName'] ) ? sanitize_text_field( $attrs['fieldName'] ) : '';
			$field      = $field_name ? self::rules_for_block( isset( $block['blockName'] ) ? $block['blockName'] : '', $attrs ) : array();

			if ( ! empty( $field ) ) {
				$rules[ $field_name ] = $field;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$rules = array_merge( $rules, self::extract( $block['innerBlocks'] ) );
			}
		}

		return $rules;
	}

	/**
	 * Rules one field block renders, from its attributes.
	 *
	 * @param string $block_name Block name.
	 * @param array  $attrs      Block attributes.
	 * @return array Rules; empty when the field renders none.
	 */
	private static function rules_for_block( $block_name, array $attrs ) {
		$rules = array();

		switch ( $block_name ) {
			case 'designsetgo/form-text-field':
				// render.php emits minlength/maxlength only when positive.
				$rules['minLength'] = isset( $attrs['minLength'] ) && (int) $attrs['minLength'] > 0 ? (int) $attrs['minLength'] : null;
				$rules['maxLength'] = isset( $attrs['maxLength'] ) && (int) $attrs['maxLength'] > 0 ? (int) $attrs['maxLength'] : null;
				$rules['pattern']   = self::text_pattern( $attrs );
				break;

			case 'designsetgo/form-textarea-field':
				$rules['maxLength'] = isset( $attrs['maxLength'] ) && (int) $attrs['maxLength'] > 0 ? (int) $attrs['maxLength'] : null;
				break;

			case 'designsetgo/form-number-field':
				$rules['min'] = isset( $attrs['min'] ) && is_numeric( $attrs['min'] ) ? (float) $attrs['min'] : null;
				$rules['max'] = isset( $attrs['max'] ) && is_numeric( $attrs['max'] ) ? (float) $attrs['max'] : null;
				// render.php forces step 1 unless decimals are allowed.
				$step          = ! empty( $attrs['allowDecimals'] ) && isset( $attrs['step'] ) && is_numeric( $attrs['step'] ) ? (float) $attrs['step'] : 1.0;
				$rules['step'] = $step > 0 ? $step : null;
				// HTML's step base: `min`, else the rendered default value, else 0.
				$rules['stepBase'] = null !== $rules['min']
					? $rules['min']
					: ( isset( $attrs['defaultValue'] ) && is_numeric( $attrs['defaultValue'] ) ? (float) $attrs['defaultValue'] : null );
				break;

			case 'designsetgo/form-date-field':
				$rules['minDate'] = isset( $attrs['minDate'] ) && self::is_date( $attrs['minDate'] ) ? $attrs['minDate'] : null;
				$rules['maxDate'] = isset( $attrs['maxDate'] ) && self::is_date( $attrs['maxDate'] ) ? $attrs['maxDate'] : null;
				break;

			case 'designsetgo/form-time-field':
				$rules['minTime']  = isset( $attrs['minTime'] ) ? self::time_to_seconds( $attrs['minTime'] ) : null;
				$rules['maxTime']  = isset( $attrs['maxTime'] ) ? self::time_to_seconds( $attrs['maxTime'] ) : null;
				$step              = isset( $attrs['step'] ) ? (int) $attrs['step'] : 60;
				$rules['timeStep'] = $step > 0 ? $step : null;
				$rules['stepBase'] = null !== $rules['minTime']
					? $rules['minTime']
					: ( isset( $attrs['defaultValue'] ) ? self::time_to_seconds( $attrs['defaultValue'] ) : null );
				break;
		}

		return array_filter(
			$rules,
			static function ( $rule ) {
				return null !== $rule && '' !== $rule;
			}
		);
	}

	/**
	 * The HTML pattern a text field renders, resolved from its preset.
	 *
	 * Mirrors form-text-field/render.php.
	 *
	 * @param array $attrs Text field attributes.
	 * @return string Pattern, or '' for none.
	 */
	private static function text_pattern( array $attrs ) {
		switch ( isset( $attrs['validation'] ) ? (string) $attrs['validation'] : 'none' ) {
			case 'letters':
				return '[A-Za-z\s]+';
			case 'numbers':
				return '[0-9]+';
			case 'alphanumeric':
				return '[A-Za-z0-9]+';
			case 'custom':
				return isset( $attrs['validationPattern'] ) ? (string) $attrs['validationPattern'] : '';
		}
		return '';
	}

	/**
	 * Check a submitted value against a field's rules.
	 *
	 * Empty values pass: an empty optional field is valid in HTML whatever its
	 * constraints, and required fields are checked separately.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param array $rules Rules from extract().
	 * @return true|WP_Error
	 */
	public static function check( $value, array $rules ) {
		if ( empty( $rules ) || '' === $value || null === $value ) {
			return true;
		}
		if ( ! is_scalar( $value ) ) {
			return self::error();
		}

		// Browsers submit CRLF line breaks but count each as one character.
		$value  = str_replace( "\r\n", "\n", (string) $value );
		$length = self::utf16_length( $value );

		if ( isset( $rules['minLength'] ) && $length < $rules['minLength'] ) {
			return self::error();
		}
		if ( isset( $rules['maxLength'] ) && $length > $rules['maxLength'] ) {
			return self::error();
		}
		if ( isset( $rules['pattern'] ) && ! self::matches_pattern( $value, $rules['pattern'] ) ) {
			return self::error();
		}

		if ( isset( $rules['min'] ) || isset( $rules['max'] ) || isset( $rules['step'] ) ) {
			if ( ! is_numeric( $value ) ) {
				return self::error();
			}
			$number = (float) $value;
			if ( ( isset( $rules['min'] ) && $number < $rules['min'] ) || ( isset( $rules['max'] ) && $number > $rules['max'] ) ) {
				return self::error();
			}
			if ( isset( $rules['step'] ) && ! self::on_step( $number - ( isset( $rules['stepBase'] ) ? $rules['stepBase'] : 0 ), $rules['step'] ) ) {
				return self::error();
			}
		}

		if ( isset( $rules['minDate'] ) || isset( $rules['maxDate'] ) ) {
			// ISO dates compare correctly as strings.
			if ( ! self::is_date( $value )
				|| ( isset( $rules['minDate'] ) && $value < $rules['minDate'] )
				|| ( isset( $rules['maxDate'] ) && $value > $rules['maxDate'] ) ) {
				return self::error();
			}
		}

		if ( isset( $rules['minTime'] ) || isset( $rules['maxTime'] ) || isset( $rules['timeStep'] ) ) {
			$seconds = self::time_to_seconds( $value );
			if ( null === $seconds || ! self::time_in_range( $seconds, $rules ) ) {
				return self::error();
			}
			if ( isset( $rules['timeStep'] ) && ! self::on_step( $seconds - ( isset( $rules['stepBase'] ) ? $rules['stepBase'] : 0 ), $rules['timeStep'] ) ) {
				return self::error();
			}
		}

		return true;
	}

	/**
	 * Whether a value fully matches an HTML `pattern` attribute.
	 *
	 * HTML anchors the pattern to the whole value. A pattern PCRE cannot
	 * compile (JavaScript-only syntax) is ignored, as browsers ignore a pattern
	 * they cannot compile — the server must not be stricter than the page.
	 * The reverse gap remains: browsers compile with the `v` flag, which
	 * rejects a few patterns PCRE accepts (an unescaped `-` or `(` inside a
	 * character class), and such a pattern is enforced here but not there.
	 *
	 * @param string $value   Submitted value.
	 * @param string $pattern HTML pattern attribute.
	 * @return bool
	 */
	private static function matches_pattern( $value, $pattern ) {
		// \x01 as delimiter: it cannot appear in an authored pattern, so the
		// pattern needs no escaping to be embedded.
		$regex = "\x01^(?:" . $pattern . ")$\x01u";
		// An uncompilable author pattern is expected input: swallow the
		// compile warning and read the false return instead.
		set_error_handler( '__return_true' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Scoped to one preg_match().
		$result = preg_match( $regex, $value );
		restore_error_handler();
		return false === $result || 1 === $result;
	}

	/**
	 * Length in UTF-16 code units, which is what `minlength`/`maxlength` count.
	 *
	 * @param string $value UTF-8 string.
	 * @return int
	 */
	private static function utf16_length( $value ) {
		$code_points = preg_match_all( '/./us', $value );
		if ( false === $code_points ) {
			return strlen( $value );
		}
		// Characters outside the Basic Multilingual Plane take two units.
		return $code_points + (int) preg_match_all( '/[\x{10000}-\x{10FFFF}]/u', $value );
	}

	/**
	 * Whether a distance from the step base is a whole number of steps.
	 *
	 * @param float $distance Value minus step base.
	 * @param float $step     Step size.
	 * @return bool
	 */
	private static function on_step( $distance, $step ) {
		$steps = $distance / $step;
		return abs( $steps - round( $steps ) ) < 1e-7;
	}

	/**
	 * Whether a time falls inside min/max, which HTML lets wrap past midnight.
	 *
	 * @param int   $seconds Seconds since midnight.
	 * @param array $rules   Field rules.
	 * @return bool
	 */
	private static function time_in_range( $seconds, array $rules ) {
		$min = isset( $rules['minTime'] ) ? $rules['minTime'] : null;
		$max = isset( $rules['maxTime'] ) ? $rules['maxTime'] : null;
		if ( null !== $min && null !== $max && $min > $max ) {
			return $seconds >= $min || $seconds <= $max;
		}
		return ( null === $min || $seconds >= $min ) && ( null === $max || $seconds <= $max );
	}

	/**
	 * Whether a value is an HTML date string (YYYY-MM-DD).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_date( $value ) {
		return is_string( $value ) && (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
	}

	/**
	 * Parse an HTML time string (HH:MM, HH:MM:SS, HH:MM:SS.sss).
	 *
	 * @param mixed $value Value.
	 * @return int|null Seconds since midnight, or null when not a time.
	 */
	private static function time_to_seconds( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d)(?:\.\d{1,3})?)?$/', $value, $m ) ) {
			return null;
		}
		return (int) $m[1] * 3600 + (int) $m[2] * 60 + ( isset( $m[3] ) ? (int) $m[3] : 0 );
	}

	/**
	 * The error returned for any rule a value breaks.
	 *
	 * @return WP_Error
	 */
	private static function error() {
		return new WP_Error(
			'value_out_of_bounds',
			__( 'Submitted value does not meet this field\'s requirements.', 'designsetgo' )
		);
	}
}
