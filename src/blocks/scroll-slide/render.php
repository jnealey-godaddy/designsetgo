<?php
/**
 * Scroll Slide — server render.
 *
 * Standalone (authored) scroll-slides pass through unchanged. When iterated
 * inside a designsetgo/query (the post-spotlight variation), each per-post
 * render gets two enrichments so the template reads as a real story panel
 * instead of N identical copies:
 *
 *  - `data-dsgo-nav-heading` is set to the iterated post's title so the
 *    frontend nav column shows post titles instead of the template's
 *    static heading.
 *  - When the author has not set a custom background, the post's featured
 *    image is injected as `background-image` in the wrapper's inline style.
 *    The outer scroll-slides/view.js already lifts per-slide backgrounds
 *    into its crossfading layer, so the image becomes the panel background
 *    at scroll time.
 *
 * Detection: designsetgo_query_render_item() pushes an item context onto
 * $GLOBALS['designsetgo_parent_stack'] before rendering each iterated
 * block, so the presence of a postId on the top of that stack means we
 * are inside a query iteration.
 *
 * @package DesignSetGo
 * @since   2.6.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Pre-rendered save.js output.
 * @param WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $GLOBALS['designsetgo_parent_stack'] ) || ! is_array( $GLOBALS['designsetgo_parent_stack'] ) ) {
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered save.js output.
	return;
}

$top           = end( $GLOBALS['designsetgo_parent_stack'] );
$iterated_post = is_array( $top ) && isset( $top['postId'] ) ? (int) $top['postId'] : 0;

if ( ! $iterated_post || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered save.js output.
	return;
}

$iterated_title = get_the_title( $iterated_post );
$thumb          = get_the_post_thumbnail_url( $iterated_post, 'full' );

$processor = new WP_HTML_Tag_Processor( $content );
if ( ! $processor->next_tag( array( 'class_name' => 'dsgo-scroll-slide' ) ) ) {
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered save.js output.
	return;
}

if ( '' !== $iterated_title ) {
	$processor->set_attribute( 'data-dsgo-nav-heading', $iterated_title );
}

if ( $thumb ) {
	$existing_style = (string) $processor->get_attribute( 'style' );
	// Only inject if the author hasn't already set a background image via
	// block supports. Case-insensitive check on the property name catches
	// `background-image` and the `background:` shorthand alike.
	if ( false === stripos( $existing_style, 'background-image' ) && false === stripos( $existing_style, 'background:' ) ) {
		$injected = sprintf( "background-image:url('%s');background-size:cover;background-position:center;", esc_url( $thumb ) );
		$merged   = '' === trim( $existing_style )
			? $injected
			: rtrim( $existing_style, "; \t\n\r\0\x0B" ) . ';' . $injected;
		$processor->set_attribute( 'style', $merged );
	}
}

echo $processor->get_updated_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tag_Processor output.
