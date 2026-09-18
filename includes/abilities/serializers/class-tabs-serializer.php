<?php
/**
 * Serializer for designsetgo/tabs.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/tabs
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tabs_Serializer.
 */
class Tabs_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$unique_id         = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 );
		$orientation       = isset( $attributes['orientation'] ) ? $attributes['orientation'] : 'horizontal';
		$active_tab        = isset( $attributes['activeTab'] ) ? Serializer_Support::numeric_attribute( $attributes['activeTab'] ) : 0;
		$alignment         = isset( $attributes['alignment'] ) ? $attributes['alignment'] : 'left';
		$mobile_breakpoint = isset( $attributes['mobileBreakpoint'] ) ? Serializer_Support::numeric_attribute( $attributes['mobileBreakpoint'] ) : 768;
		$mobile_mode       = isset( $attributes['mobileMode'] ) ? $attributes['mobileMode'] : 'accordion';
		$enable_deep_link  = isset( $attributes['enableDeepLinking'] ) ? $attributes['enableDeepLinking'] : false;
		$gap               = isset( $attributes['gap'] ) ? $attributes['gap'] : '8px';
		$tab_style         = isset( $attributes['tabStyle'] ) ? $attributes['tabStyle'] : 'default';
		$show_nav_border   = isset( $attributes['showNavBorder'] ) ? $attributes['showNavBorder'] : false;

		// Build classes.
		$class_parts   = array( 'wp-block-designsetgo-tabs', 'dsgo-tabs', 'dsgo-tabs-' . esc_attr( $unique_id ) );
		$class_parts[] = 'dsgo-tabs--' . esc_attr( $orientation );
		$class_parts[] = 'dsgo-tabs--' . esc_attr( $tab_style );
		$class_parts[] = 'dsgo-tabs--align-' . esc_attr( $alignment );
		if ( $show_nav_border ) {
			$class_parts[] = 'dsgo-tabs--show-nav-border';
		}

		// Build style.
		// Mirrors save.js: the gap always, then each colour custom
		// property only when its attribute is set. These eight were
		// missing entirely, so any Tabs block given colours stored
		// markup that did not match save().
		$tab_style_parts = array( '--dsgo-tabs-gap:' . esc_attr( $gap ) );

		$tab_color_vars = array(
			'tabColor'                  => '--dsgo-tab-color',
			'tabBackgroundColor'        => '--dsgo-tab-bg',
			'tabContentBackgroundColor' => '--dsgo-tab-content-bg',
			'activeTabColor'            => '--dsgo-tab-color-active',
			'activeTabBackgroundColor'  => '--dsgo-tab-bg-active',
			'tabBorderColor'            => '--dsgo-tab-border-color',
			'tabHoverColor'             => '--dsgo-tab-color-hover',
			'tabHoverBackgroundColor'   => '--dsgo-tab-bg-hover',
		);

		foreach ( $tab_color_vars as $attribute_name => $custom_property ) {
			$colour = isset( $attributes[ $attribute_name ] ) ? (string) $attributes[ $attribute_name ] : '';
			if ( '' !== $colour ) {
				$tab_style_parts[] = $custom_property . ':' . esc_attr( Serializer_Support::convert_color_value_to_css_var( $colour ) );
			}
		}

		$style = implode( ';', $tab_style_parts );

		// Data attributes.
		$data_attrs  = ' data-active-tab="' . esc_attr( (string) $active_tab ) . '"';
		$data_attrs .= ' data-mobile-breakpoint="' . esc_attr( (string) $mobile_breakpoint ) . '"';
		$data_attrs .= ' data-mobile-mode="' . esc_attr( $mobile_mode ) . '"';
		$data_attrs .= ' data-deep-linking="' . ( $enable_deep_link ? 'true' : 'false' ) . '"';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '"' . $data_attrs . '><div class="dsgo-tabs__nav" role="tablist"></div><div class="dsgo-tabs__panels">',
			'closing' => '</div></div>',
		);
	}
}
