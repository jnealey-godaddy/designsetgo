<?php
/**
 * Serializer for designsetgo/form-builder.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/form-builder
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form_Builder_Serializer.
 */
class Form_Builder_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$block_class = 'wp-block-designsetgo-form-builder dsgo-form-builder';

		return self::generate_form_builder_html( $block_class, $attributes );
	}

	/**
	 * Generate wrapper HTML for form-builder block.
	 *
	 * @param string               $block_class Base block class.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string> Array with 'opening' and 'closing' keys.
	 */
	private static function generate_form_builder_html( string $block_class, array $attributes ): array {
		// save.js returns null when the author never picked a template, so the
		// form has no fields and WordPress serializes a self-closing comment
		// with no markup at all. Emitting the wrapper, an empty fields
		// container and a lonely submit button instead made every field-less
		// form invalid. Same treatment as a text-less heading segment.
		if ( isset( $attributes['hasFields'] ) && ! $attributes['hasFields'] ) {
			return array(
				'opening' => '',
				'closing' => '',
			);
		}

		// Get attributes with defaults from block.json.
		$form_id                          = $attributes['formId'] ?? '';
		$submit_button_text               = $attributes['submitButtonText'] ?? 'Submit';
		$submit_button_alignment          = $attributes['submitButtonAlignment'] ?? 'left';
		$submit_button_position           = $attributes['submitButtonPosition'] ?? 'below';
		$submit_button_variation          = $attributes['submitButtonVariation'] ?? 'default';
		$ajax_submit                      = $attributes['ajaxSubmit'] ?? true;
		$success_message                  = $attributes['successMessage'] ?? 'Thank you! Your form has been submitted successfully.';
		$error_message                    = $attributes['errorMessage'] ?? 'There was an error submitting the form. Please try again.';
		$field_spacing                    = $attributes['fieldSpacing'] ?? '1.5rem';
		$input_height                     = $attributes['inputHeight'] ?? '44px';
		$input_padding                    = $attributes['inputPadding'] ?? '0.75rem';
		$field_label_color                = $attributes['fieldLabelColor'] ?? '';
		$field_border_color               = $attributes['fieldBorderColor'] ?? '';
		$field_background_color           = $attributes['fieldBackgroundColor'] ?? '';
		$submit_button_color              = $attributes['submitButtonColor'] ?? '';
		$submit_button_background_color   = $attributes['submitButtonBackgroundColor'] ?? '';
		$submit_button_padding_vertical   = $attributes['submitButtonPaddingVertical'] ?? '0.75rem';
		$submit_button_padding_horizontal = $attributes['submitButtonPaddingHorizontal'] ?? '2rem';
		$submit_button_font_size          = $attributes['submitButtonFontSize'] ?? '';
		$submit_button_height             = $attributes['submitButtonHeight'] ?? '44px';
		$enable_honeypot                  = $attributes['enableHoneypot'] ?? true;
		$enable_turnstile                 = $attributes['enableTurnstile'] ?? false;
		$enable_email                     = $attributes['enableEmail'] ?? false;
		$email_to                         = $attributes['emailTo'] ?? '';
		$email_subject                    = $attributes['emailSubject'] ?? 'New Form Submission';
		$email_from_name                  = $attributes['emailFromName'] ?? '';
		$email_from_email                 = $attributes['emailFromEmail'] ?? '';
		$email_reply_to                   = $attributes['emailReplyTo'] ?? '';
		$email_body                       = $attributes['emailBody'] ?? '';

		// Submit-button style variation class - must match save.js. Validated
		// against the block.json enum so an AI-supplied value can't inject markup.
		// The `is-style-` namespace (not `dsgo-form__submit--*`) keeps it clear of
		// the layout/state/animation modifiers that share the BEM namespace.
		$submit_button_variation_class = in_array( $submit_button_variation, array( 'secondary', 'outline' ), true )
			? ' is-style-' . $submit_button_variation
			: '';

		// Build classes - must match save.js.
		$classes = $block_class;
		if ( $submit_button_alignment && 'below' === $submit_button_position ) {
			$classes .= ' dsgo-form-builder--align-' . $submit_button_alignment;
		}
		if ( 'inline' === $submit_button_position ) {
			$classes .= ' dsgo-form-builder--button-inline';
		}

		// Build CSS custom properties - must match save.js order. Each of these
		// three is spread conditionally in save.js (`...(fieldSpacing && {...})`),
		// so an unset value emits no declaration at all. Writing an empty value
		// instead made every form with default sizing invalid.
		$style_parts = array();
		if ( '' !== (string) $field_spacing ) {
			$style_parts[] = '--dsgo-form-field-spacing:' . esc_attr( $field_spacing );
		}
		if ( '' !== (string) $input_height ) {
			$style_parts[] = '--dsgo-form-input-height:' . esc_attr( $input_height );
		}
		if ( '' !== (string) $input_padding ) {
			$style_parts[] = '--dsgo-form-input-padding:' . esc_attr( $input_padding );
		}
		// save.js passes each colour through convertColorToCSSVar(), so a preset
		// shorthand such as `var:preset|color|contrast` or a bare slug reaches the
		// stored HTML as `var(--wp--preset--color--contrast)`. Writing the raw
		// attribute produced an invalid custom property and failed validation.
		if ( $field_label_color ) {
			$style_parts[] = '--dsgo-form-label-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $field_label_color ) );
		}
		// Omit when empty — .dsgo-form-builder in style.scss supplies the #d1d5db default.
		if ( $field_border_color ) {
			$style_parts[] = '--dsgo-form-border-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $field_border_color ) );
		}
		if ( $field_background_color ) {
			$style_parts[] = '--dsgo-form-field-bg:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $field_background_color ) );
		}
		// save.js runs this through validateCSSLength() (src/utils/css-generator.js),
		// which returns undefined - and so omits the property - for anything that
		// is not a bare number with a CSS length unit. The mirror wrote nothing at
		// all, so a configured corner radius never reached stored markup.
		$field_border_radius = isset( $attributes['fieldBorderRadius'] ) && is_string( $attributes['fieldBorderRadius'] )
			? trim( $attributes['fieldBorderRadius'] )
			: '';
		if ( '' !== $field_border_radius && preg_match( '/^\d+(\.\d+)?(px|em|rem|%|vh|vw|vmin|vmax|ch|ex)$/', $field_border_radius ) ) {
			$style_parts[] = '--dsgo-form-border-radius:' . esc_attr( $field_border_radius );
		}
		$style = implode( ';', $style_parts );

		// Build data attributes.
		$data_attrs = array(
			'data-form-id="' . esc_attr( $form_id ) . '"',
			'data-ajax-submit="' . ( $ajax_submit ? 'true' : 'false' ) . '"',
			'data-success-message="' . esc_attr( $success_message ) . '"',
			'data-error-message="' . esc_attr( $error_message ) . '"',
			// submitButtonText is sourced from the submit button's text, not a
			// wrapper attribute — save.js no longer emits data-submit-text, so
			// emitting it here would fail block validation.
			//
			// The email settings are deliberately absent too. save.js never
			// emits them: they are notification config, they live in the block
			// comment where the server reads them, and putting the recipient,
			// reply-to and body template into public markup would publish the
			// form's mail configuration to every visitor. Emitting them here
			// both leaked that and failed validation on every form.
		);
		if ( $enable_turnstile ) {
			$data_attrs[] = 'data-dsgo-turnstile="true"';
		}
		// save.js adds this only when a redirect is configured. Unlike the email
		// settings above, the redirect target is a public navigation URL the
		// frontend script reads from the markup, so it belongs here.
		if ( ! empty( $attributes['redirectUrl'] ) && is_string( $attributes['redirectUrl'] ) ) {
			$data_attrs[] = 'data-redirect-url="' . esc_url( $attributes['redirectUrl'] ) . '"';
		}
		$data_str = implode( ' ', $data_attrs );

		// Build button style - must match save.js order.
		$button_style_parts = array();
		if ( $submit_button_color ) {
			$button_style_parts[] = 'color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $submit_button_color ) );
		}
		if ( $submit_button_background_color ) {
			$button_style_parts[] = 'background-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $submit_button_background_color ) );
		}
		// Sizing is spread conditionally in save.js, so an unset value emits no
		// declaration and the button inherits the theme's global button styles.
		if ( '' !== (string) $submit_button_height ) {
			$button_style_parts[] = 'min-height:' . esc_attr( $submit_button_height );
		}
		if ( '' !== (string) $submit_button_padding_vertical ) {
			$button_style_parts[] = 'padding-top:' . esc_attr( $submit_button_padding_vertical );
			$button_style_parts[] = 'padding-bottom:' . esc_attr( $submit_button_padding_vertical );
		}
		if ( '' !== (string) $submit_button_padding_horizontal ) {
			$button_style_parts[] = 'padding-left:' . esc_attr( $submit_button_padding_horizontal );
			$button_style_parts[] = 'padding-right:' . esc_attr( $submit_button_padding_horizontal );
		}
		if ( $submit_button_font_size ) {
			$button_style_parts[] = 'font-size:' . esc_attr( $submit_button_font_size );
		}
		// Hover colours are CSS custom properties the stylesheet reads on
		// :hover. save.js writes them last, after font-size; the mirror wrote
		// neither, so an author's hover colours never reached stored markup.
		if ( ! empty( $attributes['submitButtonHoverBackgroundColor'] ) && is_string( $attributes['submitButtonHoverBackgroundColor'] ) ) {
			$button_style_parts[] = '--dsgo-button-hover-bg:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( $attributes['submitButtonHoverBackgroundColor'] ) );
		}
		if ( ! empty( $attributes['submitButtonHoverColor'] ) && is_string( $attributes['submitButtonHoverColor'] ) ) {
			$button_style_parts[] = '--dsgo-button-hover-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( $attributes['submitButtonHoverColor'] ) );
		}
		$button_style = implode( ';', $button_style_parts );

		// Opening HTML: outer div + form + fields wrapper.
		$opening  = '<div class="' . esc_attr( $classes ) . '"' .
			( '' !== $style ? ' style="' . $style . '"' : '' ) . ' ' . $data_str . '>';
		$opening .= '<form class="dsgo-form" method="post" novalidate>';
		$opening .= '<div class="dsgo-form__fields">';

		// Closing HTML: depends on button position.
		$closing = '';

		// Inline button goes inside fields wrapper, before closing.
		if ( 'inline' === $submit_button_position ) {
			$closing .= '<button type="submit" class="dsgo-form__submit dsgo-form__submit--inline' . $submit_button_variation_class . ' wp-element-button"' .
				( '' !== $button_style ? ' style="' . $button_style . '"' : '' ) . '>' . esc_html( $submit_button_text ) . '</button>';
		}

		// Close fields wrapper.
		$closing .= '</div>';

		// Honeypot field.
		if ( $enable_honeypot ) {
			$closing .= '<input type="text" name="dsg_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"/>';
		}

		// Hidden form ID.
		$closing .= '<input type="hidden" name="dsg_form_id" value="' . esc_attr( $form_id ) . '"/>';

		// Turnstile widget container.
		if ( $enable_turnstile ) {
			$closing .= '<div class="dsgo-turnstile-widget" data-dsgo-turnstile-container="true"></div>';
		}

		// Footer with button (below position).
		if ( 'below' === $submit_button_position ) {
			$closing .= '<div class="dsgo-form__footer">';
			$closing .= '<button type="submit" class="dsgo-form__submit' . $submit_button_variation_class . ' wp-element-button"' .
				( '' !== $button_style ? ' style="' . $button_style . '"' : '' ) . '>' . esc_html( $submit_button_text ) . '</button>';
			$closing .= '</div>';
		}

		// Message container.
		$closing .= '<div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display:none"></div>';

		// Close form and outer div.
		$closing .= '</form></div>';

		return array(
			'opening' => $opening,
			'closing' => $closing,
		);
	}
}
