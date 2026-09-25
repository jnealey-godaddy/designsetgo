<?php
/**
 * Serializer for designsetgo/modal.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/modal
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

use DesignSetGo\Abilities\Block_Schema_Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modal_Serializer.
 */
class Modal_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$modal_id                = isset( $attributes['modalId'] ) ? $attributes['modalId'] : 'dsgo-modal-' . wp_generate_uuid4();
		$animation_type          = isset( $attributes['animationType'] ) ? $attributes['animationType'] : 'fade';
		$animation_duration      = isset( $attributes['animationDuration'] ) ? Serializer_Support::numeric_attribute( $attributes['animationDuration'] ) : 300;
		$close_on_backdrop       = isset( $attributes['closeOnBackdrop'] ) ? $attributes['closeOnBackdrop'] : true;
		$close_on_esc            = isset( $attributes['closeOnEsc'] ) ? $attributes['closeOnEsc'] : true;
		$disable_body_scroll     = isset( $attributes['disableBodyScroll'] ) ? $attributes['disableBodyScroll'] : true;
		$allow_hash_trigger      = isset( $attributes['allowHashTrigger'] ) ? $attributes['allowHashTrigger'] : true;
		$update_url_on_open      = isset( $attributes['updateUrlOnOpen'] ) ? $attributes['updateUrlOnOpen'] : false;
		$auto_trigger_type       = isset( $attributes['autoTriggerType'] ) ? $attributes['autoTriggerType'] : 'none';
		$auto_trigger_delay      = isset( $attributes['autoTriggerDelay'] ) ? Serializer_Support::numeric_attribute( $attributes['autoTriggerDelay'] ) : 0;
		$auto_trigger_frequency  = isset( $attributes['autoTriggerFrequency'] ) ? $attributes['autoTriggerFrequency'] : 'always';
		$cookie_duration         = isset( $attributes['cookieDuration'] ) ? Serializer_Support::numeric_attribute( $attributes['cookieDuration'] ) : 7;
		$exit_intent_sensitivity = isset( $attributes['exitIntentSensitivity'] ) ? $attributes['exitIntentSensitivity'] : 'medium';
		$exit_intent_min_time    = isset( $attributes['exitIntentMinTime'] ) ? Serializer_Support::numeric_attribute( $attributes['exitIntentMinTime'] ) : 5;
		$exit_intent_exclude_mob = isset( $attributes['exitIntentExcludeMobile'] ) ? $attributes['exitIntentExcludeMobile'] : true;
		$scroll_depth            = isset( $attributes['scrollDepth'] ) ? Serializer_Support::numeric_attribute( $attributes['scrollDepth'] ) : 50;
		$scroll_direction        = isset( $attributes['scrollDirection'] ) ? $attributes['scrollDirection'] : 'down';
		$time_on_page            = isset( $attributes['timeOnPage'] ) ? Serializer_Support::numeric_attribute( $attributes['timeOnPage'] ) : 30;
		$gallery_group_id        = isset( $attributes['galleryGroupId'] ) ? $attributes['galleryGroupId'] : '';
		$gallery_index           = isset( $attributes['galleryIndex'] ) ? Serializer_Support::numeric_attribute( $attributes['galleryIndex'] ) : 0;
		$show_gallery_nav        = isset( $attributes['showGalleryNavigation'] ) ? $attributes['showGalleryNavigation'] : true;
		$nav_style               = isset( $attributes['navigationStyle'] ) ? $attributes['navigationStyle'] : 'arrows';
		$nav_position            = isset( $attributes['navigationPosition'] ) ? $attributes['navigationPosition'] : 'sides';
		$width                   = isset( $attributes['width'] ) ? $attributes['width'] : '600px';
		$max_width               = isset( $attributes['maxWidth'] ) ? $attributes['maxWidth'] : '90vw';
		$display_mode            = isset( $attributes['displayMode'] ) ? $attributes['displayMode'] : 'dialog';
		$panel_edge              = isset( $attributes['panelEdge'] ) ? $attributes['panelEdge'] : 'right';
		// Mirror save.js: clamp to a known edge, or the emitted class
		// matches no CSS rule and the panel floats mid-viewport.
		if ( ! in_array( $panel_edge, array( 'left', 'right', 'top', 'bottom' ), true ) ) {
			$panel_edge = 'right';
		}
		$panel_size = isset( $attributes['panelSize'] ) ? (string) $attributes['panelSize'] : '24rem';
		// Mirror save.js: allow-list a single plain CSS length. This is
		// interpolated into `--dsgo-panel-size:<value>`, and esc_attr()
		// stops an attribute break-out but not a `;` that appends
		// further declarations to the modal root.
		if ( ! preg_match( '/^(0|\d+(\.\d+)?(px|rem|em|%|vw|vh|vmin|vmax|ch|ex|pt|pc|cm|mm|in))$/', $panel_size ) ) {
			$panel_size = '24rem';
		}
		$is_panel              = 'panel' === $display_mode;
		$overlay_color         = isset( $attributes['overlayColor'] ) ? trim( (string) $attributes['overlayColor'] ) : '';
		$overlay_opacity       = isset( $attributes['overlayOpacity'] ) ? floatval( $attributes['overlayOpacity'] ) : 80;
		$overlay_blur          = isset( $attributes['overlayBlur'] ) ? Serializer_Support::numeric_attribute( $attributes['overlayBlur'] ) : 0;
		$show_close_button     = isset( $attributes['showCloseButton'] ) ? $attributes['showCloseButton'] : true;
		$close_button_position = isset( $attributes['closeButtonPosition'] ) ? $attributes['closeButtonPosition'] : 'inside-top-right';
		$close_button_size     = isset( $attributes['closeButtonSize'] ) ? Serializer_Support::numeric_attribute( $attributes['closeButtonSize'] ) : 24;

		// Build data attributes.
		$data_attrs  = ' data-dsgo-modal="true"';
		$data_attrs .= ' data-modal-id="' . esc_attr( $modal_id ) . '"';
		$data_attrs .= ' data-animation-type="' . esc_attr( $animation_type ) . '"';
		$data_attrs .= ' data-animation-duration="' . esc_attr( (string) $animation_duration ) . '"';
		$data_attrs .= ' data-close-on-backdrop="' . ( $close_on_backdrop ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-close-on-esc="' . ( $close_on_esc ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-disable-body-scroll="' . ( $disable_body_scroll ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-allow-hash-trigger="' . ( $allow_hash_trigger ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-update-url-on-open="' . ( $update_url_on_open ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-auto-trigger-type="' . esc_attr( $auto_trigger_type ) . '"';
		$data_attrs .= ' data-auto-trigger-delay="' . esc_attr( (string) $auto_trigger_delay ) . '"';
		$data_attrs .= ' data-auto-trigger-frequency="' . esc_attr( $auto_trigger_frequency ) . '"';
		$data_attrs .= ' data-cookie-duration="' . esc_attr( (string) $cookie_duration ) . '"';
		$data_attrs .= ' data-exit-intent-sensitivity="' . esc_attr( (string) $exit_intent_sensitivity ) . '"';
		$data_attrs .= ' data-exit-intent-min-time="' . esc_attr( (string) $exit_intent_min_time ) . '"';
		$data_attrs .= ' data-exit-intent-exclude-mobile="' . ( $exit_intent_exclude_mob ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-scroll-depth="' . esc_attr( (string) $scroll_depth ) . '"';
		$data_attrs .= ' data-scroll-direction="' . esc_attr( $scroll_direction ) . '"';
		$data_attrs .= ' data-time-on-page="' . esc_attr( (string) $time_on_page ) . '"';
		$data_attrs .= ' data-gallery-group-id="' . esc_attr( $gallery_group_id ) . '"';
		$data_attrs .= ' data-gallery-index="' . esc_attr( (string) $gallery_index ) . '"';
		$data_attrs .= ' data-show-gallery-navigation="' . ( $show_gallery_nav ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-navigation-style="' . esc_attr( $nav_style ) . '"';
		$data_attrs .= ' data-navigation-position="' . esc_attr( $nav_position ) . '"';

		// Overlay styles. save.js writes background-color ONLY when the
		// author set overlayColor explicitly (hasExplicitString) — left
		// unset, the stylesheet default owns the scrim
		// (--wp--custom--designsetgo--modal--overlay-color → #000) — so
		// mirror that here, and the property order (background-color,
		// opacity, backdrop-filter), or the block fails validation on
		// first edit.
		$overlay_style = '';
		if ( '' !== $overlay_color ) {
			$overlay_style .= 'background-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( $overlay_color ) ) . ';';
		}
		$overlay_style .= 'opacity:' . ( $overlay_opacity / 100 );
		if ( $overlay_blur > 0 ) {
			$overlay_style .= ';backdrop-filter:blur(' . $overlay_blur . 'px)';
		}

		// Content styles. In panel mode save.js passes no dimensions to
		// transferStylesToContent(), because the panel is sized by
		// panelSize on the dialog — so no width/max-width is written.
		//
		// Border comes from the `style` ATTRIBUTE, not from a literal.
		// modal's block.json defaults `style` to a none/0px border, so
		// hardcoding it reproduced the default and then survived a caller
		// replacing `style` with something else entirely - writing a
		// border save() no longer emits.
		//
		// It cannot come from the style engine either: modal declares no
		// `border` support, so apply_block_support_attributes() correctly
		// serializes nothing for it, while JavaScript's
		// useBlockProps.save() writes style.border through regardless of
		// the support. Padding does come from the engine (spacing.padding
		// IS supported), which is why only border is handled here.
		$content_style_parts = array();

		// WordPress re-registers the support-backed `style` attribute as a
		// bare object on the PHP side and drops block.json's default, so
		// the border has to be read back from block.json when the caller
		// supplied no style of their own. The Section case does the same
		// for its page padding. Only when the caller supplied NOTHING: an
		// attribute default is replaced wholesale, not deep-merged, so a
		// caller-supplied style legitimately has no border.
		$modal_style = $attributes['style'] ?? null;
		if ( ! is_array( $modal_style ) ) {
			$declared_modal_style = Block_Schema_Loader::get_block_json( $block_name )['attributes']['style']['default'] ?? null;
			$modal_style          = is_array( $declared_modal_style ) ? $declared_modal_style : array();
		}

		$modal_border = isset( $modal_style['border'] ) && is_array( $modal_style['border'] )
			? $modal_style['border']
			: array();
		if ( isset( $modal_border['style'] ) && is_string( $modal_border['style'] ) ) {
			$content_style_parts[] = 'border-style:' . esc_attr( $modal_border['style'] );
		}
		if ( isset( $modal_border['width'] ) && is_string( $modal_border['width'] ) ) {
			$content_style_parts[] = 'border-width:' . esc_attr( $modal_border['width'] );
		}
		if ( ! $is_panel ) {
			$content_style_parts[] = 'width:' . esc_attr( $width );
			$content_style_parts[] = 'max-width:' . esc_attr( $max_width );

			// transferStylesToContent() drops BOTH height and max-height
			// when height is 'auto', so an auto-height modal writes
			// neither.
			$modal_height     = isset( $attributes['height'] ) ? (string) $attributes['height'] : 'auto';
			$modal_max_height = isset( $attributes['maxHeight'] ) ? (string) $attributes['maxHeight'] : '90vh';
			if ( 'auto' !== $modal_height ) {
				$content_style_parts[] = 'height:' . esc_attr( $modal_height );
				$content_style_parts[] = 'max-height:' . esc_attr( $modal_max_height );
			}
		}
		$content_style = implode( ';', $content_style_parts );

		// Close button HTML.
		$close_button_html = '';
		if ( $show_close_button ) {
			// save.js writes colour and background-color after the size,
			// and omits either when its converted value is empty (React
			// drops an `undefined` style property). Both were missing
			// here, so an author's close-button colours never reached
			// stored markup.
			$close_button_style = 'width:' . $close_button_size . 'px;height:' . $close_button_size . 'px';

			$close_icon_color = isset( $attributes['closeButtonIconColor'] )
				? Serializer_Support::convert_color_value_to_css_var( (string) $attributes['closeButtonIconColor'] )
				: '';
			if ( '' !== $close_icon_color ) {
				$close_button_style .= ';color:' . $close_icon_color;
			}

			$close_bg_color = isset( $attributes['closeButtonBgColor'] )
				? Serializer_Support::convert_color_value_to_css_var( (string) $attributes['closeButtonBgColor'] )
				: '';
			if ( '' !== $close_bg_color ) {
				$close_button_style .= ';background-color:' . $close_bg_color;
			}

			// save.js falls back to the translated default only when the
			// label is blank after trimming; this was hardcoded, so a
			// custom label was silently discarded.
			$close_button_label = isset( $attributes['closeButtonLabel'] ) && is_string( $attributes['closeButtonLabel'] )
				? trim( $attributes['closeButtonLabel'] )
				: '';
			// Then the label read back from the stored markup, as save.js does.
			if ( '' === $close_button_label && isset( $attributes['savedCloseButtonLabel'] ) && is_string( $attributes['savedCloseButtonLabel'] ) ) {
				$close_button_label = $attributes['savedCloseButtonLabel'];
			}
			if ( '' === $close_button_label ) {
				$close_button_label = __( 'Close modal', 'designsetgo' );
			}

			$close_button_html  = '<button class="dsgo-modal__close dsgo-modal__close--' . esc_attr( $close_button_position ) . '" style="' . esc_attr( $close_button_style ) . '" type="button" aria-label="' . esc_attr( $close_button_label ) . '">';
			$close_button_html .= '<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">';
			$close_button_html .= '<path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>';
			$close_button_html .= '</svg></button>';
		}

		$close_button_is_inside = strpos( $close_button_position, 'inside-' ) === 0;

		// Off-canvas panel mode. save() appends these to the root class
		// list and writes --dsgo-panel-size as a style AFTER the class
		// attribute; mirror both exactly or the block fails validation
		// on first edit.
		$outer_class = 'wp-block-designsetgo-modal dsgo-modal';
		$panel_style = '';
		if ( $is_panel ) {
			$outer_class .= ' dsgo-modal--panel dsgo-modal--panel-' . $panel_edge;
			$panel_style  = ' style="' . esc_attr( '--dsgo-panel-size:' . $panel_size ) . '"';
		}

		$inner_html  = '<div class="dsgo-modal__backdrop" style="' . esc_attr( $overlay_style ) . '" aria-hidden="true"></div>';
		$inner_html .= '<div class="dsgo-modal__dialog">';
		if ( ! $close_button_is_inside ) {
			$inner_html .= $close_button_html;
		}
		// save.js: modalLabel?.trim() || savedModalLabel || __( 'Modal' ).
		$modal_label = isset( $attributes['modalLabel'] ) && '' !== trim( (string) $attributes['modalLabel'] )
			? trim( (string) $attributes['modalLabel'] )
			: '';
		if ( '' === $modal_label && isset( $attributes['savedModalLabel'] ) && is_string( $attributes['savedModalLabel'] ) ) {
			$modal_label = $attributes['savedModalLabel'];
		}
		if ( '' === $modal_label ) {
			$modal_label = __( 'Modal', 'designsetgo' );
		}

		$inner_html .= '<div class="dsgo-modal__content" style="' . esc_attr( $content_style ) . '">';

		$closing_html = '';
		if ( $close_button_is_inside ) {
			$closing_html .= $close_button_html;
		}
		$closing_html .= '</div></div></div>';

		// save.js omits the id attribute while modalId is blank
		// (React would render id=""), so mirror that here too.
		$id_attr = '' !== $modal_id ? ' id="' . esc_attr( $modal_id ) . '"' : '';

		return array(
			'opening' => '<div' . $id_attr . ' role="dialog" aria-modal="true" aria-label="' . esc_attr( $modal_label ) . '" aria-hidden="true"' . $data_attrs . ' class="' . esc_attr( $outer_class ) . '"' . $panel_style . '>' . $inner_html,
			'closing' => $closing_html,
		);
	}
}
