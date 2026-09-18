<?php
/**
 * Serializer for designsetgo/section.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/section
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

use DesignSetGo\Abilities\Block_Schema_Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Section_Serializer.
 */
class Section_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Section's block.json gives `style` a default carrying the page
		// padding, and its save() serializes spacing support, so that
		// padding IS in the stored markup. WordPress drops the default
		// when it registers `style` on the PHP side (it re-registers the
		// support-backed attribute as a bare object), so it has to be
		// read back from block.json. Only when the caller supplied no
		// style at all: an attribute default is replaced wholesale, not
		// deep-merged, so a caller-supplied partial style legitimately
		// has no padding.
		if ( ! isset( $attributes['style'] ) ) {
			$declared_style = Block_Schema_Loader::get_block_json( $block_name )['attributes']['style']['default'] ?? null;
			if ( is_array( $declared_style ) ) {
				$attributes['style'] = Serializer_Support::convert_style_vars( $declared_style );
			}
		}

		$constrain_width = isset( $attributes['constrainWidth'] ) ? $attributes['constrainWidth'] : true;
		$content_width   = isset( $attributes['contentWidth'] ) ? $attributes['contentWidth'] : '';
		$box_width       = isset( $attributes['boxWidth'] ) && is_string( $attributes['boxWidth'] ) ? $attributes['boxWidth'] : '';
		$align           = isset( $attributes['align'] ) ? $attributes['align'] : 'full';
		$tag_name        = isset( $attributes['tagName'] ) && $attributes['tagName'] ? $attributes['tagName'] : 'div';

		// Build outer classes (order: wp-block-*, alignX, dsgo-*).
		$outer_class_parts = array( 'wp-block-designsetgo-section' );
		if ( 'full' === $align ) {
			$outer_class_parts[] = 'alignfull';
		} elseif ( 'wide' === $align ) {
			$outer_class_parts[] = 'alignwide';
		}
		$outer_class_parts[] = 'dsgo-stack';
		if ( Serializer_Support::has_overlay( $attributes ) ) {
			$outer_class_parts[] = 'dsgo-stack--has-overlay';
		}
		if ( ! $constrain_width ) {
			$outer_class_parts[] = 'dsgo-no-width-constraint';
		}

		// Shape dividers — mirrors src/blocks/section/save.js plus
		// ShapeDivider.js and utils/shape-dividers.js exactly. The
		// shape itself is CSS mask-image, not inline SVG, so only
		// marker classes and CSS custom properties are emitted.
		$shape_top    = isset( $attributes['shapeDividerTop'] ) && is_string( $attributes['shapeDividerTop'] ) ? $attributes['shapeDividerTop'] : '';
		$shape_bottom = isset( $attributes['shapeDividerBottom'] ) && is_string( $attributes['shapeDividerBottom'] ) ? $attributes['shapeDividerBottom'] : '';
		if ( '' !== $shape_top || '' !== $shape_bottom ) {
			$outer_class_parts[] = 'dsgo-stack--has-shape-divider';
		}
		if ( '' !== $box_width ) {
			$outer_class_parts[] = 'dsgo-stack--has-box-width';
		}

		// Process block support styles (colors, padding, etc.).
		$style             = isset( $attributes['style'] ) ? $attributes['style'] : array();
		$support_result    = Serializer_Support::get_block_support_styles( $style );
		$outer_class_parts = array_merge( $outer_class_parts, $support_result['classes'] );

		// Only what the style attribute actually carries. Padding used to
		// be fabricated here whenever `style.spacing.padding` was empty,
		// but a block.json attribute default is REPLACED wholesale when a
		// caller supplies the attribute, not deep-merged: a section given
		// only a colour legitimately has no padding, and save() emits
		// none. apply_block_json_defaults() supplies the default `style`
		// (which does include padding) when the caller omits it entirely.
		$outer_styles = array_merge(
			Serializer_Support::container_hover_styles( $attributes ),
			$support_result['styles']
		);

		// Shape divider content-clearance: expose the divider's
		// RENDERED height on the wrapper so the stylesheet fallback
		// reserves inner padding that matches what the divider
		// paints. Omitted when an explicit spacing override is set
		// (its inline padding wins below) or when the height is
		// unset (the divider then inherits the theme.json height
		// token, and the stylesheet resolves clearance from that
		// same token). Must match save.js exactly.
		$shape_top_height    = Serializer_Support::normalize_shape_size( $attributes['shapeDividerTopHeight'] ?? null, 10, 500 );
		$shape_bottom_height = Serializer_Support::normalize_shape_size( $attributes['shapeDividerBottomHeight'] ?? null, 10, 500 );
		if ( '' !== $shape_top && empty( $attributes['shapeDividerTopSpacing'] ) && null !== $shape_top_height ) {
			$outer_styles[] = '--dsgo-shape-clearance-top:' . Serializer_Support::format_js_number( $shape_top_height ) . 'px';
		}
		if ( '' !== $shape_bottom && empty( $attributes['shapeDividerBottomSpacing'] ) && null !== $shape_bottom_height ) {
			$outer_styles[] = '--dsgo-shape-clearance-bottom:' . Serializer_Support::format_js_number( $shape_bottom_height ) . 'px';
		}

		// Outer box width - mirrors src/blocks/section/utils/box-width.js
		// (getBoxWidthStyle) exactly, including its declaration order.
		// `width:100%` makes the box REACH the cap inside a flex parent;
		// the cap itself is `max-width`. No margins: horizontal
		// placement is a stylesheet concern (styles/_box-width.scss) so
		// that a flex parent's own alignment can govern. Emits nothing
		// when boxWidth is unset.
		if ( '' !== $box_width ) {
			$outer_styles[] = 'width:100%';
			$outer_styles[] = 'max-width:' . $box_width;
		}

		// Match Section save(): only constrained sections carry an
		// inner measure, but a shape-divider content-clearance
		// spacing override is independent of the width constraint
		// and carries its own padding declaration either way.
		// contentPosition mirrors utils/content-position.js: 'center' (and
		// anything unrecognised) keeps both margins auto.
		$max_width         = $content_width ? $content_width : 'var(--wp--style--global--content-size, 1140px)';
		$content_position  = isset( $attributes['contentPosition'] ) ? $attributes['contentPosition'] : 'center';
		$inner_style_parts = array();
		if ( $constrain_width ) {
			$inner_style_parts[] = 'max-width:' . $max_width;
			$inner_style_parts[] = 'margin-left:' . ( 'left' === $content_position ? '0' : 'auto' );
			$inner_style_parts[] = 'margin-right:' . ( 'right' === $content_position ? '0' : 'auto' );
		}
		if ( '' !== $shape_top && ! empty( $attributes['shapeDividerTopSpacing'] ) && is_string( $attributes['shapeDividerTopSpacing'] ) ) {
			$inner_style_parts[] = 'padding-top:' . Serializer_Support::wp_shorthand_to_css_var( $attributes['shapeDividerTopSpacing'] );
		}
		if ( '' !== $shape_bottom && ! empty( $attributes['shapeDividerBottomSpacing'] ) && is_string( $attributes['shapeDividerBottomSpacing'] ) ) {
			$inner_style_parts[] = 'padding-bottom:' . Serializer_Support::wp_shorthand_to_css_var( $attributes['shapeDividerBottomSpacing'] );
		}
		$inner_style = empty( $inner_style_parts ) ? '' : ' style="' . esc_attr( implode( ';', $inner_style_parts ) ) . '"';

		return array(
			'opening' => '<' . esc_attr( $tag_name ) . ' class="' . esc_attr( implode( ' ', $outer_class_parts ) ) . '" style="' . esc_attr( implode( ';', $outer_styles ) ) . '">' .
				Serializer_Support::render_shape_divider( $attributes, 'shapeDividerTop', 'top' ) .
				'<div class="dsgo-stack__inner"' . $inner_style . '>',
			'closing' => '</div>' . Serializer_Support::render_shape_divider( $attributes, 'shapeDividerBottom', 'bottom' ) . '</' . esc_attr( $tag_name ) . '>',
		);
	}
}
