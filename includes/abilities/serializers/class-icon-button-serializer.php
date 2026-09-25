<?php
/**
 * Serializer for designsetgo/icon-button.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/icon-button
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icon_Button_Serializer.
 */
class Icon_Button_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$text = isset( $attributes['text'] ) ? $attributes['text'] : '';
		$url  = isset( $attributes['url'] ) ? $attributes['url'] : '';
		// `_self`, not '', is block.json's default for linkTarget — so a
		// parsed block always has one and save() always emits
		// target="_self" alongside href. Defaulting to '' here suppressed
		// the attribute and every AI-inserted LINKED icon button failed
		// validation on first open.
		$link_target    = isset( $attributes['linkTarget'] ) && '' !== $attributes['linkTarget']
			? $attributes['linkTarget']
			: '_self';
		$rel            = isset( $attributes['rel'] ) ? $attributes['rel'] : '';
		$icon           = isset( $attributes['icon'] ) ? $attributes['icon'] : 'lightbulb';
		$icon_position  = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'start';
		$icon_size      = isset( $attributes['iconSize'] ) ? Serializer_Support::numeric_attribute( $attributes['iconSize'] ) : 20;
		$icon_gap       = isset( $attributes['iconGap'] ) ? $attributes['iconGap'] : '';
		$hover_anim     = isset( $attributes['hoverAnimation'] ) ? $attributes['hoverAnimation'] : 'none';
		$modal_close_id = isset( $attributes['modalCloseId'] ) ? $attributes['modalCloseId'] : '';

		// save.js omits data-icon-style / data-icon-stroke-width unless
		// the author sets them, so mirror that here. Both are validated
		// against block.json rather than passed through: callers of this
		// Ability are AI agents, so an out-of-enum iconStyle would emit a
		// data-icon-style the frontend injector doesn't understand, and a
		// non-scalar strokeWidth would stringify to "Array" (and warn).
		$icon_style_attr = '';
		if ( isset( $attributes['iconStyle'] ) && in_array( $attributes['iconStyle'], array( 'filled', 'outlined' ), true ) ) {
			$icon_style_attr = $attributes['iconStyle'];
		}
		$stroke_width = ( isset( $attributes['strokeWidth'] ) && is_numeric( $attributes['strokeWidth'] ) )
			? (float) $attributes['strokeWidth']
			: 1.5;

		// Read the current `justification`/`fullWidth` attributes; fall
		// back to the legacy `align` for callers that still pass it.
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

		// Build the button's own classes (matches the current save.js
		// marker-class scheme: gap is themed via `--has-icon`, not baked
		// inline, unless the author sets an explicit iconGap).
		$class_parts = array( 'dsgo-icon-button', 'wp-block-button', 'wp-block-button__link', 'wp-element-button' );
		if ( $has_icon ) {
			$class_parts[] = 'dsgo-icon-button--has-icon';
		}
		if ( $full_width ) {
			$class_parts[] = 'dsgo-icon-button--full-width';
		}
		if ( 'end' === $icon_position ) {
			$class_parts[] = 'dsgo-icon-button--icon-end';
		}
		// save.js has three branches, not one: 'explicit-none' becomes
		// --no-hover, plain 'none' emits nothing, and anything else is
		// interpolated. Interpolating all of them wrote
		// --explicit-none, a class no stylesheet defines.
		if ( 'explicit-none' === $hover_anim ) {
			$class_parts[] = 'dsgo-icon-button--no-hover';
		} elseif ( $hover_anim && 'none' !== $hover_anim ) {
			$class_parts[] = 'dsgo-icon-button--' . $hover_anim;
		}

		// Layout (display/width/flex-direction) lives in style.scss now,
		// not inline — only an explicit author gap is written inline.
		$style_parts = array();
		if ( $has_icon && '' !== $icon_gap ) {
			$style_parts[] = 'gap:' . esc_attr( $icon_gap );
		}

		// Colour, typography, border and shadow are skip-serialized on
		// the block root and re-applied to the button by save.js.
		$routed      = Serializer_Support::get_routed_visual_attributes( $attributes );
		$class_parts = array_merge( $class_parts, $routed['classes'] );
		$style_parts = array_merge(
			$style_parts,
			$routed['styles'],
			// Padding is skip-serialized on the root and re-applied here.
			Serializer_Support::routed_padding_styles( $attributes, true )
		);

		// Hover colours - save.js writes them as custom properties on the
		// button after padding. Without them an AI-inserted button with a
		// hover colour fails block validation on first open.
		foreach ( array(
			'hoverBackgroundColor' => '--dsgo-button-hover-bg',
			'hoverTextColor'       => '--dsgo-button-hover-color',
		) as $hover_attribute => $hover_property ) {
			if ( ! empty( $attributes[ $hover_attribute ] ) && is_string( $attributes[ $hover_attribute ] ) ) {
				$style_parts[] = $hover_property . ':' . Serializer_Support::convert_color_value_to_css_var( $attributes[ $hover_attribute ] );
			}
		}

		$button_style = implode( ';', $style_parts );

		// Icon HTML. Must match save.js: the icon span's layout
		// (display/align-items/justify-content/flex-shrink) lives in
		// style.scss, and width/height + data-icon-size are written ONLY
		// when the caller sets an explicit numeric iconSize — otherwise
		// the theme size token owns it. Emitting them unconditionally
		// (as this did) produced markup save() would never generate, so
		// the block validator flagged AI-inserted buttons as invalid.
		$icon_html = '';
		if ( $has_icon ) {
			$has_explicit_size = isset( $attributes['iconSize'] ) && is_numeric( $attributes['iconSize'] );
			$icon_attrs        = '';
			if ( $has_explicit_size ) {
				$icon_attrs .= ' style="' . esc_attr( 'width:' . $icon_size . 'px;height:' . $icon_size . 'px' ) . '"';
			}
			$icon_attrs .= ' data-icon-name="' . esc_attr( $icon ) . '"';
			if ( $has_explicit_size ) {
				$icon_attrs .= ' data-icon-size="' . esc_attr( (string) $icon_size ) . '"';
			}
			if ( $icon_style_attr ) {
				$icon_attrs .= ' data-icon-style="' . esc_attr( $icon_style_attr ) . '"';
			}
			if ( 'outlined' === $icon_style_attr ) {
				$icon_attrs .= ' data-icon-stroke-width="' . esc_attr( (string) $stroke_width ) . '"';
			}
			$icon_html = '<span class="dsgo-icon-button__icon dsgo-lazy-icon"' . $icon_attrs . '></span>';
		}

		// Text HTML.
		$text_html = '<span class="dsgo-icon-button__text">' . wp_kses_post( $text ) . '</span>';

		// Build element (button or link).
		$tag = $url ? 'a' : 'button';

		// Additional attributes.
		$extra_attrs = '';
		if ( $url ) {
			$extra_attrs .= ' href="' . esc_url( $url ) . '"';
			if ( $link_target ) {
				$extra_attrs .= ' target="' . esc_attr( $link_target ) . '"';
			}
			$rel_value = '_blank' === $link_target ? ( $rel ? $rel : 'noopener noreferrer' ) : $rel;
			if ( $rel_value ) {
				$extra_attrs .= ' rel="' . esc_attr( $rel_value ) . '"';
			}
		} else {
			$extra_attrs .= ' type="button"';
		}
		if ( $modal_close_id ) {
			$extra_attrs .= ' data-dsgo-modal-close="' . esc_attr( $modal_close_id ) . '"';
		}
		// save.js emits aria-label only when the author sets one.
		if ( ! empty( $attributes['ariaLabel'] ) && is_string( $attributes['ariaLabel'] ) ) {
			$extra_attrs .= ' aria-label="' . esc_attr( $attributes['ariaLabel'] ) . '"';
		}

		$inner_html = $icon_html . $text_html;

		// The block root is a block-level justification wrapper — core's
		// constrained layout caps IT at the content column — with the
		// button shrink-wrapped inside it (see save.js). Matches
		// `getJustificationClass()` (src/utils/justification.js).
		$wrapper_class     = 'wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--' . $justification;
		$button_style_attr = '' !== $button_style ? ' style="' . esc_attr( $button_style ) . '"' : '';

		return array(
			'opening' => '<div class="' . esc_attr( $wrapper_class ) . '"><' . $tag . ' class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $button_style_attr . $extra_attrs . '>' . $inner_html,
			'closing' => '</' . $tag . '></div>',
		);
	}
}
