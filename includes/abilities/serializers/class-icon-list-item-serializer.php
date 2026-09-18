<?php
/**
 * Serializer for designsetgo/icon-list-item.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/icon-list-item
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IconListItem_Serializer.
 */
class IconListItem_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$icon     = isset( $attributes['icon'] ) ? $attributes['icon'] : 'star';
		$link_url = isset( $attributes['linkUrl'] ) ? $attributes['linkUrl'] : '';
		// Same as icon-button below: block.json defaults linkTarget to
		// `_self`, so save() always emits target alongside href. Defaulting
		// to '' here suppressed it and linked items failed validation.
		$link_target = isset( $attributes['linkTarget'] ) && '' !== $attributes['linkTarget']
			? $attributes['linkTarget']
			: '_self';
		$link_rel    = isset( $attributes['linkRel'] ) ? $attributes['linkRel'] : '';

		// Everything below must reproduce save.js's output for an
		// EMPTY block context, because that is the only output that
		// exists in stored markup: WordPress does not pass block context
		// to save(), so the parent Icon List's iconSize / iconPosition /
		// colours resolve to their defaults there and the item inherits
		// them from CSS at render time instead.
		//
		// Concretely that means: icon-left, no inline icon size (the
		// `--inherit-size` class hands sizing to the theme token), no
		// item gap, and the icon box's layout coming from style.scss.
		// Reading iconSize / iconPosition off $attributes here — as this
		// branch used to — produced markup save() never generates, so an
		// AI-inserted item failed validation the first time it was opened.
		// row / flex-start / left are save.js's empty-context values, not
		// choices made here — hence the literals rather than variables.
		$item_style = 'display:flex;flex-direction:row;align-items:flex-start';

		// Content gap: written inline only for an explicit author value,
		// mirroring save.js (the attribute has no default).
		$content_style = 'text-align:left;display:flex;flex-direction:column';
		if ( isset( $attributes['contentGap'] ) && is_numeric( $attributes['contentGap'] ) ) {
			$content_style .= ';gap:' . Serializer_Support::numeric_attribute( $attributes['contentGap'] ) . 'px';
		}

		$outer_class = 'wp-block-designsetgo-icon-list-item dsgo-icon-list-item dsgo-icon-list-item--icon-left';

		// No inline style on the icon box: layout lives in style.scss and
		// size resolves from --dsgo-icon-list-size via the inherit-size class.
		$icon_html = '<div class="dsgo-icon-list-item__icon dsgo-lazy-icon dsgo-icon-list-item__icon--inherit-size" data-icon-name="' . esc_attr( $icon ) . '"></div>';

		// Build element (div or link).
		$tag         = $link_url ? 'a' : 'div';
		$extra_attrs = '';
		if ( $link_url ) {
			$extra_attrs .= ' href="' . esc_url( $link_url ) . '"';
			if ( $link_target ) {
				$extra_attrs .= ' target="' . esc_attr( $link_target ) . '"';
			}
			if ( $link_rel ) {
				$extra_attrs .= ' rel="' . esc_attr( $link_rel ) . '"';
			}
		}

		return array(
			'opening' => '<' . $tag . ' class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $item_style ) . '"' . $extra_attrs . '>' . $icon_html . '<div class="dsgo-icon-list-item__content" style="' . esc_attr( $content_style ) . '">',
			'closing' => '</div></' . $tag . '>',
		);
	}
}
