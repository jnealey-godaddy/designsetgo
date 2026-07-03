<?php
/**
 * Divider Block - Server-side Rendering
 *
 * Dynamic render. Non-icon dividers are pure CSS lines; the "icon" style embeds
 * the inline SVG from the shared PHP icon library (no client-side injection, no
 * `.dsgo-lazy-icon` placeholder). Reproduces the prior static output for parity.
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

if ( ! function_exists( 'designsetgo_render_divider' ) ) {
	/**
	 * Render the Divider block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content (unused).
	 * @param WP_Block $block      Block instance.
	 * @return string Divider markup.
	 */
	function designsetgo_render_divider( $attributes, $content, $block ) {
		unset( $content, $block );

		$divider_style = isset( $attributes['dividerStyle'] ) ? (string) $attributes['dividerStyle'] : 'solid';
		$width         = isset( $attributes['width'] ) ? (float) $attributes['width'] : 100;
		$thickness     = isset( $attributes['thickness'] ) ? (int) $attributes['thickness'] : 2;

		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class' => 'dsgo-divider dsgo-divider--' . sanitize_html_class( $divider_style ),
			)
		);

		$container_style = sprintf( 'width:%s%%;', $width );
		$line_style      = sprintf( 'height:%dpx;', $thickness );

		if ( 'icon' === $divider_style ) {
			$defaults     = class_exists( '\\DesignSetGo\\Icon_Injector' ) ? \DesignSetGo\Icon_Injector::get_icon_defaults() : array();
			$icon_name    = ! empty( $attributes['iconName'] ) ? (string) $attributes['iconName'] : 'star';
			$icon_style   = ! empty( $attributes['iconStyle'] ) ? (string) $attributes['iconStyle'] : ( isset( $defaults['style'] ) ? (string) $defaults['style'] : 'filled' );
			$stroke_width = isset( $attributes['strokeWidth'] ) ? (float) $attributes['strokeWidth'] : 1.5;
			$svg          = designsetgo_render_icon_svg( $icon_name, $icon_style, $stroke_width );

			$inner = sprintf(
				'<div class="dsgo-divider__icon-wrapper"><span class="dsgo-divider__line dsgo-divider__line--left" style="%1$s"></span><span class="dsgo-divider__icon">%2$s</span><span class="dsgo-divider__line dsgo-divider__line--right" style="%1$s"></span></div>',
				esc_attr( $line_style ),
				$svg
			);
		} else {
			$inner = sprintf( '<div class="dsgo-divider__line" style="%s"></div>', esc_attr( $line_style ) );
		}

		return sprintf(
			'<div %1$s><div class="dsgo-divider__container" style="%2$s">%3$s</div></div>',
			$wrapper_attributes,
			esc_attr( $container_style ),
			$inner
		);
	}
}

// Output directly (WordPress captures echo'd output from render callbacks).
echo designsetgo_render_divider( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
