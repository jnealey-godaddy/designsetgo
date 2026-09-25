<?php
/**
 * HTML processor that swaps the contents of an element found by class name.
 *
 * @package DesignSetGo
 * @since   2.8.3
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces the inner HTML of the first element carrying a given class.
 *
 * WP_Block::replace_html() applies a bound value to an HTML-sourced attribute
 * by matching the attribute's selector as a TAG NAME only. DesignSetGo's
 * sourced attributes use class selectors (`.dsgo-accordion-item__title`), so
 * core finds nothing and the stored text renders instead of the bound value.
 * This mirrors core's own internal processor (WP_Block::get_block_bindings_processor()),
 * matching by class instead of tag.
 */
class Bindings_HTML_Processor extends \WP_HTML_Processor {

	/**
	 * Replace the inner HTML of the first element with the class.
	 *
	 * @param string $html        Block markup.
	 * @param string $class_name  Class to match, without the leading dot.
	 * @param string $replacement Replacement inner HTML, already sanitized.
	 * @return string Updated markup, or the original when nothing matched.
	 */
	public static function replace_inner_html_by_class( $html, $class_name, $replacement ) {
		$processor = static::create_fragment( $html );

		if ( ! $processor instanceof self ) {
			return $html;
		}

		while ( $processor->next_tag() ) {
			if ( $processor->has_class( $class_name ) ) {
				return $processor->replace_inner_html( $replacement ) ? $processor->get_updated_html() : $html;
			}
		}

		return $html;
	}

	/**
	 * Replace everything between the current tag opener and its closer.
	 *
	 * @param string $inner_html Replacement inner HTML.
	 * @return bool Whether the replacement was queued.
	 */
	private function replace_inner_html( $inner_html ) {
		// Held in a variable: PHPStan treats is_tag_closer() as pure, so
		// testing the call itself here would make it "know" the closer check
		// below is always false, although next_token() has moved on since.
		$on_closer = $this->is_tag_closer();
		if ( $on_closer || ! $this->expects_closer() ) {
			return false;
		}

		$depth    = $this->get_current_depth();
		$tag_name = $this->get_tag();

		// Bookmark names are stored with a leading underscore.
		$this->set_bookmark( 'dsgo_bindings' );
		$opener = $this->bookmarks['_dsgo_bindings'];
		$start  = $opener->start + $opener->length;

		// Walk to the matching closer.
		while ( $this->next_token() && $this->get_current_depth() >= $depth ) {
			continue;
		}

		if ( ! $this->is_tag_closer() || $tag_name !== $this->get_tag() ) {
			return false;
		}

		$this->set_bookmark( 'dsgo_bindings' );
		$closer = $this->bookmarks['_dsgo_bindings'];

		$this->lexical_updates[] = new \WP_HTML_Text_Replacement( $start, $closer->start - $start, $inner_html );

		return true;
	}
}
