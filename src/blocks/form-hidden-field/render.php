<?php
/**
 * Form Hidden Field - server-side render.
 *
 * Dynamic block: save() returns null, so the field's markup is produced here at
 * render time. No static HTML is stored to diff against — block validation can
 * never fail on this field.
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

$field_name = isset( $attributes['fieldName'] ) ? (string) $attributes['fieldName'] : '';
$value      = isset( $attributes['value'] ) ? (string) $attributes['value'] : '';

$inner = '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '" data-field-type="hidden"/>';

$wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'dsgo-form-field dsgo-form-field--hidden',
	)
);

echo '<div ' . $wrapper . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
