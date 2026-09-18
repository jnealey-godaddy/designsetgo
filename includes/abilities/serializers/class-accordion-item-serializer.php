<?php
/**
 * Serializer for designsetgo/accordion-item.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/accordion-item
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accordion_Item_Serializer.
 */
class Accordion_Item_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$title     = isset( $attributes['title'] ) ? $attributes['title'] : '';
		$is_open   = isset( $attributes['isOpen'] ) ? $attributes['isOpen'] : false;
		$unique_id = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : wp_unique_id( 'accordion-item-' );

		// Get icon style/position from context or defaults.
		$icon_style    = isset( $attributes['iconStyle'] ) ? $attributes['iconStyle'] : 'chevron';
		$icon_position = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'right';

		// Build item classes.
		$item_classes   = array( 'dsgo-accordion-item' );
		$item_classes[] = $is_open ? 'dsgo-accordion-item--open' : 'dsgo-accordion-item--closed';

		// Build trigger classes.
		$trigger_classes = array( 'dsgo-accordion-item__trigger' );
		if ( 'left' === $icon_position ) {
			$trigger_classes[] = 'dsgo-accordion-item__trigger--icon-left';
		} elseif ( 'right' === $icon_position ) {
			$trigger_classes[] = 'dsgo-accordion-item__trigger--icon-right';
		}

		// Generate icon SVG based on style.
		$icon_svg = '';
		if ( 'none' !== $icon_style ) {
			switch ( $icon_style ) {
				case 'plus-minus':
					if ( $is_open ) {
						$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4 8h8v1H4z"></path></svg>';
					} else {
						$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 4v8M4 8h8" stroke="currentColor" stroke-width="1" fill="none"></path></svg>';
					}
					break;
				case 'caret':
					$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M6 7l2 2 2-2z"></path></svg>';
					break;
				case 'chevron':
				default:
					$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg>';
					break;
			}
		}

		$header_id = esc_attr( $unique_id ) . '-header';
		$panel_id  = esc_attr( $unique_id ) . '-panel';

		// Build icon HTML.
		$icon_html = '';
		if ( $icon_svg ) {
			$icon_html = '<span class="dsgo-accordion-item__icon" aria-hidden="true">' . $icon_svg . '</span>';
		}

		// Build the full accordion item HTML structure.
		$opening  = '<div class="wp-block-designsetgo-accordion-item ' . esc_attr( implode( ' ', $item_classes ) ) . '" data-initially-open="' . ( $is_open ? 'true' : 'false' ) . '">';
		$opening .= '<div class="dsgo-accordion-item__header">';
		$opening .= '<button type="button" class="' . esc_attr( implode( ' ', $trigger_classes ) ) . '" aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" aria-controls="' . $panel_id . '" id="' . $header_id . '">';
		if ( 'left' === $icon_position ) {
			$opening .= $icon_html;
		}
		// title is source: html and save() renders it with RichText.Content,
		// so escaping here would show the author's markup as literal text.
		// It was only harmless while Block_Configurator stripped the tags
		// first; now that accordion-item::title takes the inline policy,
		// the emitter has to match.
		$opening .= '<span class="dsgo-accordion-item__title">' . wp_kses_post( $title ) . '</span>';
		if ( 'right' === $icon_position ) {
			$opening .= $icon_html;
		}
		$opening .= '</button>';
		$opening .= '</div>';
		$opening .= '<div class="dsgo-accordion-item__panel" role="region" aria-labelledby="' . $header_id . '" id="' . $panel_id . '"' . ( $is_open ? '' : ' hidden' ) . '>';
		$opening .= '<div class="dsgo-accordion-item__content">';

		$closing = '</div></div></div>';

		return array(
			'opening' => $opening,
			'closing' => $closing,
		);
	}
}
