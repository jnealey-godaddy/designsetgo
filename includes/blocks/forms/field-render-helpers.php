<?php
/**
 * Shared server-render helpers for DesignSetGo form-field blocks.
 *
 * The form-field blocks render dynamically (save() returns null, markup is
 * produced here at render time). Centralising the wrapper width, label and
 * help-text markup keeps every field's render.php byte-consistent and means
 * the authored (or pattern-substituted / translated) field text is emitted
 * server-side — so a pattern (or the page generator) can substitute or
 * translate field text without ever tripping block validation.
 *
 * (Field labels are not translated here: block.json supplies each label's
 * default, which WordPress back-fills before render, so an authored value is
 * always present. Translation happens at authoring/pattern time, not render.)
 *
 * @package DesignSetGo
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'designsetgo_form_field_width_style' ) ) {
	/**
	 * Build the flex-basis / max-width inline style for a field wrapper.
	 *
	 * The fields container is a wrapping flex row with
	 * `gap: var(--dsgo-form-field-spacing)`. For N equal columns of P% each,
	 * the row holds N-1 gaps, so every field must give up gap * (N-1)/N, which
	 * is gap * (100 - P) / 100. The old "half a gap" only fit two columns: three
	 * 33% fields overflowed and the third wrapped. 33 and 66 map to exact
	 * thirds so three (or 66 + 33) fields fill the row.
	 *
	 * Keep in sync with getFormFieldWidth() in
	 * src/blocks/form-builder/utils/field-width.js (editor preview); both are
	 * checked against tests/fixtures/form-field-width-cases.json.
	 *
	 * @param string|int $field_width Width percentage (e.g. '100', '50').
	 * @return string CSS declarations (no trailing semicolon).
	 */
	function designsetgo_form_field_width_style( $field_width ) {
		// Only digits are meaningful; guards against unexpected input.
		$field_width = preg_replace( '/[^0-9]/', '', (string) $field_width );
		if ( '' === $field_width ) {
			$field_width = '100';
		}

		if ( '100' === $field_width ) {
			return 'flex-basis:100%;max-width:100%';
		}

		$percent_map = array(
			'33' => '33.3333',
			'66' => '66.6667',
		);
		$percent     = isset( $percent_map[ $field_width ] ) ? $percent_map[ $field_width ] : $field_width;

		// Share of one gap each field gives up, rounded to 4 decimals with
		// trailing zeros trimmed (50 -> 0.5, 33.3333 -> 0.6667).
		$gap_factor = rtrim( rtrim( number_format( ( 100 - (float) $percent ) / 100, 4, '.', '' ), '0' ), '.' );

		$calc = 'calc(' . $percent . '% - var(--dsgo-form-field-spacing, 1.5rem) * ' . $gap_factor . ')';
		return 'flex-basis:' . $calc . ';max-width:' . $calc;
	}
}

if ( ! function_exists( 'designsetgo_form_field_label_html' ) ) {
	/**
	 * Build a field <label> with the optional required marker.
	 *
	 * Reproduces the save() label markup exactly (class + aria-label), so the
	 * frontend and editor render identically.
	 *
	 * @param string $field_id  Field id the label points at.
	 * @param string $label     Label text.
	 * @param bool   $required  Whether to append the required marker.
	 * @param bool   $allow_html Whether the label may contain inline HTML (RichText).
	 * @return string Label HTML.
	 */
	function designsetgo_form_field_label_html( $field_id, $label, $required, $allow_html = false ) {
		$label_out = $allow_html ? wp_kses_post( $label ) : esc_html( $label );

		$html = '<label for="' . esc_attr( $field_id ) . '" class="dsgo-form-field__label">' . $label_out;
		if ( $required ) {
			$html .= '<span class="dsgo-form-field__required" aria-label="required">*</span>';
		}
		$html .= '</label>';

		return $html;
	}
}

if ( ! function_exists( 'designsetgo_form_field_help_html' ) ) {
	/**
	 * Build the help-text <p> for a field, or an empty string when unused.
	 *
	 * @param string $field_id  Field id (the help id is "{$field_id}-help").
	 * @param string $help_text Help text.
	 * @return string Help HTML or ''.
	 */
	function designsetgo_form_field_help_html( $field_id, $help_text ) {
		if ( '' === (string) $help_text ) {
			return '';
		}

		return '<p id="' . esc_attr( $field_id ) . '-help" class="dsgo-form-field__help">' . esc_html( $help_text ) . '</p>';
	}
}
