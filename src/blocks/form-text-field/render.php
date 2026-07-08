<?php
/**
 * Form Text Field - server-side render.
 *
 * Dynamic block: save() returns null, so the field's markup is produced here at
 * render time. The authored (or pattern-substituted / translated) label,
 * placeholder and help text are emitted server-side, so no static HTML is stored
 * to diff against — block validation can never fail on translated field text.
 *
 * @package DesignSetGo
 * @since 2.5.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused for dynamic blocks).
 * @param WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_render_form_text_field' ) ) {
	/**
	 * Render the Form Text Field block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content.
	 * @param WP_Block $block      Block instance.
	 * @return void
	 */
	function designsetgo_render_form_text_field( $attributes, $content, $block ) {
		$field_name  = isset( $attributes['fieldName'] ) ? (string) $attributes['fieldName'] : '';
		$label       = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
		$placeholder = isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '';
		$help_text   = isset( $attributes['helpText'] ) ? (string) $attributes['helpText'] : '';
		$required    = ! empty( $attributes['required'] );
		$default_val = isset( $attributes['defaultValue'] ) ? (string) $attributes['defaultValue'] : '';
		$min_length  = isset( $attributes['minLength'] ) ? (int) $attributes['minLength'] : 0;
		$max_length  = isset( $attributes['maxLength'] ) ? (int) $attributes['maxLength'] : 0;
		$validation  = isset( $attributes['validation'] ) ? (string) $attributes['validation'] : 'none';
		$val_pattern = isset( $attributes['validationPattern'] ) ? (string) $attributes['validationPattern'] : '';
		$val_message = isset( $attributes['validationMessage'] ) ? (string) $attributes['validationMessage'] : '';
		$field_width = isset( $attributes['fieldWidth'] ) ? (string) $attributes['fieldWidth'] : '100';

		$field_id = 'field-' . $field_name;

		// Resolve the HTML pattern from the validation preset.
		$pattern = '';
		switch ( $validation ) {
			case 'letters':
				$pattern = '[A-Za-z\\s]+';
				break;
			case 'numbers':
				$pattern = '[0-9]+';
				break;
			case 'alphanumeric':
				$pattern = '[A-Za-z0-9]+';
				break;
			case 'custom':
				$pattern = $val_pattern;
				break;
		}

		$input = '<input type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__input"';
		if ( '' !== $placeholder ) {
			$input .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		}
		if ( $required ) {
			$input .= ' required';
		}
		if ( $min_length > 0 ) {
			$input .= ' minlength="' . $min_length . '"';
		}
		if ( $max_length > 0 ) {
			$input .= ' maxlength="' . $max_length . '"';
		}
		if ( '' !== $pattern ) {
			$input .= ' pattern="' . esc_attr( $pattern ) . '"';
		}
		if ( '' !== $val_message ) {
			$input .= ' title="' . esc_attr( $val_message ) . '"';
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
		$input .= ' data-field-type="text"/>';

		$inner  = designsetgo_form_field_label_html( $field_id, $label, $required );
		$inner .= $input;
		$inner .= designsetgo_form_field_help_html( $field_id, $help_text );

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'dsgo-form-field dsgo-form-field--text',
				'style' => designsetgo_form_field_width_style( $field_width ),
			)
		);

		echo '<div ' . $wrapper . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

designsetgo_render_form_text_field( $attributes, $content, $block );
