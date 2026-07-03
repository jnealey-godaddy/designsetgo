<?php
/**
 * Form Select Field - server-side render.
 *
 * Dynamic block: save() returns null, so the field's markup is produced here at
 * render time. The authored (or pattern-substituted / translated) label,
 * placeholder and option text are emitted server-side, so no static HTML is
 * stored to diff against — block validation can never fail on translated field
 * text.
 *
 * @package DesignSetGo
 * @since 2.5.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused for dynamic blocks).
 * @param WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name  = isset( $attributes['fieldName'] ) ? (string) $attributes['fieldName'] : '';
$label       = isset( $attributes['label'] ) ? (string) $attributes['label'] : __( 'Select Option', 'designsetgo' );
$help_text   = isset( $attributes['helpText'] ) ? (string) $attributes['helpText'] : '';
$required    = ! empty( $attributes['required'] );
$default_val = isset( $attributes['defaultValue'] ) ? (string) $attributes['defaultValue'] : '';
$options     = isset( $attributes['options'] ) && is_array( $attributes['options'] ) ? $attributes['options'] : array();
$field_width = isset( $attributes['fieldWidth'] ) ? (string) $attributes['fieldWidth'] : '100';

// Fall back to a translatable placeholder only when the key is entirely
// unset — an explicit empty string means the author opted out of one.
if ( array_key_exists( 'placeholder', $attributes ) ) {
	$placeholder = (string) $attributes['placeholder'];
} else {
	$placeholder = __( '-- Select an option --', 'designsetgo' );
}

$field_id = 'field-' . $field_name;

$select = '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__select"';
if ( $required ) {
	$select .= ' required';
}
if ( '' !== $help_text ) {
	$select .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
}
if ( $required ) {
	$select .= ' aria-required="true"';
}
$select .= ' data-field-type="select">';

if ( '' !== $placeholder ) {
	$select .= '<option value="">' . esc_html( $placeholder ) . '</option>';
}

foreach ( $options as $option ) {
	$option_value = isset( $option['value'] ) ? (string) $option['value'] : '';
	$option_label = isset( $option['label'] ) ? (string) $option['label'] : '';

	// Mirror save.js: when a placeholder is present, skip any option that
	// duplicates the empty placeholder value.
	if ( '' !== $placeholder && '' === $option_value ) {
		continue;
	}

	$selected = ( '' !== $default_val && $option_value === $default_val ) ? ' selected' : '';

	$select .= '<option value="' . esc_attr( $option_value ) . '"' . $selected . '>' . esc_html( $option_label ) . '</option>';
}

$select .= '</select>';

$inner  = designsetgo_form_field_label_html( $field_id, $label, $required );
$inner .= $select;
$inner .= designsetgo_form_field_help_html( $field_id, $help_text );

$wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'dsgo-form-field dsgo-form-field--select',
		'style' => designsetgo_form_field_width_style( $field_width ),
	)
);

echo '<div ' . $wrapper . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
