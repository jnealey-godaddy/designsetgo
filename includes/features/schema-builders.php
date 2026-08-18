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
				// Stop at the matched element's own closing tag.
				//
				// This reads as though it could not do that — a following
				// SIBLING opens at the same depth as the match, so a `<`
				// comparison would not terminate on it. What makes it work is
				// token ORDER: WP_HTML_Processor pops the breadcrumb stack
				// before visiting a closer, so the matched element's closer
				// reports its PARENT's depth (strictly less than $found) and is
				// visited before any sibling. Descendants report deeper, so
				// nested markup inside the title is still collected in full.
				//
				// tests/phpunit/schema-builders-test.php has the traced token
				// walk, plus a sibling carrying real text that fails loudly if
				// this ever stops holding.
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

if ( ! function_exists( 'designsetgo_schema_normalize_text' ) ) {
	/**
	 * Reduce a fragment of saved HTML to schema-ready plain text.
	 *
	 * The single definition of that sequence. It was duplicated between the
	 * answer text and the HowTo title, and the entity-decoding step was once
	 * fixed in one place and missed in the other — the same page emitting
	 * "Tea &amp; Coffee" beside "Tea & coffee?".
	 *
	 * Order matters and is deliberate:
	 *
	 * 1. Separate at tag boundaries, or `<p>One.</p><p>Two.</p>` fuses into
	 *    "One.Two.". The extra spaces collapse in step 4.
	 * 2. Strip tags.
	 * 3. THEN decode entities. Decoding first would let strip_tags() delete
	 *    text the page actually displays — an answer reading "<b>bold</b>"
	 *    would come back as "bold". A decoded `</script>` cannot break the
	 *    JSON-LD element: SchemaOutput::render() escapes the slash after
	 *    encoding, which covers every source of that sequence at once.
	 * 4. Collapse whitespace and trim.
	 *
	 * @param string $html Saved HTML.
	 * @return string Plain text.
	 */
	function designsetgo_schema_normalize_text( $html ) {
		$html = str_replace( '<', ' <', (string) $html );

		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( (string) $text );
	}
}

if ( ! function_exists( 'designsetgo_schema_text_from_blocks' ) ) {
	/**
	 * Reduce inner blocks to the plain text of their SAVED markup.
	 *
	 * Deliberately serializes rather than renders. render_block() executes each
	 * block's render_callback, and this runs on wp_head — before the main loop
	 * renders the very same blocks for display. Any dynamic block inside an
	 * answer (Query Loop, Latest Posts, a form) would therefore run twice per
	 * request: doubled queries and HTTP calls, and non-idempotent side effects
	 * fired twice. It would also render incorrectly, because render_block()
	 * builds a fresh root WP_Block with no ancestor-supplied context.
	 *
	 * serialize_blocks() reconstructs the stored markup and executes nothing.
	 * Block delimiters are HTML comments, which strip_tags() removes along with
	 * the tags. A dynamic block contributes no text — correct, since its output
	 * is not knowable here and is not part of the authored answer.
	 *
	 * @param array $blocks Parsed inner blocks.
	 * @return string Plain text, whitespace collapsed.
	 */
	function designsetgo_schema_text_from_blocks( array $blocks ) {
		$blocks = array_values(
			array_filter(
				$blocks,
				static function ( $block ) {
					// parse_blocks() emits nameless blocks for the whitespace
					// between siblings.
					return is_array( $block ) && ! empty( $block['blockName'] );
				}
			)
		);

		if ( empty( $blocks ) ) {
			return '';
		}

		return designsetgo_schema_normalize_text( serialize_blocks( $blocks ) );
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
	 * The title is a parameter rather than a get_the_title() call so this stays
	 * a pure function of its arguments, per the contract at the top of the file.
	 * SchemaOutput::render() already holds the post and passes it through.
	 *
	 * @param array  $block Parsed accordion block.
	 * @param string $title Page title, used as HowTo.name. Optional.
	 * @return array|null Schema graph node, or null when unusable.
	 */
	function designsetgo_schema_build_howto( array $block, $title = '' ) {
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

		// Exactly the treatment the question and answer text gets — see the
		// helper for why that matters.
		$title = designsetgo_schema_normalize_text( $title );

		// HowTo.name is required by Google, but claiming an empty one is worse
		// than omitting it.
		if ( '' !== $title ) {
			$schema['name'] = $title;
		}

		return $schema;
	}
}
