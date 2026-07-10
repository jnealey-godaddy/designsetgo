<?php
/**
 * Form Date Field - server-side render.
 *
 * Dynamic block: save() returns null, so the field's markup is produced here at
 * render time. The authored (or pattern-substituted / translated) label and
 * help text are emitted server-side, so no static HTML is stored to diff
 * against — block validation can never fail on translated field text.
 *
 * @package DesignSetGo
 * @since 2.5.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused for dynamic blocks).
 * @param WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_render_form_date_field' ) ) {
	/**
	 * Render the Form Date Field block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content.
	 * @param WP_Block $block      Block instance.
	 * @return void
	 */
	function designsetgo_render_form_date_field( $attributes, $content, $block ) {
		$field_name  = isset( $attributes['fieldName'] ) ? (string) $attributes['fieldName'] : '';
		$label       = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
		$help_text   = isset( $attributes['helpText'] ) ? (string) $attributes['helpText'] : '';
		$required    = ! empty( $attributes['required'] );
		$default_val = isset( $attributes['defaultValue'] ) ? (string) $attributes['defaultValue'] : '';
		$min_date    = isset( $attributes['minDate'] ) ? (string) $attributes['minDate'] : '';
		$max_date    = isset( $attributes['maxDate'] ) ? (string) $attributes['maxDate'] : '';
		$field_width = isset( $attributes['fieldWidth'] ) ? (string) $attributes['fieldWidth'] : '100';

		$field_id = 'field-' . $field_name;

		$input = '<input type="date" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__input"';
		if ( $required ) {
			$input .= ' required';
		}
		if ( '' !== $min_date ) {
			$input .= ' min="' . esc_attr( $min_date ) . '"';
		}
		if ( '' !== $max_date ) {
			$input .= ' max="' . esc_attr( $max_date ) . '"';
		}
		if ( '' !== $default_val ) {
			$input .= ' value="' . esc_attr( $default_val ) . '"';
		}
		if ( '' !== $help_text ) {
			$input .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		if ( $required ) {
			$input .= ' aria-required="true"';
		}
		$input .= ' data-field-type="date"/>';

		$inner  = designsetgo_form_field_label_html( $field_id, $label, $required );
		$inner .= $input;
		$inner .= designsetgo_form_field_help_html( $field_id, $help_text );

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'dsgo-form-field dsgo-form-field--date',
				'style' => designsetgo_form_field_width_style( $field_width ),
			)
		);

		echo '<div ' . $wrapper . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

designsetgo_render_form_date_field( $attributes, $content, $block );
