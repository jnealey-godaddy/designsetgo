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

		// The visible pill is the inner `.dsgo-pill__content` span, so the colour,
		// background and border inline styles that get_block_wrapper_attributes()
		// places on the wrapper are moved onto the span here (the same transfer the
		// old static save() did). Everything else — padding, margin, typography —
		// stays on the wrapper.
		//
		// A declaration moves when its property is `color` or belongs to the
		// `background` / `border` groups (prefix match, not an exact-name list), so
		// unlinked per-corner radius (`border-top-left-radius`) and per-side borders
		// (`border-top-color`, …) move too — an exact-name list silently left those
		// on the wrapper, where they never reached the visible span.
		//
		// This reads/rewrites the wrapper's `style` as a string, which couples to
		// get_block_wrapper_attributes()'s output: double-quoted and `esc_attr()`-
		// escaped (so any `"` in a value is `&quot;`, never a bare quote that would
		// break the `[^"]*` capture) and `;`-joined. Splitting on `;` is safe for
		// this block because none of its enabled supports (colour, gradient, border;
		// no background-image) can produce a value containing a literal `;`. Revisit
		// if background-image — or any other `;`-bearing value — is ever supported.
		$inner_decls = array();
		$keep_decls  = array();

		if ( preg_match( '/style="([^"]*)"/', $wrapper, $style_match ) ) {
			foreach ( explode( ';', $style_match[1] ) as $declaration ) {
				$declaration = trim( $declaration );
				if ( '' === $declaration ) {
					continue;
				}
				$property = strtolower( trim( strtok( $declaration, ':' ) ) );
				$move     = 0 === strpos( $property, 'color' )
					|| 0 === strpos( $property, 'background' )
					|| 0 === strpos( $property, 'border' );
				if ( $move ) {
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
