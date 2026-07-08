<?php
/**
 * Form Phone Field - server-side render.
 *
 * Dynamic block: save() returns null, so the field's markup is produced here at
 * render time. The authored (or pattern-substituted / translated) label,
 * placeholder and help text are emitted server-side, so no static HTML is stored
 * to diff against — block validation can never fail on translated field text.
 *
 * The country-code <select> is intentionally emitted empty: view.js hydrates its
 * <option> list at runtime from the shared COUNTRY_CODES constant, mirroring the
 * previous save() behaviour.
 *
 * @package DesignSetGo
 * @since 2.5.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused for dynamic blocks).
 * @param WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_render_form_phone_field' ) ) {
	/**
	 * Render the Form Phone Field block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content.
	 * @param WP_Block $block      Block instance.
	 * @return void
	 */
	function designsetgo_render_form_phone_field( $attributes, $content, $block ) {
		$field_name        = isset( $attributes['fieldName'] ) ? (string) $attributes['fieldName'] : '';
		$label             = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
		$placeholder       = isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '';
		$help_text         = isset( $attributes['helpText'] ) ? (string) $attributes['helpText'] : '';
		$required          = ! empty( $attributes['required'] );
		$default_val       = isset( $attributes['defaultValue'] ) ? (string) $attributes['defaultValue'] : '';
		$phone_format      = isset( $attributes['phoneFormat'] ) ? (string) $attributes['phoneFormat'] : 'any';
		$show_country_code = ! isset( $attributes['showCountryCode'] ) || ! empty( $attributes['showCountryCode'] );
		$country_code      = isset( $attributes['countryCode'] ) ? (string) $attributes['countryCode'] : '+1';
		$auto_format       = ! isset( $attributes['autoFormat'] ) || ! empty( $attributes['autoFormat'] );
		$field_width       = isset( $attributes['fieldWidth'] ) ? (string) $attributes['fieldWidth'] : '100';

		$field_id = 'field-' . $field_name;

		// Resolve the HTML pattern from the phone format.
		$pattern = '';
		switch ( $phone_format ) {
			case 'us':
				$pattern = '[0-9]{3}-[0-9]{3}-[0-9]{4}';
				break;
			case 'international':
				$pattern = '\+[0-9]{1,3}[0-9\s\-]{4,14}';
				break;
		}

		// Resolve the placeholder from the authored value or the phone format.
		if ( '' !== $placeholder ) {
			$field_placeholder = $placeholder;
		} else {
			switch ( $phone_format ) {
				case 'us':
					$field_placeholder = '555-123-4567';
					break;
				case 'international':
					$field_placeholder = '+1 555 123 4567';
					break;
				default:
					$field_placeholder = '';
			}
		}

		$phone_wrapper  = '<div class="dsgo-form-field__phone-wrapper" style="display: flex; gap: 0.5rem;" data-auto-format="' . esc_attr( $auto_format ? 'true' : 'false' ) . '">';

		if ( $show_country_code ) {
			// Options are intentionally empty here — view.js hydrates this <select> at
			// runtime from the shared COUNTRY_CODES constant. The form already requires
			// JS for auto-formatting, so the no-JS experience is an accepted trade-off.
			$phone_wrapper .= '<select name="' . esc_attr( $field_name . '_country_code' ) . '" class="dsgo-form-field__country-code" data-dsgo-country-code="' . esc_attr( $country_code ) . '" style="min-width: 85px; flex-shrink: 0;" aria-label="Country Code"></select>';
		}

		$input = '<input type="tel" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__input"';
		if ( '' !== $field_placeholder ) {
			$input .= ' placeholder="' . esc_attr( $field_placeholder ) . '"';
		}
		if ( $required ) {
			$input .= ' required';
		}
		if ( '' !== $default_val ) {
			$input .= ' value="' . esc_attr( $default_val ) . '"';
		}
		if ( '' !== $pattern ) {
			$input .= ' pattern="' . esc_attr( $pattern ) . '"';
		}
		if ( '' !== $help_text ) {
			$input .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		if ( $required ) {
			$input .= ' aria-required="true"';
		}
		$input .= ' data-field-type="tel" data-phone-format="' . esc_attr( $phone_format ) . '" style="flex: 1;"/>';

		$phone_wrapper .= $input;
		$phone_wrapper .= '</div>';

		$inner  = designsetgo_form_field_label_html( $field_id, $label, $required );
		$inner .= $phone_wrapper;
		$inner .= designsetgo_form_field_help_html( $field_id, $help_text );

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'dsgo-form-field dsgo-form-field--phone',
				'style' => designsetgo_form_field_width_style( $field_width ),
			)
		);

		echo '<div ' . $wrapper . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

designsetgo_render_form_phone_field( $attributes, $content, $block );
