<?php
/**
 * Scroll Slides — server render.
 *
 * Two modes:
 *
 * 1. Authored (no query context): echoes $content unchanged so the static
 *    save.js output passes through untouched.
 *
 * 2. Query-bound (parent designsetgo/query provides `designsetgo/queryId`):
 *    rebuilds the scroll-slides chrome in PHP (mirroring save.js) and
 *    injects the pre-rendered items from the parent container's stash.
 *    Each item is a full designsetgo/scroll-slide render with per-post
 *    context; this file just wraps them in the panels/inner/outer divs.
 *
 * NOTE: the chrome below MUST stay in sync with src/blocks/scroll-slides/save.js.
 * If save.js gains a new wrapper or attribute, mirror it here too.
 *
 * @package DesignSetGo
 * @since   2.6.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Pre-rendered save.js output.
 * @param WP_Block $block      Block instance (carries context).
 */

defined( 'ABSPATH' ) || exit;

$query_id = '';
if ( isset( $block->context['designsetgo/queryId'] ) ) {
	$query_id = sanitize_key( (string) $block->context['designsetgo/queryId'] );
}

// Authored mode — pass through unchanged. block.json `render` captures the
// output buffer so we ECHO rather than return.
if ( '' === $query_id ) {
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered save.js output.
	return;
}

// Query mode — pull pre-rendered items from the parent container's stash.
// Inner blocks render before their parent's render_callback fires, so we
// can't rely on the query container having loaded the shared helpers yet.
$helpers = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
if ( ! file_exists( $helpers ) ) {
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered save.js output.
	return;
}
require_once $helpers;

$items_html = '';
if ( isset( $GLOBALS['designsetgo_query_items_html'][ $query_id ] ) ) {
	$items_html = (string) $GLOBALS['designsetgo_query_items_html'][ $query_id ];
}

$atts = wp_parse_args(
	$attributes,
	array(
		'minHeight'      => '100vh',
		'maxHeight'      => '900px',
		'constrainWidth' => true,
		'contentWidth'   => '',
		'overlayColor'   => '',
		'navColor'       => '',
		'navActiveColor' => '',
	)
);

// Minimal parity with save.js's convertColorToCSSVar(): convert the WP preset
// shorthand (`var:preset|color|slug`) to a real `var(...)`, otherwise pass
// through as-is (hex/rgb/CSS keywords already work inline).
//
// Both branches run the final value through designsetgo_safe_css_value() so a
// stored color like `red; content:'x'` or a crafted slug like `slug);}` can't
// escape the `--prop: VALUE` declaration. Preset slug segments are also
// constrained via sanitize_html_class() so only identifier characters survive.
$color_to_css = static function ( $value ) {
	$value = (string) $value;
	if ( '' === $value ) {
		return '';
	}
	if ( 0 === strpos( $value, 'var:preset|' ) ) {
		$parts  = explode( '|', substr( $value, strlen( 'var:preset|' ) ) );
		$parts  = array_filter( array_map( 'sanitize_html_class', $parts ) );
		$result = 'var(--wp--preset--' . implode( '--', $parts ) . ')';
		return designsetgo_safe_css_value( $result );
	}
	return designsetgo_safe_css_value( $value );
};

$classes = array( 'dsgo-scroll-slides' );
if ( '' !== $atts['overlayColor'] ) {
	$classes[] = 'dsgo-scroll-slides--has-overlay';
}
if ( empty( $atts['constrainWidth'] ) ) {
	$classes[] = 'dsgo-scroll-slides--no-width-constraint';
}
if ( '' !== $atts['navColor'] || '' !== $atts['navActiveColor'] ) {
	$classes[] = 'dsgo-scroll-slides--has-nav-color';
}

$style_parts = array();
if ( '' !== $atts['overlayColor'] ) {
	$style_parts[] = '--dsgo-overlay-color:' . $color_to_css( $atts['overlayColor'] );
	$style_parts[] = '--dsgo-overlay-opacity:0.8';
}
if ( '' !== $atts['navColor'] ) {
	$style_parts[] = '--dsgo-nav-color:' . $color_to_css( $atts['navColor'] );
}
if ( '' !== $atts['navActiveColor'] ) {
	$style_parts[] = '--dsgo-nav-active-color:' . $color_to_css( $atts['navActiveColor'] );
}
$style_inline = implode( ';', $style_parts );
if ( '' !== $style_inline ) {
	$style_inline .= ';';
}

$min_height = designsetgo_safe_css_value( $atts['minHeight'] );
if ( '' === $min_height ) {
	$min_height = '100vh';
}
$wrapper_attrs_list = array(
	'class'                => implode( ' ', $classes ),
	'data-dsgo-min-height' => $min_height,
);
if ( '' !== $atts['maxHeight'] ) {
	$max_h = designsetgo_safe_css_value( $atts['maxHeight'] );
	if ( '' !== $max_h ) {
		$wrapper_attrs_list['data-dsgo-max-height'] = $max_h;
	}
}
if ( '' !== $style_inline ) {
	$wrapper_attrs_list['style'] = $style_inline;
}

$wrapper_attrs = get_block_wrapper_attributes( $wrapper_attrs_list );

$inner_style = '';
if ( ! empty( $atts['constrainWidth'] ) ) {
	$max_width = $atts['contentWidth'] !== ''
		? designsetgo_safe_css_value( $atts['contentWidth'] )
		: 'var(--wp--style--global--content-size, 1140px)';
	if ( '' === $max_width ) {
		$max_width = 'var(--wp--style--global--content-size, 1140px)';
	}
	$inner_style = ' style="max-width:' . esc_attr( $max_width ) . ';margin-left:auto;margin-right:auto"';
}

printf(
	'<div %1$s><div class="dsgo-scroll-slides__inner"%2$s><div class="dsgo-scroll-slides__panels">%3$s</div></div></div>',
	$wrapper_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via get_block_wrapper_attributes().
	$inner_style,   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_attr().
	$items_html     // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- items rendered via WP_Block (WP escapes server-side).
);
