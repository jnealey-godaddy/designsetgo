<?php
/**
 * Schema builders.
 *
 * Pure functions: a parsed block array in, a schema.org array out. No output,
 * no globals — unit-testable in isolation.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_schema_text_from_html' ) ) {
	/**
	 * Extract the plain text of the first element carrying a class.
	 *
	 * The accordion's `title` is an HTML-SOURCED attribute in block.json
	 * (source: "html", selector: ".dsgo-accordion-item__title"), which means
	 * WordPress reads it back out of the markup when parsing for the editor —
	 * but `parse_blocks()` does NOT. It only decodes the block comment's JSON,
	 * so `$block['attrs']['title']` is absent for every real post. Reading the
	 * title therefore means reading the saved HTML, which is what this does.
	 *
	 * Uses WP_HTML_Processor (WP 6.4+; the plugin requires 6.7) rather than
	 * DOMDocument, which mangles UTF-8 without an explicit encoding preamble,
	 * or a regex, which cannot track nesting.
	 *
	 * @param string $html       Saved block HTML.
	 * @param string $class_name Class to look for, without the leading dot.
	 * @return string Plain text, whitespace collapsed. '' when not found.
	 */
	function designsetgo_schema_text_from_html( $html, $class_name ) {
		$html = (string) $html;

		if ( '' === $html || '' === $class_name ) {
			return '';
		}

		$processor = WP_HTML_Processor::create_fragment( $html );

		if ( null === $processor ) {
			return '';
		}

		$text  = '';
		$found = false;

		while ( $processor->next_token() ) {
			if ( $found ) {
				// Collect text until the subtree closes. Breadcrumbs shrink back
				// past the opening depth once the element ends.
				if ( count( $processor->get_breadcrumbs() ) < $found ) {
					break;
				}

				if ( '#text' === $processor->get_token_type() ) {
					$text .= $processor->get_modifiable_text();
				}

				continue;
			}

			if ( '#tag' !== $processor->get_token_type() || $processor->is_tag_closer() ) {
				continue;
			}

			if ( ! $processor->has_class( $class_name ) ) {
				continue;
			}

			// Depth of the element itself; its children sit deeper than this.
			$found = count( $processor->get_breadcrumbs() );
		}

		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( (string) $text );
	}
}

if ( ! function_exists( 'designsetgo_schema_text_from_blocks' ) ) {
	/**
	 * Render inner blocks and reduce them to plain text.
	 *
	 * @param array $blocks Parsed inner blocks.
	 * @return string Plain text, whitespace collapsed.
	 */
	function designsetgo_schema_text_from_blocks( array $blocks ) {
		$html = '';

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			// parse_blocks() emits nameless blocks for the whitespace between
			// siblings; rendering them is harmless but pointless.
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$html .= render_block( $block );
		}

		// Separate at every tag boundary before stripping. Without this,
		// `<p>One.</p><p>Two.</p>` reduces to "One.Two." — two sentences fused
		// into one token, which is poor structured data. The extra spaces are
		// collapsed immediately below, so inline markup is unaffected:
		// "Hello <strong>world</strong>" still yields "Hello world".
		$html = str_replace( '<', ' <', $html );

		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( (string) $text );
	}
}

if ( ! function_exists( 'designsetgo_schema_accordion_pairs' ) ) {
	/**
	 * Extract question/answer pairs from an accordion block.
	 *
	 * @param array $block Parsed accordion block.
	 * @return array List of array{name: string, text: string}.
	 */
	function designsetgo_schema_accordion_pairs( array $block ) {
		$pairs = array();
		$items = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
			? $block['innerBlocks']
			: array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( 'designsetgo/accordion-item' !== ( isset( $item['blockName'] ) ? $item['blockName'] : '' ) ) {
				continue;
			}

			$name = designsetgo_schema_text_from_html(
				isset( $item['innerHTML'] ) ? $item['innerHTML'] : '',
				'dsgo-accordion-item__title'
			);

			$text = designsetgo_schema_text_from_blocks(
				isset( $item['innerBlocks'] ) && is_array( $item['innerBlocks'] )
					? $item['innerBlocks']
					: array()
			);

			// A pair missing either half is not valid structured data.
			if ( '' === $name || '' === $text ) {
				continue;
			}

			$pairs[] = array(
				'name' => $name,
				'text' => $text,
			);
		}

		return $pairs;
	}
}

if ( ! function_exists( 'designsetgo_schema_build_faq' ) ) {
	/**
	 * Build FAQPage schema from an accordion.
	 *
	 * @param array $block Parsed accordion block.
	 * @return array|null Schema graph node, or null when unusable.
	 */
	function designsetgo_schema_build_faq( array $block ) {
		$pairs = designsetgo_schema_accordion_pairs( $block );

		if ( empty( $pairs ) ) {
			return null;
		}

		$questions = array();

		foreach ( $pairs as $pair ) {
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $pair['name'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $pair['text'],
				),
			);
		}

		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => $questions,
		);
	}
}

if ( ! function_exists( 'designsetgo_schema_build_howto' ) ) {
	/**
	 * Build HowTo schema from an accordion.
	 *
	 * @param array $block Parsed accordion block.
	 * @return array|null Schema graph node, or null when unusable.
	 */
	function designsetgo_schema_build_howto( array $block ) {
		$pairs = designsetgo_schema_accordion_pairs( $block );

		if ( empty( $pairs ) ) {
			return null;
		}

		$steps = array();

		foreach ( $pairs as $index => $pair ) {
			$steps[] = array(
				'@type'    => 'HowToStep',
				'position' => $index + 1,
				'name'     => $pair['name'],
				'text'     => $pair['text'],
			);
		}

		$schema = array(
			'@type' => 'HowTo',
			'step'  => $steps,
		);

		$title = trim( wp_strip_all_tags( (string) get_the_title() ) );

		// HowTo.name is required by Google. Only claim one when there is a real
		// post title to use — outside the loop get_the_title() returns ''.
		if ( '' !== $title ) {
			$schema['name'] = $title;
		}

		return $schema;
	}
}
