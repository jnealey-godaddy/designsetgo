<?php
/**
 * Serializer for designsetgo/card.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/card
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Card_Serializer.
 */
class Card_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$layout_preset     = isset( $attributes['layoutPreset'] ) ? $attributes['layoutPreset'] : 'standard';
		$visual_style      = isset( $attributes['visualStyle'] ) ? $attributes['visualStyle'] : 'default';
		$title             = isset( $attributes['title'] ) ? $attributes['title'] : '';
		$subtitle          = isset( $attributes['subtitle'] ) ? $attributes['subtitle'] : '';
		$body_text         = isset( $attributes['bodyText'] ) ? $attributes['bodyText'] : '';
		$show_title        = isset( $attributes['showTitle'] ) ? $attributes['showTitle'] : true;
		$show_subtitle     = isset( $attributes['showSubtitle'] ) ? $attributes['showSubtitle'] : true;
		$show_body         = isset( $attributes['showBody'] ) ? $attributes['showBody'] : true;
		$show_cta          = isset( $attributes['showCta'] ) ? $attributes['showCta'] : true;
		$content_alignment = isset( $attributes['contentAlignment'] ) ? $attributes['contentAlignment'] : 'left';

		$card_align  = Serializer_Support::align_class( $block_name, $attributes );
		$outer_class = 'wp-block-designsetgo-card' . ( '' !== $card_align ? ' ' . $card_align : '' ) .
			' dsgo-card dsgo-card--' . esc_attr( $layout_preset ) . ' dsgo-card--style-' . esc_attr( $visual_style );

		$card_border = '';
		if ( ! empty( $attributes['borderColor'] ) && 'minimal' !== $visual_style ) {
			$card_border = ' style="border-color:' . esc_attr( $attributes['borderColor'] ) . ';border-width:' . ( 'outlined' === $visual_style ? '2px' : '1px' ) . ';border-style:solid"';
		}

		// Build content HTML.
		$content_class = 'dsgo-card__content ';
		if ( 'background' === $layout_preset ) {
			$content_class .= 'dsgo-card__content--' . esc_attr( $content_alignment );
		}

		// title/subtitle/bodyText are DOM-sourced, so the element must stay
		// in the markup whenever the text is non-empty (matching save.js) —
		// a hidden field carries the `--hidden` modifier instead of being
		// omitted, otherwise the sourced text would be silently lost.
		$content_html = '';
		if ( $title ) {
			$title_class   = 'dsgo-card__title' . ( $show_title ? '' : ' dsgo-card__title--hidden' );
			$content_html .= '<h3 class="' . esc_attr( $title_class ) . '">' . wp_kses_post( $title ) . '</h3>';
		}
		if ( $subtitle ) {
			$subtitle_class = 'dsgo-card__subtitle' . ( $show_subtitle ? '' : ' dsgo-card__subtitle--hidden' );
			$content_html  .= '<p class="' . esc_attr( $subtitle_class ) . '">' . wp_kses_post( $subtitle ) . '</p>';
		}
		if ( $body_text ) {
			$body_class    = 'dsgo-card__body' . ( $show_body ? '' : ' dsgo-card__body--hidden' );
			$content_html .= '<p class="' . esc_attr( $body_class ) . '">' . wp_kses_post( $body_text ) . '</p>';
		}

		// CTA area for inner blocks.
		$cta_opening = '';
		$cta_closing = '';
		if ( $show_cta ) {
			$cta_opening = '<div class="dsgo-card__cta">';
			$cta_closing = '</div>';
		}

		// Image. save.js renders nothing unless the image is shown, the
		// layout is not 'minimal', and the URL passes isValidImageUrl()
		// (src/utils/is-valid-image-url.js) - which allows http(s) only,
		// so a javascript: or data: URL produces no element at all
		// rather than an escaped one. The mirror emitted no image in any
		// case, so every card with a picture failed validation.
		$card_image_url  = isset( $attributes['imageUrl'] ) && is_string( $attributes['imageUrl'] ) ? $attributes['imageUrl'] : '';
		$card_image_html = '';
		$card_background_html = '';

		if ( ! empty( $attributes['showImage'] )
			&& 'minimal' !== $layout_preset
			&& '' !== $card_image_url
			&& preg_match( '#^https?://#', $card_image_url )
		) {
			if ( 'background' === $layout_preset ) {
				// The background variant sits OUTSIDE .dsgo-card__inner
				// and carries the overlay instead of an <img>.
				// save.js always writes an overlay here, falling back to
				// the theme contrast colour when none is chosen, with
				// opacity stored 0..100 and written 0..1.
				$card_overlay_color = isset( $attributes['overlayColor'] ) && '' !== $attributes['overlayColor']
					? (string) $attributes['overlayColor']
					: 'var(--wp--preset--color--contrast, #000)';
				$card_overlay_pct   = isset( $attributes['overlayOpacity'] ) && is_numeric( $attributes['overlayOpacity'] )
					? (float) $attributes['overlayOpacity']
					: 0.0;
				$card_overlay_style = 'background-color:' . $card_overlay_color .
					';opacity:' . Serializer_Support::format_js_number( $card_overlay_pct / 100 );

				$card_background_html = '<div class="dsgo-card__background" style="' .
					esc_attr( 'background-image:url(' . $card_image_url . ')' ) . '">' .
					'<div class="dsgo-card__overlay" style="' . esc_attr( $card_overlay_style ) . '"></div>' .
					'</div>';
			} else {
				$card_image_styles = array();

				// 'original' writes no aspect-ratio; the named ratios use
				// a spaced `16 / 9` form, which React renders verbatim.
				$card_ratio = isset( $attributes['imageAspectRatio'] ) ? (string) $attributes['imageAspectRatio'] : 'original';
				$named_ratios = array(
					'16-9' => '16 / 9',
					'4-3'  => '4 / 3',
					'1-1'  => '1 / 1',
				);
				if ( 'custom' === $card_ratio && ! empty( $attributes['imageCustomAspectRatio'] ) ) {
					$card_image_styles[] = 'aspect-ratio:' . (string) $attributes['imageCustomAspectRatio'];
				} elseif ( isset( $named_ratios[ $card_ratio ] ) ) {
					$card_image_styles[] = 'aspect-ratio:' . $named_ratios[ $card_ratio ];
				}

				$card_object_fit = isset( $attributes['imageObjectFit'] ) ? (string) $attributes['imageObjectFit'] : '';
				if ( '' !== $card_object_fit ) {
					$card_image_styles[] = 'object-fit:' . $card_object_fit;
				}

				// Focal point only applies to a cover fit, and is stored
				// 0..1 but written as a percentage.
				if ( 'cover' === $card_object_fit && isset( $attributes['imageFocalPoint']['x'], $attributes['imageFocalPoint']['y'] ) ) {
					$card_image_styles[] = 'object-position:' .
						Serializer_Support::format_js_number( (float) $attributes['imageFocalPoint']['x'] * 100 ) . '% ' .
						Serializer_Support::format_js_number( (float) $attributes['imageFocalPoint']['y'] * 100 ) . '%';
				}

				// A decorative image (no alt) is hidden from screen
				// readers and takes a translated fallback alt.
				$card_image_alt = isset( $attributes['imageAlt'] ) && is_string( $attributes['imageAlt'] ) ? $attributes['imageAlt'] : '';
				$card_aria      = '' === $card_image_alt ? ' aria-hidden="true"' : '';
				$card_alt_text  = '' === $card_image_alt ? __( 'Card image', 'designsetgo' ) : $card_image_alt;

				$card_image_html = '<div class="dsgo-card__image-wrapper"><img src="' . esc_url( $card_image_url ) .
					'" alt="' . esc_attr( $card_alt_text ) . '" class="dsgo-card__image"' .
					( empty( $card_image_styles ) ? '' : ' style="' . esc_attr( implode( ';', $card_image_styles ) ) . '"' ) .
					' loading="lazy"' . $card_aria . '/></div>';
			}
		}

		return array(
			'opening' => '<div class="' . esc_attr( $outer_class ) . '"' . $card_border . '>' . $card_background_html . '<div class="dsgo-card__inner">' . $card_image_html . '<div class="' . esc_attr( $content_class ) . '">' . $content_html . $cta_opening,
			'closing' => $cta_closing . '</div></div></div>',
		);
	}
}
