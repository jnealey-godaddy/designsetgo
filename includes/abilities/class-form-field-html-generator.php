<?php
/**
 * Form Field HTML Generator for Block Inserter.
 *
 * Generates HTML markup for form field blocks when inserted via the
 * Abilities API. Extracted from Block_Inserter to reduce class size.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.2.0
 */

namespace DesignSetGo\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form Field HTML Generator class.
 *
 * Handles HTML generation for all form field block types:
 * text, email, textarea, select, checkbox, number, phone, date, time, url, hidden.
 */
class Form_Field_Html_Generator {

	/**
	 * Generate HTML for a form field block by name.
	 *
	 * @param string               $block_name Block name (e.g., 'designsetgo/form-text-field').
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string|null Generated HTML or null if not a form field block.
	 */
	public static function generate( string $block_name, array $attributes ): ?string {
		switch ( $block_name ) {
			case 'designsetgo/form-text-field':
				return self::generate_text_field( $attributes );

			case 'designsetgo/form-email-field':
				return self::generate_email_field( $attributes );

			case 'designsetgo/form-textarea-field':
				return self::generate_textarea( $attributes );

			case 'designsetgo/form-select-field':
				return self::generate_select_field( $attributes );

			case 'designsetgo/form-checkbox-field':
				return self::generate_checkbox_field( $attributes );

			case 'designsetgo/form-number-field':
				return self::generate_number_field( $attributes );

			case 'designsetgo/form-phone-field':
				return self::generate_phone_field( $attributes );

			case 'designsetgo/form-date-field':
				return self::generate_date_field( $attributes );

			case 'designsetgo/form-time-field':
				return self::generate_time_field( $attributes );

			case 'designsetgo/form-url-field':
				return self::generate_url_field( $attributes );

			case 'designsetgo/form-hidden-field':
				return self::generate_hidden_field( $attributes );

			default:
				return null;
		}
	}

	/**
	 * Generate field width style for form fields.
	 *
	 * @param string $field_width Width percentage.
	 * @return string CSS style string.
	 */
	public static function get_field_width_style( string $field_width ): string {
		if ( '100' === $field_width ) {
			return 'flex-basis:100%;max-width:100%';
		}
		return 'flex-basis:calc(' . esc_attr( $field_width ) . '% - var(--dsgo-form-field-spacing, 1.5rem) / 2);max-width:calc(' . esc_attr( $field_width ) . '% - var(--dsgo-form-field-spacing, 1.5rem) / 2)';
	}

	/**
	 * Generate form field label HTML.
	 *
	 * @param string $field_id Field ID.
	 * @param string $label Label text.
	 * @param bool   $required Whether field is required.
	 * @return string Label HTML.
	 */
	public static function generate_label( string $field_id, string $label, bool $required ): string {
		$html = '<label for="' . esc_attr( $field_id ) . '" class="dsgo-form-field__label">' . esc_html( $label );
		if ( $required ) {
			$html .= '<span class="dsgo-form-field__required"><span aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( 'Required', 'designsetgo' ) . '</span></span>';
		}
		$html .= '</label>';
		return $html;
	}

	/**
	 * Generate form field help text HTML.
	 *
	 * @param string $field_id Field ID.
	 * @param string $help_text Help text.
	 * @return string Help text HTML.
	 */
	public static function generate_help_text( string $field_id, string $help_text ): string {
		if ( empty( $help_text ) ) {
			return '';
		}
		return '<p id="' . esc_attr( $field_id ) . '-help" class="dsgo-form-field__help">' . esc_html( $help_text ) . '</p>';
	}

	/**
	 * Generate text field HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Field HTML.
	 */
	private static function generate_text_field( array $attributes ): string {
		$field_name         = $attributes['fieldName'] ?? '';
		$label              = $attributes['label'] ?? __( 'Text Field', 'designsetgo' );
		$placeholder        = $attributes['placeholder'] ?? '';
		$help_text          = $attributes['helpText'] ?? '';
		$required           = $attributes['required'] ?? false;
		$default_value      = $attributes['defaultValue'] ?? '';
		$min_length         = $attributes['minLength'] ?? 0;
		$max_length         = $attributes['maxLength'] ?? 0;
		$validation         = $attributes['validation'] ?? 'none';
		$validation_pattern = $attributes['validationPattern'] ?? '';
		$validation_message = $attributes['validationMessage'] ?? '';
		$field_width        = $attributes['fieldWidth'] ?? '100';

		$field_id = 'field-' . $field_name;
		$style    = self::get_field_width_style( $field_width );

		// Get validation pattern.
		$pattern = null;
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
				$pattern = $validation_pattern;
				break;
		}

