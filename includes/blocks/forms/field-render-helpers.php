<?php
/**
 * Shared server-render helpers for DesignSetGo form-field blocks.
 *
 * The form-field blocks render dynamically (save() returns null, markup is
 * produced here at render time). Centralising the wrapper width, label and
 * help-text markup keeps every field's render.php byte-consistent and means
 * the authored (or pattern-substituted / translated) field text is emitted
 * server-side — so a pattern (or the site-designer-api) can substitute or
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
	 * Mirrors the pre-dynamic save() output so field layout is unchanged. The
	 * calc() keeps a half-gap between side-by-side fields.
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

		$calc = 'calc(' . $field_width . '% - var(--dsgo-form-field-spacing, 1.5rem) / 2)';
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
