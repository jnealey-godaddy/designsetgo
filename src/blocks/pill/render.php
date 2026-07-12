<?php
/**
 * Pill Block - Server-side Rendering
 *
 * Dynamic render. The block serializes to a single self-closing comment (no
 * stored HTML), so a fresh pill never bakes `has-small-font-size` into the
 * database — that class only appears when the author explicitly sets a font
 * size. The default inherited-text-size look is CSS (style.scss), not a baked
 * attribute.
 *
 * The block root (`.dsgo-pill`) is a plain block-level positioning wrapper —
 * core's constrained layout caps it at the theme's content width, exactly like
 * a paragraph. The visible pill is the inner `.dsgo-pill__content` span, which
 * shrink-wraps and is positioned inside the wrapper via `justify-content`
 * (`.dsgo-justify`, driven by the `justification` attribute). All visual block
 * supports (color, background, border, padding) are routed from the wrapper to
 * that span by the shared `designsetgo_route_visual_supports()` helper —
 * otherwise a background or border would paint across the whole content column
 * instead of hugging the pill.
 *
 * `align` support was removed entirely when `justification` replaced it, but
 * block.json still registers an explicit `align` attribute (attributes
 * registered directly are kept regardless of `supports`) purely as a
 * migration shim: deprecations only run in the editor, so a published pill
 * that has never been re-opened and re-saved still has `align` in its stored
 * comment and NO `justification` key. Without this shim, `align` would be
 * silently stripped before render.php ever sees it (WordPress drops
 * attributes the block type doesn't register) and the pill would render at
 * `justification`'s default ("center"), moving existing left/right pills.
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

		// A stored `align` key only ever exists on un-migrated legacy content
		// (the deprecation strips it on first editor re-save, and a fresh
		// pill never writes one) — so its presence, not `justification`'s
		// value, is what identifies "not yet migrated" here. `justification`
		// always reads back as a value (its own block.json default fills in
		// when the key is missing), so it cannot be used to detect that.
		$legacy_align = isset( $attributes['align'] ) ? (string) $attributes['align'] : '';
		$justification = in_array( $legacy_align, array( 'left', 'center', 'right' ), true )
			? $legacy_align
			: ( isset( $attributes['justification'] ) ? (string) $attributes['justification'] : 'center' );

		$justify_class = in_array( $justification, array( 'left', 'center', 'right' ), true )
			? ' dsgo-justify--' . $justification
			: '';

		$wrapper = get_block_wrapper_attributes(
			array( 'class' => 'dsgo-pill dsgo-justify' . $justify_class )
		);

		$html = sprintf(
			'<div %1$s><span class="dsgo-pill__content">%2$s</span></div>',
			$wrapper,
			wp_kses_post( $text )
		);

		// Colour, background, border and padding paint the visible pill, not the
		// full-column-width positioning wrapper. Margin and typography stay put.
		// 'background' is not a separate entry: the pill has no distinct WP
		// background-image support, so backgrounds live under 'color'
		// (style.color.background) and 'color' already routes them.
		return designsetgo_route_visual_supports(
			$html,
			$attributes,
			'dsgo-pill__content',
			array( 'color', 'border', 'spacing.padding' )
		);
	}
}

// Output directly (WordPress captures echo'd output from render callbacks).
echo designsetgo_render_pill( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
