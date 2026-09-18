<?php
/**
 * Serializer for designsetgo/scroll-accordion.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/scroll-accordion
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scroll_Accordion_Serializer.
 */
class Scroll_Accordion_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$align_items = isset( $attributes['alignItems'] ) ? $attributes['alignItems'] : 'flex-start';

		// Must match save.js: the constant layout (`width`/`align-self`
		// on the root, `display`/`flex-direction` on the items wrapper)
		// lives in style.scss and is no longer serialized. Only the
		// author-controlled alignItems is written inline. Emitting the
		// constants here would produce markup save() never generates, so
		// the block would fail validation on first open.
		$inner_style = 'align-items:' . esc_attr( $align_items );

		// Built as a list, not by concatenating a possibly-empty align
		// class between two others: trim() only strips the ends, so an
		// unaligned block was left with a double space in its class
		// attribute on every insert.
		$accordion_classes = array( 'wp-block-designsetgo-scroll-accordion' );
		$accordion_align   = Serializer_Support::align_class( $block_name, $attributes );
		if ( '' !== $accordion_align ) {
			$accordion_classes[] = $accordion_align;
		}
		$accordion_classes[] = 'dsgo-scroll-accordion';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $accordion_classes ) ) . '"><div class="dsgo-scroll-accordion__items" style="' . esc_attr( $inner_style ) . '">',
			'closing' => '</div></div>',
		);
	}
}
