<?php
/**
 * Slider — server render.
 *
 * Two modes:
 *
 * 1. Authored (no query context): returns $content unchanged so the static
 *    save.js output passes through untouched.
 *
 * 2. Query-bound (parent designsetgo/query provides `designsetgo/queryId`):
 *    rebuilds the slider chrome in PHP (mirroring save.js) and injects the
 *    pre-rendered items from $GLOBALS['designsetgo_query_items_html']. The
 *    parent query container iterates once and stashes raw item HTML (each
 *    item is a full designsetgo/slide render with per-post context), so the
 *    slider here just wraps that HTML in the track/viewport/outer divs.
 *
 * NOTE: the chrome below MUST stay in sync with src/blocks/slider/save.js.
 * If save.js gains a new wrapper or attribute, mirror it here too — otherwise
 * query-mode output diverges visually from authored-mode output.
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

// Authored mode — slider behaves exactly as it did before the query integration.
// block.json `render` wires this file as a render_callback that captures its
// output buffer (require + ob_get_clean). So we ECHO rather than return.
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
		'slidesPerView'         => 1,
		'slidesPerViewTablet'   => 1,
		'slidesPerViewMobile'   => 1,
		'height'                => '',
		'aspectRatio'           => '16/9',
		'useAspectRatio'        => false,
		'gap'                   => '20px',
		'showArrows'            => true,
		'showDots'              => true,
		'arrowStyle'            => 'default',
		'arrowPosition'         => 'sides',
		'arrowVerticalPosition' => 'center',
		'arrowColor'            => '',
		'arrowBackgroundColor'  => '',
		'arrowSize'             => '24px',
		'arrowPadding'          => '',
		'dotStyle'              => 'default',
		'dotPosition'           => 'inside',
		'dotColor'              => '',
		'effect'                => 'slide',
		'transitionDuration'    => '0.5s',
		'transitionEasing'      => 'ease-in-out',
		'autoplay'              => false,
		'autoplayInterval'      => 3000,
		'pauseOnHover'          => true,
		'pauseOnInteraction'    => true,
		'loop'                  => true,
		'draggable'             => true,
		'swipeable'             => true,
		'freeMode'              => false,
		'centeredSlides'        => false,
		'mobileBreakpoint'      => 768,
		'tabletBreakpoint'      => 1024,
		'activeSlide'           => 0,
		'styleVariation'        => 'classic',
		'ariaLabel'             => '',
		'scrollDriven'          => false,
		'scrollDrivenSpeed'     => 1,
	)
);

// Effects that force a single slide per view (mirrors SINGLE_SLIDE_EFFECTS in save.js).
$single_effects = array( 'fade', 'zoom' );
$forces_single  = in_array( $atts['effect'], $single_effects, true );

$spv        = $forces_single ? 1 : (int) $atts['slidesPerView'];
$spv_tablet = $forces_single ? 1 : (int) $atts['slidesPerViewTablet'];
$spv_mobile = $forces_single ? 1 : (int) $atts['slidesPerViewMobile'];

// Build class list. Order matches save.js for snapshot-friendly diffs.
$classes = array( 'dsgo-slider' );
if ( ! empty( $atts['styleVariation'] ) ) {
	$classes[] = 'dsgo-slider--' . sanitize_html_class( (string) $atts['styleVariation'] );
}
if ( ! empty( $atts['effect'] ) ) {
	$classes[] = 'dsgo-slider--effect-' . sanitize_html_class( (string) $atts['effect'] );
}
if ( $atts['showArrows'] ) {
	$classes[] = 'dsgo-slider--has-arrows';
}
if ( $atts['showDots'] ) {
	$classes[] = 'dsgo-slider--has-dots';
}
if ( $atts['centeredSlides'] ) {
	$classes[] = 'dsgo-slider--centered';
}
if ( $atts['freeMode'] ) {
	$classes[] = 'dsgo-slider--free-mode';
}
if ( $atts['scrollDriven'] ) {
	$classes[] = 'dsgo-slider--scroll-driven';
}

// CSS custom properties. Each attribute passes through designsetgo_safe_css_value()
// so a stored value like `20px; --dsgo-slider-slides-per-view:99` can't escape
// the declaration context — see render-helpers.php for the rules.
$style_parts = array();
if ( '' !== $atts['height'] ) {
	$style_parts[] = '--dsgo-slider-height:' . designsetgo_safe_css_value( $atts['height'] );
}
$style_parts[] = '--dsgo-slider-aspect-ratio:' . designsetgo_safe_css_value( $atts['aspectRatio'] );
$style_parts[] = '--dsgo-slider-gap:' . designsetgo_safe_css_value( $atts['gap'] );
$style_parts[] = '--dsgo-slider-transition:' . designsetgo_safe_css_value( $atts['transitionDuration'] );
$style_parts[] = '--dsgo-slider-slides-per-view:' . (int) $spv;
$style_parts[] = '--dsgo-slider-slides-per-view-tablet:' . (int) $spv_tablet;
$style_parts[] = '--dsgo-slider-slides-per-view-mobile:' . (int) $spv_mobile;
if ( '' !== $atts['arrowColor'] ) {
	$style_parts[] = '--dsgo-slider-arrow-color:' . designsetgo_safe_css_value( $atts['arrowColor'] );
}
if ( '' !== $atts['arrowBackgroundColor'] ) {
	$style_parts[] = '--dsgo-slider-arrow-bg-color:' . designsetgo_safe_css_value( $atts['arrowBackgroundColor'] );
}
if ( '' !== $atts['dotColor'] ) {
	$style_parts[] = '--dsgo-slider-dot-color:' . designsetgo_safe_css_value( $atts['dotColor'] );
}
if ( '' !== $atts['arrowSize'] ) {
	$style_parts[] = '--dsgo-slider-arrow-size:' . designsetgo_safe_css_value( $atts['arrowSize'] );
}
if ( '' !== $atts['arrowPadding'] ) {
	$style_parts[] = '--dsgo-slider-arrow-padding:' . designsetgo_safe_css_value( $atts['arrowPadding'] );
}
$style_inline = implode( ';', $style_parts ) . ';';

// Merge with get_block_wrapper_attributes() so native supports (padding, margin,
// color, border, etc.) flow through to query-mode output identically.
$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                => implode( ' ', $classes ),
		'style'                => $style_inline,
		'role'                 => 'region',
		// Literal fallback mirrors save.js (which does not translate it).
		// If save.js ever translates the default, match that change here too.
		'aria-label'           => $atts['ariaLabel'] !== '' ? $atts['ariaLabel'] : 'Image slider',
		'aria-roledescription' => 'slider',
	)
);

// Data attributes read by view.js. Booleans serialize as the literal "true"/"false"
// strings — matching how React's save.js emits them via JSX attribute props.
$bool = static function ( $value ) {
	return $value ? 'true' : 'false';
};

$data_attrs = array(
	'data-slides-per-view'         => (string) $spv,
	'data-slides-per-view-tablet'  => (string) $spv_tablet,
	'data-slides-per-view-mobile'  => (string) $spv_mobile,
	'data-use-aspect-ratio'        => $bool( $atts['useAspectRatio'] ),
	'data-show-arrows'             => $bool( $atts['showArrows'] ),
	'data-show-dots'               => $bool( $atts['showDots'] ),
	'data-arrow-style'             => (string) $atts['arrowStyle'],
	'data-arrow-position'          => (string) $atts['arrowPosition'],
	'data-arrow-vertical-position' => (string) $atts['arrowVerticalPosition'],
	'data-dot-style'               => (string) $atts['dotStyle'],
	'data-dot-position'            => (string) $atts['dotPosition'],
	'data-effect'                  => (string) $atts['effect'],
	'data-transition-duration'     => (string) $atts['transitionDuration'],
	'data-transition-easing'       => (string) $atts['transitionEasing'],
	'data-autoplay'                => $bool( $atts['autoplay'] ),
	'data-autoplay-interval'       => (string) (int) $atts['autoplayInterval'],
	'data-pause-on-hover'          => $bool( $atts['pauseOnHover'] ),
	'data-pause-on-interaction'    => $bool( $atts['pauseOnInteraction'] ),
	'data-loop'                    => $bool( $atts['loop'] ),
	'data-draggable'               => $bool( $atts['draggable'] ),
	'data-swipeable'               => $bool( $atts['swipeable'] ),
	'data-free-mode'               => $bool( $atts['freeMode'] ),
	'data-centered-slides'         => $bool( $atts['centeredSlides'] ),
	'data-mobile-breakpoint'       => (string) (int) $atts['mobileBreakpoint'],
	'data-tablet-breakpoint'       => (string) (int) $atts['tabletBreakpoint'],
	'data-active-slide'            => (string) (int) $atts['activeSlide'],
);

if ( $atts['scrollDriven'] ) {
	$data_attrs['data-scroll-driven']       = 'true';
	$data_attrs['data-scroll-driven-speed'] = (string) $atts['scrollDrivenSpeed'];
}

$data_string = '';
foreach ( $data_attrs as $k => $v ) {
	$data_string .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
}

printf(
	'<div %1$s%2$s><div class="dsgo-slider__viewport"><div class="dsgo-slider__track">%3$s</div></div></div>',
	$wrapper_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via get_block_wrapper_attributes().
	$data_string,   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_attr() escaped parts.
	$items_html     // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- items rendered via WP_Block (WP escapes server-side).
);
