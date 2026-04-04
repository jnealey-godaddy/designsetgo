<?php
/**
 * Element Handler Registry for HTML-to-Block Converter.
 *
 * Manages tag-based and CSS-class-based handler lookups for converting
 * HTML elements to WordPress block arrays.
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
 * Element Handler registry class.
 *
 * Supports two lookup strategies:
 * - Tag handlers: match by HTML tag name (e.g., 'section', 'p', 'h1').
 * - Class handlers: match by CSS class on generic elements like div/span.
 *   Class handlers are checked first for ambiguous tags, allowing
 *   `<div class="columns">` to map to a row block instead of a generic group.
 */
class Element_Handler {

	/**
	 * Tag-name handlers.
	 *
	 * @var array<string, callable>
	 */
	private array $tag_handlers = array();

	/**
	 * CSS-class handlers (checked first for generic tags like div/span).
	 *
	 * @var array<string, callable>
	 */
	private array $class_handlers = array();

	/**
	 * Tags considered generic (class handlers checked first).
	 *
	 * @var array<string>
	 */
	private array $generic_tags = array( 'div', 'span' );

	/**
	 * Register a handler for an HTML tag name.
	 *
	 * @param string   $tag_name Lowercase HTML tag name (e.g., 'section', 'p').
	 * @param callable $handler  Handler receiving (DOMElement, Converter) and returning array|null.
	 */
	public function register_tag_handler( string $tag_name, callable $handler ): void {
		$this->tag_handlers[ strtolower( $tag_name ) ] = $handler;
	}

	/**
	 * Register a handler for a CSS class name.
	 *
	 * Class handlers are checked before tag handlers for generic tags
	 * (div, span), allowing class-based disambiguation.
	 *
	 * @param string   $class_name CSS class name (e.g., 'columns', 'grid', 'card').
	 * @param callable $handler    Handler receiving (DOMElement, Converter) and returning array|null.
	 */
	public function register_class_handler( string $class_name, callable $handler ): void {
		$this->class_handlers[ strtolower( $class_name ) ] = $handler;
	}

	/**
	 * Find the best handler for a DOM element.
	 *
	 * For generic tags (div, span), class handlers are checked first.
	 * Then tag handlers are checked. Returns null if no handler matches.
	 *
	 * @param \DOMElement $element The HTML element to find a handler for.
	 * @return callable|null The matching handler or null.
	 */
	public function get_handler( \DOMElement $element ): ?callable {
		$tag_name = strtolower( $element->tagName );

		// For generic tags, check class handlers first.
		if ( in_array( $tag_name, $this->generic_tags, true ) ) {
			$handler = $this->find_class_handler( $element );
			if ( $handler ) {
				return $handler;
			}
		}

		// Check tag handler.
		if ( isset( $this->tag_handlers[ $tag_name ] ) ) {
			return $this->tag_handlers[ $tag_name ];
		}

		return null;
	}

	/**
	 * Check if a handler exists for a tag name.
	 *
	 * @param string $tag_name HTML tag name.
	 * @return bool
	 */
	public function has_tag_handler( string $tag_name ): bool {
		return isset( $this->tag_handlers[ strtolower( $tag_name ) ] );
	}

	/**
	 * Check if a handler exists for a CSS class name.
	 *
	 * @param string $class_name CSS class name.
	 * @return bool
	 */
	public function has_class_handler( string $class_name ): bool {
		return isset( $this->class_handlers[ strtolower( $class_name ) ] );
	}

	/**
	 * Find a matching class handler for an element.
	 *
	 * Iterates the element's class list and returns the first matching handler.
	 *
	 * @param \DOMElement $element The HTML element.
	 * @return callable|null The matching handler or null.
	 */
	private function find_class_handler( \DOMElement $element ): ?callable {
		$class_attr = $element->getAttribute( 'class' );
		if ( empty( $class_attr ) ) {
			return null;
		}

		$classes = preg_split( '/\s+/', trim( $class_attr ) );

		foreach ( $classes as $class ) {
			$class = strtolower( $class );
			if ( isset( $this->class_handlers[ $class ] ) ) {
				return $this->class_handlers[ $class ];
			}
		}

		return null;
	}
}
