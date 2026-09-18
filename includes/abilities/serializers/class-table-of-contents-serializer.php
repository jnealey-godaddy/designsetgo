<?php
/**
 * Serializer for designsetgo/table-of-contents.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/table-of-contents
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Table_Of_Contents_Serializer.
 */
class Table_Of_Contents_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$unique_id     = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : substr( wp_generate_uuid4(), 0, 8 );
		$include_h2    = isset( $attributes['includeH2'] ) ? $attributes['includeH2'] : true;
		$include_h3    = isset( $attributes['includeH3'] ) ? $attributes['includeH3'] : true;
		$include_h4    = isset( $attributes['includeH4'] ) ? $attributes['includeH4'] : false;
		$include_h5    = isset( $attributes['includeH5'] ) ? $attributes['includeH5'] : false;
		$include_h6    = isset( $attributes['includeH6'] ) ? $attributes['includeH6'] : false;
		$display_mode  = isset( $attributes['displayMode'] ) ? $attributes['displayMode'] : 'hierarchical';
		$list_style    = isset( $attributes['listStyle'] ) ? $attributes['listStyle'] : 'unordered';
		$show_title    = isset( $attributes['showTitle'] ) ? $attributes['showTitle'] : true;
		$title_text    = isset( $attributes['titleText'] ) ? $attributes['titleText'] : 'Table of Contents';
		$scroll_smooth = isset( $attributes['scrollSmooth'] ) ? $attributes['scrollSmooth'] : true;
		$scroll_offset = isset( $attributes['scrollOffset'] ) ? Serializer_Support::numeric_attribute( $attributes['scrollOffset'] ) : 0;

		// Build heading levels.
		$heading_levels = array();
		if ( $include_h2 ) {
			$heading_levels[] = 'h2';
		}
		if ( $include_h3 ) {
			$heading_levels[] = 'h3';
		}
		if ( $include_h4 ) {
			$heading_levels[] = 'h4';
		}
		if ( $include_h5 ) {
			$heading_levels[] = 'h5';
		}
		if ( $include_h6 ) {
			$heading_levels[] = 'h6';
		}

		// Build classes.
		$class_parts = array( 'wp-block-designsetgo-table-of-contents', 'dsgo-table-of-contents' );
		if ( 'hierarchical' === $display_mode ) {
			$class_parts[] = 'dsgo-table-of-contents--hierarchical';
		} else {
			$class_parts[] = 'dsgo-table-of-contents--flat';
		}
		if ( 'ordered' === $list_style ) {
			$class_parts[] = 'dsgo-table-of-contents--ordered';
		}
		if ( $scroll_smooth ) {
			$class_parts[] = 'dsgo-table-of-contents--smooth';
		}

		// Data attributes.
		$data_attrs  = ' data-unique-id="' . esc_attr( $unique_id ) . '"';
		$data_attrs .= ' data-heading-levels="' . esc_attr( implode( ',', $heading_levels ) ) . '"';
		$data_attrs .= ' data-display-mode="' . esc_attr( $display_mode ) . '"';
		$data_attrs .= ' data-scroll-smooth="' . ( $scroll_smooth ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-scroll-offset="' . esc_attr( (string) $scroll_offset ) . '"';

		// Custom properties, set only when the author chose a value -
		// save.js guards each one on truthiness, so a zero sticky offset
		// is omitted rather than written as 0px.
		$toc_styles = array();
		if ( ! empty( $attributes['linkColor'] ) && is_string( $attributes['linkColor'] ) ) {
			$toc_styles[] = '--dsgo-toc-link-color:' . Serializer_Support::convert_color_value_to_css_var( $attributes['linkColor'] );
		}
		if ( ! empty( $attributes['activeLinkColor'] ) && is_string( $attributes['activeLinkColor'] ) ) {
			$toc_styles[] = '--dsgo-toc-active-link-color:' . Serializer_Support::convert_color_value_to_css_var( $attributes['activeLinkColor'] );
		}
		if ( ! empty( $attributes['stickyOffset'] ) && is_numeric( $attributes['stickyOffset'] ) ) {
			$toc_styles[] = '--dsgo-toc-sticky-offset:' . Serializer_Support::format_js_number( (float) $attributes['stickyOffset'] ) . 'px';
		}
		$toc_style_attr = empty( $toc_styles ) ? '' : ' style="' . esc_attr( implode( ';', $toc_styles ) ) . '"';

		// List tag.
		$list_tag = 'ordered' === $list_style ? 'ol' : 'ul';

		// Inner HTML.
		$inner_html = '<div class="dsgo-table-of-contents__content">';
		// titleText is DOM-sourced, so the title element is always rendered
		// (matching save.js) and hidden via the `--hidden` modifier when the
		// toggle is off, rather than being omitted — otherwise a hidden
		// title's text would be silently lost on reload.
		$toc_title_class = 'dsgo-table-of-contents__title' . ( $show_title ? '' : ' dsgo-table-of-contents__title--hidden' );
		$inner_html     .= '<div class="' . esc_attr( $toc_title_class ) . '">' . esc_html( $title_text ) . '</div>';
		$inner_html     .= '<' . $list_tag . ' class="dsgo-table-of-contents__list"></' . $list_tag . '>';
		$inner_html     .= '</div>';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $toc_style_attr . $data_attrs . '>' . $inner_html,
			'closing' => '</div>',
		);
	}
}
