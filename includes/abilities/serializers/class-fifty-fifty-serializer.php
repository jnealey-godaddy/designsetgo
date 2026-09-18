<?php
/**
 * Serializer for designsetgo/fifty-fifty.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/fifty-fifty
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FiftyFifty_Serializer.
 */
class FiftyFifty_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/fifty-fifty/save.js.
		$media_position = ( isset( $attributes['mediaPosition'] ) && 'right' === $attributes['mediaPosition'] ) ? 'right' : 'left';
		$media_url      = isset( $attributes['mediaUrl'] ) ? (string) $attributes['mediaUrl'] : '';
		$media_alt      = isset( $attributes['mediaAlt'] ) ? (string) $attributes['mediaAlt'] : '';
		$focal_point    = ( isset( $attributes['focalPoint'] ) && is_array( $attributes['focalPoint'] ) ) ? $attributes['focalPoint'] : null;
		$min_height     = isset( $attributes['minHeight'] ) ? (string) $attributes['minHeight'] : '';
		$vertical_align = isset( $attributes['verticalAlignment'] ) ? (string) $attributes['verticalAlignment'] : '';
		$content_pad    = isset( $attributes['contentPadding'] ) ? $attributes['contentPadding'] : '';

		$class_parts = array( 'wp-block-designsetgo-fifty-fifty' );
		if ( isset( $attributes['align'] ) && 'full' === $attributes['align'] ) {
			$class_parts[] = 'alignfull';
		}
		$class_parts[] = 'dsgo-fifty-fifty';
		$class_parts[] = 'dsgo-fifty-fifty--media-' . $media_position;

		$fifty_styles = array();

		// save.js only writes minHeight when it matches this pattern.
		if ( '' !== $min_height && preg_match( '/^[\d.]+(px|vh|vw|em|rem|%)$/', $min_height ) ) {
			$fifty_styles[] = '--dsgo-fifty-fifty-min-height:' . $min_height;
		}

		$align_items_map = array(
			'top'    => 'flex-start',
			'center' => 'center',
			'bottom' => 'flex-end',
		);
		// Always written: save.js falls back to 'center'.
		$fifty_styles[] = '--dsgo-fifty-fifty-content-justify:' . ( $align_items_map[ $vertical_align ] ?? 'center' );

		$content_pad_css = is_string( $content_pad ) ? Serializer_Support::wp_shorthand_to_css_var( $content_pad ) : '';
		if ( '' !== $content_pad_css ) {
			$fifty_styles[] = '--dsgo-fifty-fifty-content-padding:' . $content_pad_css;
		}

		// The media <img> is emitted only for an http(s) URL, matching
		// isValidImageUrl() in save.js.
		$media_html = '';
		if ( '' !== $media_url && preg_match( '#^https?://#', $media_url ) ) {
			$object_position = '';
			if ( $focal_point && isset( $focal_point['x'], $focal_point['y'] ) ) {
				$object_position = ' style="object-position:' .
					esc_attr( ( (float) $focal_point['x'] * 100 ) . '% ' . ( (float) $focal_point['y'] * 100 ) . '%' ) . '"';
			}

			$media_html = '<img src="' . esc_url( $media_url ) . '" alt="' . esc_attr( $media_alt ) . '"' .
				$object_position . ' loading="lazy"' .
				( '' === $media_alt ? ' aria-hidden="true"' : '' ) . '/>';
		}

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( implode( ';', $fifty_styles ) ) . '">' .
				'<div class="dsgo-fifty-fifty__media">' . $media_html . '</div>' .
				'<div class="dsgo-fifty-fifty__content"><div class="dsgo-fifty-fifty__content-inner">',
			'closing' => '</div></div></div>',
		);
	}
}
