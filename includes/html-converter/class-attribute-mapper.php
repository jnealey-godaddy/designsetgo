<?php
/**
 * Attribute Mapper for HTML-to-Block Converter.
 *
 * Converts HTML element attributes (style, class, id, data-*) to
 * WordPress block attributes and style objects.
 *
 * @package DesignSetGo
 * @subpackage HTML_Converter
 * @since 2.1.0
 */

namespace DesignSetGo\HTML_Converter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attribute Mapper class.
 */
class Attribute_Mapper {

	/**
	 * CSS side keywords for padding/margin shorthand.
	 *
	 * @var array<string>
	 */
	private const SIDES = array( 'top', 'right', 'bottom', 'left' );

	/**
	 * Extract block attributes from a DOM element.
	 *
	 * Combines style, class, id, and data-* attribute mappings.
	 *
	 * @param \DOMElement $element    The HTML element.
	 * @param string      $block_name Target block name (for context-specific mapping).
	 * @return array<string, mixed> Block attributes.
	 */
	public function map_attributes( \DOMElement $element, string $block_name ): array {
		$attrs = array();

		// Map inline styles.
		$style_attr = $element->getAttribute( 'style' );
		if ( ! empty( $style_attr ) ) {
			$style = $this->parse_style_to_block_style( $style_attr );
			if ( ! empty( $style ) ) {
				$attrs['style'] = $style;
			}
		}

		// Map CSS classes.
		$class_attr = $element->getAttribute( 'class' );
		if ( ! empty( $class_attr ) ) {
			$class_attrs = $this->parse_classes_to_attributes( $class_attr, $block_name );
			$attrs       = array_merge( $attrs, $class_attrs );
		}

		// Map id to anchor.
		$id_attr = $element->getAttribute( 'id' );
		if ( ! empty( $id_attr ) ) {
			$attrs['anchor'] = sanitize_html_class( $id_attr );
		}

		// Map data-* attributes.
		$data_attrs = $this->parse_data_attributes( $element );
		if ( ! empty( $data_attrs ) ) {
			$attrs = array_merge( $attrs, $data_attrs );
		}

		return $attrs;
	}

	/**
	 * Parse inline style string into WordPress block style array.
	 *
	 * @param string $style_string CSS style string (e.g., "color: red; padding: 10px").
	 * @return array<string, mixed> WordPress block style attribute.
	 */
	public function parse_style_to_block_style( string $style_string ): array {
		$style      = array();
		$properties = array_filter( array_map( 'trim', explode( ';', $style_string ) ) );

		foreach ( $properties as $property ) {
			$parts = array_map( 'trim', explode( ':', $property, 2 ) );
			if ( count( $parts ) !== 2 || empty( $parts[0] ) || empty( $parts[1] ) ) {
				continue;
			}

			$name  = strtolower( $parts[0] );
			$value = $parts[1];

			$this->map_css_property( $style, $name, $value );
		}

		return $style;
	}

	/**
	 * Map CSS classes to block attributes.
	 *
	 * Recognizes WordPress utility classes like has-text-align-*, alignfull, etc.
	 *
	 * @param string $class_string Space-separated class names.
	 * @param string $block_name   Target block name.
	 * @return array<string, mixed> Extracted attributes.
	 */
	public function parse_classes_to_attributes( string $class_string, string $block_name ): array {
		$attrs   = array();
		$classes = preg_split( '/\s+/', trim( $class_string ) );

		foreach ( $classes as $class ) {
			$class = strtolower( $class );

			// Text alignment: has-text-align-center, has-text-align-left, etc.
			if ( preg_match( '/^has-text-align-(left|center|right)$/', $class, $matches ) ) {
				$attrs['textAlign'] = $matches[1];
				continue;
			}

			// Font size: has-small-font-size, has-large-font-size, etc.
			if ( preg_match( '/^has-([a-z-]+)-font-size$/', $class, $matches ) ) {
				$attrs['fontSize'] = $matches[1];
				continue;
			}

			// Background color preset: has-contrast-background-color, etc.
			if ( preg_match( '/^has-([a-z-]+)-background-color$/', $class, $matches ) ) {
				$attrs['backgroundColor'] = $matches[1];
				continue;
			}

			// Text color preset: has-contrast-color, etc.
			// Skip WordPress marker classes like has-text-color and has-background.
			if ( preg_match( '/^has-([a-z-]+)-color$/', $class, $matches ) && 'text' !== $matches[1] ) {
				$attrs['textColor'] = $matches[1];
				continue;
			}

			// Alignment: alignfull, alignwide, aligncenter, etc.
			if ( preg_match( '/^align(full|wide|center|left|right|none)$/', $class, $matches ) ) {
				$attrs['align'] = $matches[1];
				continue;
			}
		}

		return $attrs;
	}

