<?php
/**
 * Form Checkbox Field - server-side render.
 *
 * Dynamic block: save() returns null, so the field's markup is produced here at
 * render time. The authored (or pattern-substituted / translated) label,
 * which may contain inline HTML from RichText, and help text are emitted
 * server-side, so no static HTML is stored to diff against — block validation
 * can never fail on translated field text.
 *
 * @package DesignSetGo
 * @since 2.5.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused for dynamic blocks).
 * @param WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_render_form_checkbox_field' ) ) {
	/**
	 * Render the Form Checkbox Field block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content.
	 * @param WP_Block $block      Block instance.
	 * @return void
	 */
	function designsetgo_render_form_checkbox_field( $attributes, $content, $block ) {
		$field_name         = isset( $attributes['fieldName'] ) ? (string) $attributes['fieldName'] : '';
		$label              = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
		$help_text          = isset( $attributes['helpText'] ) ? (string) $attributes['helpText'] : '';
		$required           = ! empty( $attributes['required'] );
		$checked_by_default = ! empty( $attributes['checkedByDefault'] );
		$value              = isset( $attributes['value'] ) ? (string) $attributes['value'] : '1';

		$field_id = 'field-' . $field_name;

		$input = '<input type="checkbox" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__checkbox-input" value="' . esc_attr( $value ) . '"';
		if ( $checked_by_default ) {
			$input .= ' checked';
		}
		if ( $required ) {
			$input .= ' required';
		}
		if ( '' !== $help_text ) {
			$input .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		if ( $required ) {
			$input .= ' aria-required="true"';
		}
		$input .= ' data-field-type="checkbox"/>';

		$label_html = '<label for="' . esc_attr( $field_id ) . '" class="dsgo-form-field__checkbox-label"><span>' . wp_kses_post( $label ) . '</span>';
		if ( $required ) {
			$label_html .= '<span class="dsgo-form-field__required" aria-label="required"> *</span>';
		}
		$label_html .= '</label>';

		$inner  = '<div class="dsgo-form-field__checkbox-wrapper">' . $input . $label_html . '</div>';
		$inner .= designsetgo_form_field_help_html( $field_id, $help_text );

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'dsgo-form-field dsgo-form-field--checkbox',
			)
		);

		echo '<div ' . $wrapper . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

designsetgo_render_form_checkbox_field( $attributes, $content, $block );
