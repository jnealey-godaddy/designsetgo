<?php
/**
 * Block Animation Attributes Helper
 *
 * Provides utility functions to add animation data attributes
 * to dynamic blocks during server-side rendering.
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get animation classes/attributes as structured arrays.
 *
 * Raw (unescaped) values — callers are responsible for escaping. Mirrors
 * addAnimationSaveProps() in src/extensions/block-animations/editor.js: the
 * two must emit identical markup, because a block is served by whichever of
 * them applies (save filter for static blocks, render filter for dynamic
 * ones). Returns only the SVG-draw attribute unless dsgoAnimationEnabled is
 * truthy — that one effect is independent of the entrance/exit system.
 *
 * @param array $attributes Block attributes array.
 * @return array{classes: string[], attrs: array<string,string>}
 */
function designsetgo_get_animation_parts( $attributes ) {
	$classes = array();
	$attrs   = array();

	// SVG drawing targets descendant strokes rather than this block's own
	// opacity, so it is independent of the entrance/exit system and survives
	// the animations-disabled return below.
	if ( ! empty( $attributes['dsgoSvgDraw'] ) ) {
		$attrs['data-dsgo-svg-draw'] = 'true';
	}

	$enabled = isset( $attributes['dsgoAnimationEnabled'] ) ? $attributes['dsgoAnimationEnabled'] : false;
	if ( ! $enabled ) {
		return array(
			'classes' => $classes,
			'attrs'   => $attrs,
		);
	}

	$classes[]                            = 'has-dsgo-animation';
	$attrs['data-dsgo-animation-enabled'] = 'true';

	$entrance = isset( $attributes['dsgoEntranceAnimation'] ) ? (string) $attributes['dsgoEntranceAnimation'] : '';
	if ( '' !== $entrance ) {
		$classes[]                             = 'dsgo-animation-' . $entrance;
		$attrs['data-dsgo-entrance-animation'] = $entrance;
	}

	$trigger = isset( $attributes['dsgoAnimationTrigger'] ) ? (string) $attributes['dsgoAnimationTrigger'] : 'scroll';
	if ( 'scroll' !== $trigger ) {
		$attrs['data-dsgo-animation-trigger'] = $trigger;
	}

	// Scrubbing hands the entrance to the scroll timeline, so it needs an
	// entrance animation and it only means anything on the scroll trigger:
	// frontend.js skips scroll-linked elements entirely, so emitting it on a
	// click- or hover-triggered block would swallow that trigger - and, for
	// click, the tabindex/role=button keyboard affordance with it. Existing
	// content can still carry the combination, which is why the trigger is
	// checked here and not only in the panel.
	$scroll_linked = ! empty( $attributes['dsgoScrollLinked'] )
		&& '' !== $entrance
		&& 'scroll' === $trigger;

	// frontend.js never wires up the exit trigger for a scrubbed element, so
	// exit markup alongside it would advertise an animation that can never
	// fire. Dropped here exactly as the save path drops it.
	$exit = isset( $attributes['dsgoExitAnimation'] ) ? (string) $attributes['dsgoExitAnimation'] : '';
	if ( $scroll_linked ) {
		$exit = '';
	}
	if ( '' !== $exit ) {
		$classes[]                         = 'dsgo-animation-exit-' . $exit;
		$attrs['data-dsgo-exit-animation'] = $exit;
	}

	$duration = isset( $attributes['dsgoAnimationDuration'] ) ? (int) $attributes['dsgoAnimationDuration'] : 600;
	if ( 600 !== $duration ) {
		$attrs['data-dsgo-animation-duration'] = (string) $duration;
	}

	$delay = isset( $attributes['dsgoAnimationDelay'] ) ? (int) $attributes['dsgoAnimationDelay'] : 0;
	if ( 0 !== $delay ) {
		$attrs['data-dsgo-animation-delay'] = (string) $delay;
	}

	$easing = isset( $attributes['dsgoAnimationEasing'] ) ? (string) $attributes['dsgoAnimationEasing'] : 'ease-out';
	if ( 'ease-out' !== $easing ) {
		$attrs['data-dsgo-animation-easing'] = $easing;
	}

	$offset = isset( $attributes['dsgoAnimationOffset'] ) ? (int) $attributes['dsgoAnimationOffset'] : 100;
	if ( 100 !== $offset ) {
		$attrs['data-dsgo-animation-offset'] = (string) $offset;
	}

	$once = isset( $attributes['dsgoAnimationOnce'] ) ? (bool) $attributes['dsgoAnimationOnce'] : true;
	if ( ! $once ) {
		$attrs['data-dsgo-animation-once'] = 'false';
	}

	if ( $scroll_linked ) {
		$attrs['data-dsgo-scroll-linked'] = 'true';
	}

	// Stagger moves the motion onto the block's children, so it needs an
	// animation to move and it rules scrubbing out - the two want the
	// keyframes on different elements.
	$stagger = ! empty( $attributes['dsgoStaggerEnabled'] )
		&& ! $scroll_linked
		&& ( '' !== $entrance || '' !== $exit );

	if ( $stagger ) {
		$attrs['data-dsgo-stagger'] = 'true';

		$step = isset( $attributes['dsgoStaggerStep'] ) ? (int) $attributes['dsgoStaggerStep'] : 80;
		if ( 80 !== $step ) {
			$attrs['data-dsgo-stagger-step'] = (string) $step;
		}
	}

	return array(
		'classes' => $classes,
		'attrs'   => $attrs,
	);
}

