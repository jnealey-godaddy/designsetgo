<?php
/**
 * Serializer for designsetgo/image-accordion.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/image-accordion
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image_Accordion_Serializer.
 */
class Image_Accordion_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$height                   = isset( $attributes['height'] ) ? $attributes['height'] : '500px';
		$gap                      = isset( $attributes['gap'] ) ? $attributes['gap'] : '4px';
		$expanded_ratio           = isset( $attributes['expandedRatio'] ) ? floatval( $attributes['expandedRatio'] ) : 3;
		$transition_duration      = isset( $attributes['transitionDuration'] ) ? $attributes['transitionDuration'] : '0.5s';
		$enable_overlay           = isset( $attributes['enableOverlay'] ) ? $attributes['enableOverlay'] : true;
		$overlay_color            = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '#000000';
		$overlay_opacity          = isset( $attributes['overlayOpacity'] ) ? floatval( $attributes['overlayOpacity'] ) : 40;
		$overlay_opacity_expanded = isset( $attributes['overlayOpacityExpanded'] ) ? floatval( $attributes['overlayOpacityExpanded'] ) : 20;
		$trigger_type             = isset( $attributes['triggerType'] ) ? $attributes['triggerType'] : 'hover';
		$default_expanded         = isset( $attributes['defaultExpanded'] ) ? Serializer_Support::numeric_attribute( $attributes['defaultExpanded'] ) : 0;

		// Build classes.
		$class_parts   = array( 'wp-block-designsetgo-image-accordion', 'dsgo-image-accordion' );
		$class_parts[] = 'dsgo-image-accordion--' . esc_attr( $trigger_type );

		// Build style with CSS custom properties.
		// height and gap have no block.json default either, so save()
		// writes nothing for them and the stylesheet supplies the size.
		$style_parts = array();
		if ( isset( $attributes['height'] ) && '' !== $attributes['height'] ) {
			$style_parts[] = '--dsgo-image-accordion-height:' . esc_attr( $attributes['height'] );
		}
		if ( isset( $attributes['gap'] ) && '' !== $attributes['gap'] ) {
			$style_parts[] = '--dsgo-image-accordion-gap:' . esc_attr( $attributes['gap'] );
		}
		$style_parts[] = '--dsgo-image-accordion-expanded-ratio:' . esc_attr( (string) $expanded_ratio );
		$style_parts[] = '--dsgo-image-accordion-transition:' . esc_attr( $transition_duration );

		// overlayColor and the two opacities have NO block.json default,
		// so save() sees them undefined and writes nothing; the
		// stylesheet supplies the fallback. Inventing #000000 / 0.4 / 0.2
		// here emitted declarations save() never writes, and the block
		// was only "valid" because a deprecation claimed it — every
		// insert silently migrated on open.
		if ( isset( $attributes['overlayColor'] ) && '' !== $attributes['overlayColor'] ) {
			$style_parts[] = '--dsgo-image-accordion-overlay-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $attributes['overlayColor'] ) );
		}
		if ( isset( $attributes['overlayOpacity'] ) && is_numeric( $attributes['overlayOpacity'] ) ) {
			$style_parts[] = '--dsgo-image-accordion-overlay-opacity:' . esc_attr( Serializer_Support::format_js_number( (float) $attributes['overlayOpacity'] / 100 ) );
		}
		if ( isset( $attributes['overlayOpacityExpanded'] ) && is_numeric( $attributes['overlayOpacityExpanded'] ) ) {
			$style_parts[] = '--dsgo-image-accordion-overlay-opacity-expanded:' . esc_attr( Serializer_Support::format_js_number( (float) $attributes['overlayOpacityExpanded'] / 100 ) );
		}
		$style = implode( ';', $style_parts );

		// Data attributes.
		$data_attrs  = ' data-trigger-type="' . esc_attr( $trigger_type ) . '"';
		$data_attrs .= ' data-default-expanded="' . esc_attr( (string) $default_expanded ) . '"';
		$data_attrs .= ' data-enable-overlay="' . ( $enable_overlay ? 'true' : 'false' ) . '"';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '"' . $data_attrs . '><div class="dsgo-image-accordion__items">',
			'closing' => '</div></div>',
		);
	}
}
