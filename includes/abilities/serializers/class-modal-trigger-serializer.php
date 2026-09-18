<?php
/**
 * Serializer for designsetgo/modal-trigger.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/modal-trigger
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ModalTrigger_Serializer.
 */
class ModalTrigger_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$target_modal_id = isset( $attributes['targetModalId'] ) ? $attributes['targetModalId'] : '';
		$text            = isset( $attributes['text'] ) ? $attributes['text'] : 'Open Modal';
		$button_style    = isset( $attributes['buttonStyle'] ) ? $attributes['buttonStyle'] : 'fill';
		$icon            = isset( $attributes['icon'] ) ? $attributes['icon'] : '';
		$icon_position   = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'none';
		$icon_gap        = isset( $attributes['iconGap'] ) ? $attributes['iconGap'] : '8px';
		$icon_style      = isset( $attributes['iconStyle'] ) ? $attributes['iconStyle'] : '';
		$stroke_width    = isset( $attributes['strokeWidth'] ) ? $attributes['strokeWidth'] : 1.5;

		// Read the current `justification`/`fullWidth` attributes; fall
		// back to the legacy `align` for callers that still pass it
		// (mirrors the icon-button case above).
		$justification = isset( $attributes['justification'] )
			? $attributes['justification']
			: ( isset( $attributes['align'] ) ? $attributes['align'] : 'left' );
		if ( ! in_array( $justification, array( 'left', 'center', 'right' ), true ) ) {
			$justification = 'left';
		}
		// save.js reads `fullWidth` alone. Treating align 'full' as implying
		// it is left over from before `justification` replaced `align`
		// here: an aligned button picked up a --full-width class save()
		// never writes.
		$full_width = ! empty( $attributes['fullWidth'] );

		$has_icon = 'none' !== $icon_position && $icon;

		// Button classes — must match save.js's clsx() list exactly, or
		// the block validator flags AI-inserted triggers as invalid.
		$class_parts = array(
			'dsgo-modal-trigger',
			'dsgo-modal-trigger--' . $button_style,
			'wp-block-button',
			'wp-block-button__link',
			'wp-element-button',
		);
		if ( $full_width ) {
			$class_parts[] = 'dsgo-modal-trigger--full-width';
		}
		if ( 'end' === $icon_position ) {
			$class_parts[] = 'dsgo-modal-trigger--icon-end';
		}

		// Colour, typography, border and shadow are skip-serialized on the
		// block root and re-applied to the trigger by save.js, exactly as
		// Icon Button does. None of them were emitted here, so a trigger
		// given any colour stored markup save() would not reproduce.
		$trigger_routed = Serializer_Support::get_routed_visual_attributes( $attributes );
		$class_parts    = array_merge( $class_parts, $trigger_routed['classes'] );

		// save.js writes the gap inline whenever there is an icon and an
		// iconGap (which defaults to 8px); layout lives in style.scss.
		$trigger_styles = $trigger_routed['styles'];
		if ( $has_icon && '' !== $icon_gap ) {
			$trigger_styles[] = 'gap:' . $icon_gap;
		}
		// Padding is skip-serialized on the root and re-applied here.
		// Unlike Icon Button, save.js writes the value through unchanged.
		$trigger_styles    = array_merge( $trigger_styles, Serializer_Support::routed_padding_styles( $attributes, false ) );
		$button_style_attr = empty( $trigger_styles )
			? ''
			: ' style="' . esc_attr( implode( ';', $trigger_styles ) ) . '"';

		// Icon span — size is only baked inline when the caller sets an
		// explicit numeric iconSize, so the theme token owns it otherwise.
		$icon_html = '';
		if ( $has_icon ) {
			$icon_attrs = '';
			if ( isset( $attributes['iconSize'] ) && is_numeric( $attributes['iconSize'] ) ) {
				$size        = Serializer_Support::numeric_attribute( $attributes['iconSize'] );
				$icon_attrs .= ' style="' . esc_attr( 'width:' . $size . 'px;height:' . $size . 'px' ) . '"';
			}
			$icon_attrs .= ' data-icon-name="' . esc_attr( $icon ) . '"';
			if ( $icon_style ) {
				$icon_attrs .= ' data-icon-style="' . esc_attr( $icon_style ) . '"';
			}
			if ( 'outlined' === $icon_style ) {
				$icon_attrs .= ' data-icon-stroke-width="' . esc_attr( (string) $stroke_width ) . '"';
			}
			$icon_html = '<span class="dsgo-modal-trigger__icon dsgo-lazy-icon"' . $icon_attrs . '></span>';
		}

		$inner_html  = '<button class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $button_style_attr;
		$inner_html .= ' type="button" data-dsgo-modal-trigger="' . esc_attr( $target_modal_id ) . '">';
		$inner_html .= $icon_html;
		$inner_html .= '<span class="dsgo-modal-trigger__text">' . wp_kses_post( $text ) . '</span>';
		$inner_html .= '</button>';

		// The block root is a block-level justification wrapper — core's
		// constrained layout caps IT at the content column — with the
		// button shrink-wrapped inside it (see save.js). Matches
		// `getJustificationClass()` (src/utils/justification.js).
		$wrapper_class = 'wp-block-designsetgo-modal-trigger dsgo-justify dsgo-justify--' . $justification;

		return array(
			'opening' => '<div class="' . esc_attr( $wrapper_class ) . '">' . $inner_html,
			'closing' => '</div>',
		);
	}
}
