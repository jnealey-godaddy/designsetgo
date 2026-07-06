<?php
/**
 * Icon Block - Server-side Rendering
 *
 * Dynamic render. Emits the inline SVG (from the shared PHP icon library) inside
 * the same wrapper structure the block used before it became dynamic, so
 * existing content renders identically — but with no client-side icon injection
 * (no `.dsgo-lazy-icon` placeholder, no data-* attributes).
 *
 * @package DesignSetGo
 * @since 2.4.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused for dynamic blocks).
 * @param WP_Block $block      Block instance.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'designsetgo_render_icon' ) ) {
	/**
	 * Render the Icon block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content (unused).
	 * @param WP_Block $block      Block instance.
	 * @return string Icon markup.
	 */
	function designsetgo_render_icon( $attributes, $content, $block ) {
		unset( $content, $block );

		// Theme-level defaults (settings.custom.designsetgo.icon), used when an
		// attribute is left unset — mirrors the frontend injector's behavior.
		$defaults = array();
		if ( class_exists( '\\DesignSetGo\\Icon_Injector' ) ) {
			$defaults = \DesignSetGo\Icon_Injector::get_icon_defaults();
		}
		$default_size  = isset( $defaults['size'] ) ? (int) $defaults['size'] : 48;
		$default_style = isset( $defaults['style'] ) ? (string) $defaults['style'] : 'filled';

		$icon              = ! empty( $attributes['icon'] ) ? (string) $attributes['icon'] : 'star';
		$icon_style        = ! empty( $attributes['iconStyle'] ) ? (string) $attributes['iconStyle'] : $default_style;
		$stroke_width      = isset( $attributes['strokeWidth'] ) ? (float) $attributes['strokeWidth'] : 1.5;
		$has_explicit_size = isset( $attributes['iconSize'] );
		$icon_size         = $has_explicit_size ? (int) $attributes['iconSize'] : $default_size;
		$rotation          = isset( $attributes['rotation'] ) ? (int) $attributes['rotation'] : 0;
		$is_decorative     = ! empty( $attributes['isDecorative'] );
		$aria_label        = isset( $attributes['ariaLabel'] ) ? (string) $attributes['ariaLabel'] : '';

		// Trusted SVG markup from the shared library (filled, or outlined wrapper).
		$svg = designsetgo_render_icon_svg( $icon, $icon_style, $stroke_width );
		if ( '' === $svg ) {
			return '';
		}

		// Wrapper inline styles. Width/height are baked inline only when the author
		// set an explicit iconSize; left unset, sizing falls to the kit-tunable
		// `--wp--custom--designsetgo--icon--default-size` CSS var (see style.scss),
		// mirroring the pre-dynamic lazy save's hasExplicitSize behaviour so a Style
		// Kit / theme.json icon size is not silently overridden by an inline style.
		$wrapper_style = 'display:inline-flex;align-items:center;justify-content:center;border-radius:inherit;';
		if ( $has_explicit_size ) {
			$wrapper_style = sprintf( 'width:%1$dpx;height:%1$dpx;', $icon_size ) . $wrapper_style;
		}
		if ( 0 !== $rotation ) {
			$wrapper_style .= sprintf( 'transform:rotate(%ddeg);', $rotation );
		}

		// ARIA: decorative → hidden from AT; otherwise labelled with the custom
		// label or a humanized icon name (e.g. "circle-check" → "Circle Check").
		if ( $is_decorative ) {
			$aria = ' role="presentation" aria-hidden="true"';
		} else {
			$label = '' !== $aria_label ? $aria_label : ucwords( str_replace( '-', ' ', $icon ) );
			$aria  = sprintf( ' role="img" aria-label="%s"', esc_attr( $label ) );
		}

		$icon_html = sprintf(
			'<div class="dsgo-icon__wrapper" style="%s"%s>%s</div>',
			esc_attr( $wrapper_style ),
			$aria,
			$svg
		);

		// Optional link wrap (mirrors save.js: plain <a>, noopener on _blank).
		$url = ! empty( $attributes['linkUrl'] ) ? esc_url( $attributes['linkUrl'] ) : '';
		if ( '' !== $url ) {
			$target = isset( $attributes['linkTarget'] ) ? (string) $attributes['linkTarget'] : '_self';
			$rel    = isset( $attributes['linkRel'] ) ? (string) $attributes['linkRel'] : '';
			if ( '_blank' === $target && '' === $rel ) {
				$rel = 'noopener noreferrer';
			}
			$rel_attr  = '' !== $rel ? sprintf( ' rel="%s"', esc_attr( $rel ) ) : '';
			$icon_html = sprintf(
				'<a href="%s" target="%s"%s>%s</a>',
				$url,
				esc_attr( $target ),
				$rel_attr,
				$icon_html
			);
		}

		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class' => 'dsgo-icon',
				'style' => 'display:flex;align-items:center;justify-content:center;',
			)
		);

		return sprintf( '<div %s>%s</div>', $wrapper_attributes, $icon_html );
	}
}

// Output directly (WordPress captures echo'd output from render callbacks).
echo designsetgo_render_icon( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
