<?php
/**
 * Serializer for designsetgo/blobs.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/blobs
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blobs_Serializer.
 */
class Blobs_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/blobs/save.js.
		$blob_shape     = isset( $attributes['blobShape'] ) ? (string) $attributes['blobShape'] : 'shape-1';
		$blob_animation = isset( $attributes['blobAnimation'] ) ? (string) $attributes['blobAnimation'] : 'none';
		$blob_duration  = isset( $attributes['animationDuration'] ) ? (string) $attributes['animationDuration'] : '8s';
		$blob_easing    = isset( $attributes['animationEasing'] ) ? (string) $attributes['animationEasing'] : 'ease-in-out';
		$blob_size      = isset( $attributes['size'] ) ? (string) $attributes['size'] : '300px';
		$blob_height    = isset( $attributes['height'] ) ? (string) $attributes['height'] : '';
		$blob_max_width = isset( $attributes['maxWidth'] ) ? $attributes['maxWidth'] : null;
		$enable_overlay = ! empty( $attributes['enableOverlay'] );
		$overlay_color  = isset( $attributes['overlayColor'] ) ? (string) $attributes['overlayColor'] : '';
		$overlay_pct    = isset( $attributes['overlayOpacity'] ) && is_numeric( $attributes['overlayOpacity'] )
			? (float) $attributes['overlayOpacity']
			: 80;

		// hasExplicitString(): a non-empty trimmed string.
		$has_max_width = is_string( $blob_max_width ) && '' !== trim( $blob_max_width );

		$wrapper_classes = array( 'wp-block-designsetgo-blobs' );
		$blobs_align     = Serializer_Support::align_class( $block_name, $attributes );
		if ( '' !== $blobs_align ) {
			$wrapper_classes[] = $blobs_align;
		}
		$wrapper_classes[] = 'dsgo-blobs-wrapper';
		if ( $has_max_width ) {
			$wrapper_classes[] = 'dsgo-has-max-width';
		}

		$blob_classes = array( 'dsgo-blobs' );
		if ( '' !== $blob_shape ) {
			$blob_classes[] = 'dsgo-blobs--' . $blob_shape;
		}
		if ( '' !== $blob_animation && 'none' !== $blob_animation ) {
			$blob_classes[] = 'dsgo-blobs--' . $blob_animation;
		}

		// save.js writes size, duration and easing unconditionally;
		// height only when set.
		$blob_styles = array( '--dsgo-blob-size:' . $blob_size );
		if ( '' !== $blob_height ) {
			$blob_styles[] = '--dsgo-blob-height:' . $blob_height;
		}
		$blob_styles[] = '--dsgo-blob-animation-duration:' . $blob_duration;
		$blob_styles[] = '--dsgo-blob-animation-easing:' . $blob_easing;

		$overlay_html = '';
		if ( $enable_overlay ) {
			// React drops a style property whose value is empty, so an
			// overlay with no colour set writes opacity alone. Building
			// the string unconditionally emitted `background-color:;`,
			// which is not a declaration save() ever produces.
			$blob_overlay_styles = array();
			$blob_overlay_color  = Serializer_Support::convert_color_value_to_css_var( $overlay_color );
			if ( '' !== $blob_overlay_color ) {
				$blob_overlay_styles[] = 'background-color:' . $blob_overlay_color;
			}
			$blob_overlay_styles[] = 'opacity:' . Serializer_Support::format_js_number( $overlay_pct / 100 );

			$overlay_html = '<div class="dsgo-blobs__overlay" style="' . esc_attr( implode( ';', $blob_overlay_styles ) ) . '"></div>';
		}

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"' .
				( $has_max_width ? ' style="' . esc_attr( '--dsgo-blob-max-width:' . $blob_max_width ) . '"' : '' ) . '>' .
				'<div class="' . esc_attr( implode( ' ', $blob_classes ) ) . '" style="' . esc_attr( implode( ';', $blob_styles ) ) . '"' .
				' data-blob-animation="' . esc_attr( $blob_animation ) . '">' .
				$overlay_html .
				'<div class="dsgo-blobs__shape"><div class="dsgo-blobs__content">',
			'closing' => '</div></div></div></div>',
		);
	}
}