		$html  = '<div class="wp-block-designsetgo-form-text-field dsgo-form-field dsgo-form-field--text" style="' . $style . '">';
		$html .= self::generate_label( $field_id, $label, $required );

		$html .= '<input type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__input"';
		if ( $placeholder ) {
			$html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		}
		if ( $required ) {
			$html .= ' required aria-required="true"';
		}
		if ( $min_length > 0 ) {
			$html .= ' minlength="' . intval( $min_length ) . '"';
		}
		if ( $max_length > 0 ) {
			$html .= ' maxlength="' . intval( $max_length ) . '"';
		}
		if ( $pattern ) {
			$html .= ' pattern="' . esc_attr( $pattern ) . '"';
		}
		if ( $validation_message ) {
			$html .= ' title="' . esc_attr( $validation_message ) . '"';
		}
		if ( $default_value ) {
			$html .= ' value="' . esc_attr( $default_value ) . '"';
		}
		if ( $help_text ) {
			$html .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		$html .= ' data-field-type="text"/>';

		$html .= self::generate_help_text( $field_id, $help_text );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate email field HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Field HTML.
	 */
	private static function generate_email_field( array $attributes ): string {
		$field_name    = $attributes['fieldName'] ?? '';
		$label         = $attributes['label'] ?? __( 'Email', 'designsetgo' );
		$placeholder   = $attributes['placeholder'] ?? '';
		$help_text     = $attributes['helpText'] ?? '';
		$required      = $attributes['required'] ?? false;
		$default_value = $attributes['defaultValue'] ?? '';
		$field_width   = $attributes['fieldWidth'] ?? '100';

		$field_id = 'field-' . $field_name;
		$style    = self::get_field_width_style( $field_width );

		$html  = '<div class="wp-block-designsetgo-form-email-field dsgo-form-field dsgo-form-field--email" style="' . $style . '">';
		$html .= self::generate_label( $field_id, $label, $required );

		$html .= '<input type="email" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__input"';
		if ( $placeholder ) {
			$html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		}
		if ( $required ) {
			$html .= ' required aria-required="true"';
		}
		if ( $default_value ) {
			$html .= ' value="' . esc_attr( $default_value ) . '"';
		}
		if ( $help_text ) {
			$html .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		$html .= ' data-field-type="email"/>';

		$html .= self::generate_help_text( $field_id, $help_text );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate textarea HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Field HTML.
	 */
	private static function generate_textarea( array $attributes ): string {
		$field_name    = $attributes['fieldName'] ?? '';
		$label         = $attributes['label'] ?? __( 'Message', 'designsetgo' );
		$placeholder   = $attributes['placeholder'] ?? '';
		$help_text     = $attributes['helpText'] ?? '';
		$required      = $attributes['required'] ?? false;
		$default_value = $attributes['defaultValue'] ?? '';
		$rows          = $attributes['rows'] ?? 4;
		$max_length    = $attributes['maxLength'] ?? 0;
		$field_width   = $attributes['fieldWidth'] ?? '100';

		$field_id = 'field-' . $field_name;
		$style    = self::get_field_width_style( $field_width );

		$html  = '<div class="wp-block-designsetgo-form-textarea dsgo-form-field dsgo-form-field--textarea" style="' . $style . '">';
		$html .= self::generate_label( $field_id, $label, $required );

		$html .= '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__textarea"';
		if ( $placeholder ) {
			$html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		}
		if ( $required ) {
			$html .= ' required aria-required="true"';
		}
		$html .= ' rows="' . intval( $rows ) . '"';
		if ( $max_length > 0 ) {
			$html .= ' maxlength="' . intval( $max_length ) . '"';
		}
		if ( $help_text ) {
			$html .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		$html .= ' data-field-type="textarea">';
		if ( $default_value ) {
			$html .= esc_html( $default_value );
		}
		$html .= '</textarea>';

		$html .= self::generate_help_text( $field_id, $help_text );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate select field HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Field HTML.
	 */
	private static function generate_select_field( array $attributes ): string {
		$field_name    = $attributes['fieldName'] ?? '';
		$label         = $attributes['label'] ?? __( 'Select', 'designsetgo' );
		$help_text     = $attributes['helpText'] ?? '';
		$required      = $attributes['required'] ?? false;
		$default_value = $attributes['defaultValue'] ?? '';
		$options       = $attributes['options'] ?? array();
		$placeholder   = $attributes['placeholder'] ?? '';
		$field_width   = $attributes['fieldWidth'] ?? '100';

		$field_id = 'field-' . $field_name;
		$style    = self::get_field_width_style( $field_width );

		$html  = '<div class="wp-block-designsetgo-form-select-field dsgo-form-field dsgo-form-field--select" style="' . $style . '">';
		$html .= self::generate_label( $field_id, $label, $required );

		$html .= '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__select"';
		if ( $required ) {
			$html .= ' required aria-required="true"';
		}
		if ( $help_text ) {
			$html .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		$html .= ' data-field-type="select">';

		if ( $placeholder ) {
			$html .= '<option value="">' . esc_html( $placeholder ) . '</option>';
		}
		foreach ( $options as $option ) {
			$value     = $option['value'] ?? '';
			$opt_label = $option['label'] ?? $value;
			$selected  = ( $value === $default_value ) ? ' selected' : '';
			$html     .= '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $opt_label ) . '</option>';
		}
		$html .= '</select>';

		$html .= self::generate_help_text( $field_id, $help_text );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate checkbox field HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Field HTML.
	 */
	private static function generate_checkbox_field( array $attributes ): string {
		$field_name    = $attributes['fieldName'] ?? '';
		$label         = $attributes['label'] ?? __( 'Checkbox', 'designsetgo' );
		$help_text     = $attributes['helpText'] ?? '';
		$required      = $attributes['required'] ?? false;
		$default_value = $attributes['defaultValue'] ?? false;
		$field_width   = $attributes['fieldWidth'] ?? '100';

		$field_id = 'field-' . $field_name;
		$style    = self::get_field_width_style( $field_width );

		$html  = '<div class="wp-block-designsetgo-form-checkbox-field dsgo-form-field dsgo-form-field--checkbox" style="' . $style . '">';
		$html .= '<label class="dsgo-form-field__checkbox-label">';
		$html .= '<input type="checkbox" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__checkbox"';
		if ( $required ) {
			$html .= ' required aria-required="true"';
		}
		if ( $default_value ) {
			$html .= ' checked';
		}
		if ( $help_text ) {
			$html .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		$html .= ' data-field-type="checkbox"/>';
		$html .= '<span class="dsgo-form-field__checkbox-text">' . esc_html( $label );
		if ( $required ) {
			$html .= '<span class="dsgo-form-field__required"><span aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( 'Required', 'designsetgo' ) . '</span></span>';
		}
		$html .= '</span></label>';

		$html .= self::generate_help_text( $field_id, $help_text );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate number field HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Field HTML.
	 */
	private static function generate_number_field( array $attributes ): string {
		$field_name    = $attributes['fieldName'] ?? '';
		$label         = $attributes['label'] ?? __( 'Number', 'designsetgo' );
		$placeholder   = $attributes['placeholder'] ?? '';
		$help_text     = $attributes['helpText'] ?? '';
		$required      = $attributes['required'] ?? false;
		$default_value = $attributes['defaultValue'] ?? '';
		$min           = $attributes['min'] ?? null;
		$max           = $attributes['max'] ?? null;
		$step          = $attributes['step'] ?? 1;
		$field_width   = $attributes['fieldWidth'] ?? '100';

		$field_id = 'field-' . $field_name;
		$style    = self::get_field_width_style( $field_width );

		$html  = '<div class="wp-block-designsetgo-form-number-field dsgo-form-field dsgo-form-field--number" style="' . $style . '">';
		$html .= self::generate_label( $field_id, $label, $required );

		$html .= '<input type="number" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__input"';
		if ( $placeholder ) {
			$html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		}
		if ( $required ) {
			$html .= ' required aria-required="true"';
		}
		if ( null !== $min ) {
			$html .= ' min="' . esc_attr( (string) $min ) . '"';
		}
		if ( null !== $max ) {
			$html .= ' max="' . esc_attr( (string) $max ) . '"';
		}
		$html .= ' step="' . esc_attr( (string) $step ) . '"';
		if ( '' !== $default_value ) {
			$html .= ' value="' . esc_attr( $default_value ) . '"';
		}
		if ( $help_text ) {
			$html .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		$html .= ' data-field-type="number"/>';

		$html .= self::generate_help_text( $field_id, $help_text );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate phone field HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Field HTML.
	 */
	private static function generate_phone_field( array $attributes ): string {
		$field_name    = $attributes['fieldName'] ?? '';
		$label         = $attributes['label'] ?? __( 'Phone', 'designsetgo' );
		$placeholder   = $attributes['placeholder'] ?? '';
		$help_text     = $attributes['helpText'] ?? '';
		$required      = $attributes['required'] ?? false;
		$default_value = $attributes['defaultValue'] ?? '';
		$field_width   = $attributes['fieldWidth'] ?? '100';

		$field_id = 'field-' . $field_name;
		$style    = self::get_field_width_style( $field_width );

		$html  = '<div class="wp-block-designsetgo-form-phone-field dsgo-form-field dsgo-form-field--phone" style="' . $style . '">';
		$html .= self::generate_label( $field_id, $label, $required );

		$html .= '<input type="tel" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__input"';
		if ( $placeholder ) {
			$html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		}
		if ( $required ) {
			$html .= ' required aria-required="true"';
		}
		if ( $default_value ) {
			$html .= ' value="' . esc_attr( $default_value ) . '"';
		}
		if ( $help_text ) {
			$html .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		$html .= ' data-field-type="tel"/>';

		$html .= self::generate_help_text( $field_id, $help_text );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate date field HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Field HTML.
	 */
	private static function generate_date_field( array $attributes ): string {
		$field_name    = $attributes['fieldName'] ?? '';
		$label         = $attributes['label'] ?? __( 'Date', 'designsetgo' );
		$help_text     = $attributes['helpText'] ?? '';
		$required      = $attributes['required'] ?? false;
		$default_value = $attributes['defaultValue'] ?? '';
		$min_date      = $attributes['minDate'] ?? '';
		$max_date      = $attributes['maxDate'] ?? '';
		$field_width   = $attributes['fieldWidth'] ?? '100';

		$field_id = 'field-' . $field_name;
		$style    = self::get_field_width_style( $field_width );

		$html  = '<div class="wp-block-designsetgo-form-date-field dsgo-form-field dsgo-form-field--date" style="' . $style . '">';
		$html .= self::generate_label( $field_id, $label, $required );

		$html .= '<input type="date" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__input"';
		if ( $required ) {
			$html .= ' required aria-required="true"';
		}
		if ( $min_date ) {
			$html .= ' min="' . esc_attr( $min_date ) . '"';
		}
		if ( $max_date ) {
			$html .= ' max="' . esc_attr( $max_date ) . '"';
		}
		if ( $default_value ) {
			$html .= ' value="' . esc_attr( $default_value ) . '"';
		}
		if ( $help_text ) {
			$html .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		$html .= ' data-field-type="date"/>';

		$html .= self::generate_help_text( $field_id, $help_text );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate time field HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Field HTML.
	 */
	private static function generate_time_field( array $attributes ): string {
		$field_name    = $attributes['fieldName'] ?? '';
		$label         = $attributes['label'] ?? __( 'Time', 'designsetgo' );
		$help_text     = $attributes['helpText'] ?? '';
		$required      = $attributes['required'] ?? false;
		$default_value = $attributes['defaultValue'] ?? '';
		$field_width   = $attributes['fieldWidth'] ?? '100';

		$field_id = 'field-' . $field_name;
		$style    = self::get_field_width_style( $field_width );

		$html  = '<div class="wp-block-designsetgo-form-time-field dsgo-form-field dsgo-form-field--time" style="' . $style . '">';
		$html .= self::generate_label( $field_id, $label, $required );

		$html .= '<input type="time" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__input"';
		if ( $required ) {
			$html .= ' required aria-required="true"';
		}
		if ( $default_value ) {
			$html .= ' value="' . esc_attr( $default_value ) . '"';
		}
		if ( $help_text ) {
			$html .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		$html .= ' data-field-type="time"/>';

		$html .= self::generate_help_text( $field_id, $help_text );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate URL field HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Field HTML.
	 */
	private static function generate_url_field( array $attributes ): string {
		$field_name    = $attributes['fieldName'] ?? '';
		$label         = $attributes['label'] ?? __( 'URL', 'designsetgo' );
		$placeholder   = $attributes['placeholder'] ?? '';
		$help_text     = $attributes['helpText'] ?? '';
		$required      = $attributes['required'] ?? false;
		$default_value = $attributes['defaultValue'] ?? '';
		$field_width   = $attributes['fieldWidth'] ?? '100';

		$field_id = 'field-' . $field_name;
		$style    = self::get_field_width_style( $field_width );

		$html  = '<div class="wp-block-designsetgo-form-url-field dsgo-form-field dsgo-form-field--url" style="' . $style . '">';
		$html .= self::generate_label( $field_id, $label, $required );

		$html .= '<input type="url" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="dsgo-form-field__input"';
		if ( $placeholder ) {
			$html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		}
		if ( $required ) {
			$html .= ' required aria-required="true"';
		}
		if ( $default_value ) {
			$html .= ' value="' . esc_attr( $default_value ) . '"';
		}
		if ( $help_text ) {
			$html .= ' aria-describedby="' . esc_attr( $field_id ) . '-help"';
		}
		$html .= ' data-field-type="url"/>';

		$html .= self::generate_help_text( $field_id, $help_text );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate hidden field HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Field HTML.
	 */
	private static function generate_hidden_field( array $attributes ): string {
		$field_name    = $attributes['fieldName'] ?? '';
		$default_value = $attributes['defaultValue'] ?? '';

		return '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $default_value ) . '" data-field-type="hidden"/>';
	}
}
