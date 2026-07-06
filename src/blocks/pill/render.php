<?php
/**
 * Pill Block - Server-side Rendering
 *
 * Dynamic render. The block serializes to a single self-closing comment (no
 * stored HTML), so a fresh pill never bakes `aligncenter` / `has-small-font-size`
 * into the database — those classes only appear when the author explicitly sets
 * an alignment or font size. The default centred, inherited-text look is CSS
 * (style.scss), not baked attributes.
 *
 * The visible pill is the inner `.dsgo-pill__content` span; the outer `.dsgo-pill`
 * div is the alignment container. So the colour/background/border inline styles
 * that `get_block_wrapper_attributes()` puts on the wrapper are moved to the inner
 * span here — the same transfer the old static save() did in JS. Colour *classes*
 * stay on the wrapper and are transferred to the span by CSS (style.scss).
 *
 * @package DesignSetGo
 * @since 2.5.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused for dynamic blocks).
 * @param WP_Block $block      Block instance.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'designsetgo_render_pill' ) ) {
	/**
	 * Render the Pill block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content (unused).
	 * @param WP_Block $block      Block instance.
	 * @return string Pill markup.
	 */
	function designsetgo_render_pill( $attributes, $content, $block ) {
		unset( $content, $block );

		$text = isset( $attributes['content'] ) ? (string) $attributes['content'] : '';

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'dsgo-pill' ) );

		// Inline declarations WordPress placed on the wrapper. Colour/background/
		// border ones belong on the inner span (the visible pill); everything else
		// (padding, margin, typography) stays on the wrapper — matching the old
		// static save() which only moved colour + border.
		$move_props  = array(
			'color',
			'background-color',
			'background',
			'border-color',
			'border-width',
			'border-style',
			'border-radius',
		);
		$inner_decls = array();
		$keep_decls  = array();

		if ( preg_match( '/style="([^"]*)"/', $wrapper, $style_match ) ) {
			foreach ( explode( ';', $style_match[1] ) as $declaration ) {
				$declaration = trim( $declaration );
				if ( '' === $declaration ) {
					continue;
				}
				$property = trim( strtok( $declaration, ':' ) );
				if ( in_array( $property, $move_props, true ) ) {
					$inner_decls[] = $declaration;
				} else {
					$keep_decls[] = $declaration;
				}
			}

			// Rebuild the wrapper's style attribute with only the kept declarations
			// (values are already escaped by get_block_wrapper_attributes()).
			$keep_style  = implode( ';', $keep_decls );
			$replacement = '' !== $keep_style ? 'style="' . $keep_style . '"' : '';
			$wrapper     = preg_replace( '/style="[^"]*"/', $replacement, $wrapper );
		}

		$inner_style = implode( ';', $inner_decls );
		// Values originate from get_block_wrapper_attributes(), already escaped.
		$inner_style_attr = '' !== $inner_style ? ' style="' . $inner_style . '"' : '';

		return sprintf(
			'<div %1$s><span class="dsgo-pill__content"%2$s>%3$s</span></div>',
			trim( $wrapper ),
			$inner_style_attr,
			wp_kses_post( $text )
		);
	}
}

// Output directly (WordPress captures echo'd output from render callbacks).
echo designsetgo_render_pill( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
