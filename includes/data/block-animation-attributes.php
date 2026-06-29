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
 * Get animation data attributes from block attributes
 *
 * Extracts animation-related attributes and returns them as
 * an array of data attributes suitable for adding to HTML elements.
 *
 * @param array $attributes Block attributes array.
 * @return array Array of data attributes for animations.
 */
function designsetgo_get_animation_attributes( $attributes ) {
	$animation_attrs   = array();
	$animation_classes = array();

	// Check if animations are enabled.
	$animation_enabled = isset( $attributes['dsgoAnimationEnabled'] ) ? $attributes['dsgoAnimationEnabled'] : false;

	if ( ! $animation_enabled ) {
		return array(
			'classes' => '',
			'attrs'   => '',
		);
	}

	// Add animation classes.
	$animation_classes[] = 'has-dsgo-animation';

	// Add entrance animation class.
	$entrance_animation = isset( $attributes['dsgoEntranceAnimation'] ) ? $attributes['dsgoEntranceAnimation'] : '';
	if ( $entrance_animation ) {
		$animation_classes[] = 'dsgo-animation-' . esc_attr( $entrance_animation );
	}

	// Add exit animation class.
	$exit_animation = isset( $attributes['dsgoExitAnimation'] ) ? $attributes['dsgoExitAnimation'] : '';
	if ( $exit_animation ) {
		$animation_classes[] = 'dsgo-animation-exit-' . esc_attr( $exit_animation );
	}

	// Always include the enabled flag and animation type(s) — required by frontend JS.
	$animation_attrs['data-dsgo-animation-enabled'] = 'true';

	if ( $entrance_animation ) {
		$animation_attrs['data-dsgo-entrance-animation'] = esc_attr( $entrance_animation );
	}

	if ( $exit_animation ) {
		$animation_attrs['data-dsgo-exit-animation'] = esc_attr( $exit_animation );
	}

	// Only output settings that differ from defaults to keep markup lean.
	// Defaults: trigger=scroll, duration=600, delay=0, easing=ease-out, offset=100, once=true.
	$trigger = isset( $attributes['dsgoAnimationTrigger'] ) ? $attributes['dsgoAnimationTrigger'] : 'scroll';
	if ( 'scroll' !== $trigger ) {
		$animation_attrs['data-dsgo-animation-trigger'] = esc_attr( $trigger );
	}

	$duration = isset( $attributes['dsgoAnimationDuration'] ) ? (int) $attributes['dsgoAnimationDuration'] : 600;
	if ( 600 !== $duration ) {
		$animation_attrs['data-dsgo-animation-duration'] = esc_attr( (string) $duration );
	}

	$delay = isset( $attributes['dsgoAnimationDelay'] ) ? (int) $attributes['dsgoAnimationDelay'] : 0;
	if ( 0 !== $delay ) {
		$animation_attrs['data-dsgo-animation-delay'] = esc_attr( (string) $delay );
	}

	$easing = isset( $attributes['dsgoAnimationEasing'] ) ? $attributes['dsgoAnimationEasing'] : 'ease-out';
	if ( 'ease-out' !== $easing ) {
		$animation_attrs['data-dsgo-animation-easing'] = esc_attr( $easing );
	}

	$offset = isset( $attributes['dsgoAnimationOffset'] ) ? (int) $attributes['dsgoAnimationOffset'] : 100;
	if ( 100 !== $offset ) {
		$animation_attrs['data-dsgo-animation-offset'] = esc_attr( (string) $offset );
	}

	$once = isset( $attributes['dsgoAnimationOnce'] ) ? (bool) $attributes['dsgoAnimationOnce'] : true;
	if ( ! $once ) {
		$animation_attrs['data-dsgo-animation-once'] = 'false';
	}

	// Convert classes array to string.
	$classes_string = implode( ' ', $animation_classes );

	// Convert data attributes array to string.
	$attrs_string = '';
	foreach ( $animation_attrs as $key => $value ) {
		$attrs_string .= ' ' . $key . '="' . $value . '"';
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
