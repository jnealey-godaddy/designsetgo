<?php
/**
 * Form Textarea Field - server-side render.
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

if ( ! function_exists( 'designsetgo_render_form_textarea_field' ) ) {
	/**
	 * Render the Form Textarea Field block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content.
	 * @param WP_Block $block      Block instance.
	 * @return void
	 */
	function designsetgo_render_form_textarea_field( $attributes, $content, $block ) {
		$field_name  = isset( $attributes['fieldName'] ) ? (string) $attributes['fieldName'] : '';
		$label       = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
		$placeholder = isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '';
		$help_text   = isset( $attributes['helpText'] ) ? (string) $attributes['helpText'] : '';
		$required    = ! empty( $attributes['required'] );
		$default_val = isset( $attributes['defaultValue'] ) ? (string) $attributes['defaultValue'] : '';
		$rows        = isset( $attributes['rows'] ) ? (int) $attributes['rows'] : 4;
		$max_length  = isset( $attributes['maxLength'] ) ? (int) $attributes['maxLength'] : 0;
		$field_width = isset( $attributes['fieldWidth'] ) ? (string) $attributes['fieldWidth'] : '100';

		$field_id = 'field-' . $field_name;

		$textarea = '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__textarea"';
		if ( '' !== $placeholder ) {
			$textarea .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		}
		if ( $required ) {
			$textarea .= ' required';
		}
		$textarea .= ' rows="' . $rows . '"';
		if ( $max_length > 0 ) {
			$textarea .= ' maxlength="' . $max_length . '"';
		}
		if ( '' !== $help_text ) {
			$textarea .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		if ( $required ) {
			$textarea .= ' aria-required="true"';
		}
		$textarea .= ' data-field-type="textarea">' . esc_html( $default_val ) . '</textarea>';

		$inner  = designsetgo_form_field_label_html( $field_id, $label, $required );
		$inner .= $textarea;
		$inner .= designsetgo_form_field_help_html( $field_id, $help_text );

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'dsgo-form-field dsgo-form-field--textarea',
				'style' => designsetgo_form_field_width_style( $field_width ),
			)
		);

		echo '<div ' . $wrapper . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

designsetgo_render_form_textarea_field( $attributes, $content, $block );
