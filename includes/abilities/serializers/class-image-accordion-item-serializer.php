<?php
/**
 * Serializer for designsetgo/image-accordion-item.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/image-accordion-item
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image_Accordion_Item_Serializer.
 */
class Image_Accordion_Item_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$unique_id            = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : 'image-accordion-item-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 );
		$vertical_alignment   = isset( $attributes['verticalAlignment'] ) ? $attributes['verticalAlignment'] : 'center';
		$horizontal_alignment = isset( $attributes['horizontalAlignment'] ) ? $attributes['horizontalAlignment'] : 'center';

		// The overlay marker is unconditional: enableOverlay is not an
		// attribute of this block (it comes from the parent through
		// usesContext, and save() receives no context), so save.js
		// hardcodes the enabled default for serialized markup.
		$class_parts = array(
			'wp-block-designsetgo-image-accordion-item',
			'dsgo-image-accordion-item',
			'dsgo-image-accordion-item--has-overlay',
		);

		// Build style with CSS custom properties (overlay first, then alignment - must match save.js order).
		$style_parts = array();
		// No overlay custom properties here. The overlay colour and
		// opacities are NOT attributes of this block - they come from the
		// parent accordion through usesContext, and WordPress passes no
		// context to save(), so save() serializes none of them and the
		// values cascade from the parent's own custom properties at
		// render time. Inventing #000000 / 0.4 / 0.2 wrote three
		// declarations save() never emits, and the block was only
		// "valid" because a deprecation claimed the markup - every
		// inserted item silently migrated when the editor opened it.
		$style_parts[] = '--dsgo-vertical-alignment:' . esc_attr( $vertical_alignment );
		$style_parts[] = '--dsgo-horizontal-alignment:' . esc_attr( $horizontal_alignment );
		$style         = implode( ';', $style_parts );

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '" data-unique-id="' . esc_attr( $unique_id ) . '" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content">',
			'closing' => '</div></div>',
		);
	}
}