	/**
	 * Map data-* attributes to block attributes.
	 *
	 * @param \DOMElement $element The HTML element.
	 * @return array<string, mixed> Block attributes from data attributes.
	 */
	public function parse_data_attributes( \DOMElement $element ): array {
		$attrs = array();

		if ( ! $element->hasAttributes() ) {
			return $attrs;
		}

		foreach ( $element->attributes as $attr ) {
			if ( strpos( $attr->name, 'data-' ) !== 0 ) {
				continue;
			}

			// Convert data-attribute-name to camelCase attributeName.
			$key = substr( $attr->name, 5 ); // Remove 'data-' prefix.
			$key = lcfirst( str_replace( '-', '', ucwords( $key, '-' ) ) );

			$attrs[ $key ] = $attr->value;
		}

		return $attrs;
	}

	/**
	 * Map a single CSS property to the WordPress block style structure.
	 *
	 * @param array<string, mixed> $style Block style array (modified by reference).
	 * @param string               $name  CSS property name.
	 * @param string               $value CSS property value.
	 */
	private function map_css_property( array &$style, string $name, string $value ): void {
		// Color properties.
		if ( 'color' === $name ) {
			$style['color']['text'] = $value;
			return;
		}
		if ( 'background-color' === $name ) {
			$style['color']['background'] = $value;
			return;
		}

		// Typography properties.
		if ( 'font-size' === $name ) {
			$style['typography']['fontSize'] = $value;
			return;
		}
		if ( 'font-weight' === $name ) {
			$style['typography']['fontWeight'] = $value;
			return;
		}
		if ( 'font-style' === $name ) {
			$style['typography']['fontStyle'] = $value;
			return;
		}
		if ( 'font-family' === $name ) {
			$style['typography']['fontFamily'] = $value;
			return;
		}
		if ( 'line-height' === $name ) {
			$style['typography']['lineHeight'] = $value;
			return;
		}
		if ( 'letter-spacing' === $name ) {
			$style['typography']['letterSpacing'] = $value;
			return;
		}
		if ( 'text-decoration' === $name ) {
			$style['typography']['textDecoration'] = $value;
			return;
		}
		if ( 'text-transform' === $name ) {
			$style['typography']['textTransform'] = $value;
			return;
		}

		// Spacing: padding.
		if ( 'padding' === $name ) {
			$this->map_shorthand_spacing( $style, 'padding', $value );
			return;
		}
		foreach ( self::SIDES as $side ) {
			if ( "padding-{$side}" === $name ) {
				$style['spacing']['padding'][ $side ] = $value;
				return;
			}
		}

		// Spacing: margin.
		if ( 'margin' === $name ) {
			$this->map_shorthand_spacing( $style, 'margin', $value );
			return;
		}
		foreach ( self::SIDES as $side ) {
			if ( "margin-{$side}" === $name ) {
				$style['spacing']['margin'][ $side ] = $value;
				return;
			}
		}

		// Border radius.
		if ( 'border-radius' === $name ) {
			$style['border']['radius'] = $value;
			return;
		}
	}

	/**
	 * Map CSS shorthand spacing (padding/margin) to block style.
	 *
	 * @param array<string, mixed> $style    Block style array (modified by reference).
	 * @param string               $property Either 'padding' or 'margin'.
	 * @param string               $value    CSS shorthand value.
	 */
	private function map_shorthand_spacing( array &$style, string $property, string $value ): void {
		$parts = preg_split( '/\s+/', trim( $value ) );
		$count = count( $parts );

		switch ( $count ) {
			case 1:
				$style['spacing'][ $property ] = array(
					'top'    => $parts[0],
					'right'  => $parts[0],
					'bottom' => $parts[0],
					'left'   => $parts[0],
				);
				break;
			case 2:
				$style['spacing'][ $property ] = array(
					'top'    => $parts[0],
					'right'  => $parts[1],
					'bottom' => $parts[0],
					'left'   => $parts[1],
				);
				break;
			case 3:
				$style['spacing'][ $property ] = array(
					'top'    => $parts[0],
					'right'  => $parts[1],
					'bottom' => $parts[2],
					'left'   => $parts[1],
				);
				break;
			case 4:
				$style['spacing'][ $property ] = array(
					'top'    => $parts[0],
					'right'  => $parts[1],
					'bottom' => $parts[2],
					'left'   => $parts[3],
				);
				break;
		}
	}
}