/**
 * Get animation data attributes from block attributes
 *
 * Extracts animation-related attributes and returns them as
 * an array of data attributes suitable for adding to HTML elements.
 *
 * @param array $attributes Block attributes array.
 * @return array Array of data attributes for animations.
 */
function designsetgo_get_animation_attributes( $attributes ) {
	$parts = designsetgo_get_animation_parts( $attributes );

	if ( empty( $parts['classes'] ) && empty( $parts['attrs'] ) ) {
		return array(
			'classes' => '',
			'attrs'   => '',
		);
	}

	$classes_string = implode( ' ', array_map( 'esc_attr', $parts['classes'] ) );

	$attrs_string = '';
	foreach ( $parts['attrs'] as $key => $value ) {
		$attrs_string .= ' ' . $key . '="' . esc_attr( $value ) . '"';
	}

	return array(
		'classes' => $classes_string,
		'attrs'   => $attrs_string,
	);
}

/**
 * Get clickable link data attributes from block attributes
 *
 * Extracts link-related attributes for the clickable-group extension.
 *
 * @param array $attributes Block attributes array.
 * @return array Array of data attributes for links.
 */
function designsetgo_get_clickable_attributes( $attributes ) {
	$link_attrs   = array();
	$link_classes = array();

	$link_url = isset( $attributes['dsgLinkUrl'] ) ? $attributes['dsgLinkUrl'] : '';

	if ( empty( $link_url ) ) {
		return array(
			'classes' => '',
			'attrs'   => '',
		);
	}

	// Add clickable class.
	$link_classes[] = 'dsgo-clickable';

	// Add link data attributes.
	$link_attrs['data-link-url'] = esc_attr( $link_url );

	$link_target = isset( $attributes['dsgLinkTarget'] ) ? $attributes['dsgLinkTarget'] : false;
	if ( $link_target ) {
		$link_attrs['data-link-target'] = '_blank';
	}

	$link_rel = isset( $attributes['dsgLinkRel'] ) ? $attributes['dsgLinkRel'] : '';
	if ( $link_rel ) {
		$link_attrs['data-link-rel'] = esc_attr( $link_rel );
	}

	// Convert classes array to string.
	$classes_string = implode( ' ', $link_classes );

	// Convert data attributes array to string.
	$attrs_string = '';
	foreach ( $link_attrs as $key => $value ) {
		$attrs_string .= ' ' . $key . '="' . $value . '"';
	}

	return array(
		'classes' => $classes_string,
		'attrs'   => $attrs_string,
	);
}

/**
 * Add animation and extension attributes to wrapper attributes string
 *
 * Takes an existing wrapper attributes string (from get_block_wrapper_attributes)
 * and injects animation classes/data attributes plus other extension attributes.
 *
 * @param string $wrapper_attributes Existing wrapper attributes string.
 * @param array  $attributes         Block attributes array.
 * @return string Modified wrapper attributes string.
 */
function designsetgo_add_animation_to_wrapper( $wrapper_attributes, $attributes ) {
	// Get animation data.
	$animation_data = designsetgo_get_animation_attributes( $attributes );

	// Get clickable link data.
	$clickable_data = designsetgo_get_clickable_attributes( $attributes );

	// Combine all classes.
	$all_classes = trim( $animation_data['classes'] . ' ' . $clickable_data['classes'] );

	// Combine all data attributes.
	$all_attrs = $animation_data['attrs'] . $clickable_data['attrs'];

	// Add classes to existing class attribute if we have any.
	if ( ! empty( $all_classes ) ) {
		if ( strpos( $wrapper_attributes, 'class="' ) !== false ) {
			$wrapper_attributes = preg_replace(
				'/class="([^"]*)"/',
				'class="$1 ' . $all_classes . '"',
				$wrapper_attributes
			);
		} else {
			// Add new class attribute.
			$wrapper_attributes .= ' class="' . $all_classes . '"';
		}
	}

	// Append all data attributes.
	$wrapper_attributes .= $all_attrs;

	return $wrapper_attributes;
}
