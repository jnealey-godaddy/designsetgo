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

		// The visible pill is the inner `.dsgo-pill__content` span, so the colour,
		// background and border inline styles are placed on the span here (the same
		// transfer the old static save() did). Everything else the style supports
		// produce — padding, margin, typography, dimensions — stays on the wrapper.
		//
		// Rather than re-parse the serialized `style="…"` string that
		// get_block_wrapper_attributes() produces (fragile: a `;` inside any future
		// value — a gradient, box-shadow, multi-value font-family — would split a
		// declaration mid-value, and a change to core's serialization format would
		// silently misroute styles), the two style strings are computed directly
		// from the structured `style` attribute via the Style Engine. Whole style
		// *groups* move as a unit — `color` (text/background/gradient), `background`
		// and `border` (incl. unlinked per-corner radius / per-side colour+width) —
		// so there is no per-property allowlist to fall out of date. Colour *classes*
		// (has-…-color) stay on the wrapper and reach the span via style.scss.
		$block_style = ( isset( $attributes['style'] ) && is_array( $attributes['style'] ) ) ? $attributes['style'] : array();
		$inner_keys  = array(
			'color'      => true,
			'background' => true,
			'border'     => true,
		);
		$inner_styles = wp_style_engine_get_styles( array_intersect_key( $block_style, $inner_keys ) );
		$keep_styles  = wp_style_engine_get_styles( array_diff_key( $block_style, $inner_keys ) );
		$inner_css    = isset( $inner_styles['css'] ) ? $inner_styles['css'] : '';
		$keep_css     = isset( $keep_styles['css'] ) ? $keep_styles['css'] : '';

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'dsgo-pill' ) );

		$html = sprintf(
			'<div %1$s><span class="dsgo-pill__content">%2$s</span></div>',
			$wrapper,
			wp_kses_post( $text )
		);

		// Overwrite the wrapper's colour/border-bearing style with only the kept
		// declarations and set the moved declarations on the span. WP_HTML_Tag_Processor
		// handles attribute quoting/escaping, so there is no string surgery.
		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag( 'div' );
		if ( '' !== $keep_css ) {
			$processor->set_attribute( 'style', $keep_css );
		} else {
			$processor->remove_attribute( 'style' );
		}
		if ( '' !== $inner_css ) {
			$processor->next_tag( 'span' );
			$processor->set_attribute( 'style', $inner_css );
		}

		return $processor->get_updated_html();
	}
}

// Output directly (WordPress captures echo'd output from render callbacks).
echo designsetgo_render_pill( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
