<?php
/**
 * Form Time Field - server-side render.
 *
 * Dynamic block: save() returns null, so the field's markup is produced here at
 * render time. The authored (or pattern-substituted / translated) label and help
 * text are emitted server-side, so no static HTML is stored to diff against —
 * block validation can never fail on translated field text.
 *
 * @package DesignSetGo
 * @since 2.5.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused for dynamic blocks).
 * @param WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_render_form_time_field' ) ) {
	/**
	 * Render the Form Time Field block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content.
	 * @param WP_Block $block      Block instance.
	 * @return void
	 */
	function designsetgo_render_form_time_field( $attributes, $content, $block ) {
		$field_name  = isset( $attributes['fieldName'] ) ? (string) $attributes['fieldName'] : '';
		$label       = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
		$help_text   = isset( $attributes['helpText'] ) ? (string) $attributes['helpText'] : '';
		$required    = ! empty( $attributes['required'] );
		$default_val = isset( $attributes['defaultValue'] ) ? (string) $attributes['defaultValue'] : '';
		$min_time    = isset( $attributes['minTime'] ) ? (string) $attributes['minTime'] : '';
		$max_time    = isset( $attributes['maxTime'] ) ? (string) $attributes['maxTime'] : '';
		$step        = isset( $attributes['step'] ) ? (int) $attributes['step'] : 60;
		$field_width = isset( $attributes['fieldWidth'] ) ? (string) $attributes['fieldWidth'] : '100';

		$field_id = 'field-' . $field_name;

		$input = '<input type="time" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__input"';
		if ( $required ) {
			$input .= ' required';
		}
		if ( '' !== $min_time ) {
			$input .= ' min="' . esc_attr( $min_time ) . '"';
		}
		if ( '' !== $max_time ) {
			$input .= ' max="' . esc_attr( $max_time ) . '"';
		}
		$input .= ' step="' . (int) $step . '"';
		if ( '' !== $default_val ) {
			$input .= ' value="' . esc_attr( $default_val ) . '"';
		}
		if ( '' !== $help_text ) {
			$input .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		if ( $required ) {
			$input .= ' aria-required="true"';
		}
		$input .= ' data-field-type="time"/>';

		$inner  = designsetgo_form_field_label_html( $field_id, $label, $required );
		$inner .= $input;
		$inner .= designsetgo_form_field_help_html( $field_id, $help_text );

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'dsgo-form-field dsgo-form-field--time',
				'style' => designsetgo_form_field_width_style( $field_width ),
			)
		);

		echo '<div ' . $wrapper . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

designsetgo_render_form_time_field( $attributes, $content, $block );
