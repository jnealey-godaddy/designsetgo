<?php
/**
 * HTML-to-Block Converter.
 *
 * Main orchestrator that parses semantic HTML and converts it to
 * WordPress Gutenberg block markup using DOMDocument.
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
 * Converter class.
 *
 * Parses HTML using DOMDocument and walks the DOM tree, delegating
 * each element to the appropriate handler via Element_Handler.
 */
class Converter {

	/**
	 * Element handler registry.
	 *
	 * @var Element_Handler
	 */
	private Element_Handler $handler_registry;

	/**
	 * Attribute mapper.
	 *
	 * @var Attribute_Mapper
	 */
	private Attribute_Mapper $attribute_mapper;

	/**
	 * Tags to skip entirely (stripped from output).
	 *
	 * @var array<string>
	 */
	private const SKIP_TAGS = array( 'script', 'style', 'link', 'meta', 'head', 'noscript' );

	/**
	 * Inline-level tags that are preserved as HTML inside block content.
	 *
	 * @var array<string>
	 */
	private const INLINE_TAGS = array(
		'a',
		'abbr',
		'b',
		'bdi',
		'bdo',
		'br',
		'cite',
		'code',
		'del',
		'dfn',
		'em',
		'i',
		'ins',
		'kbd',
		'mark',
		'q',
		's',
		'samp',
		'small',
		'span',
		'strong',
		'sub',
		'sup',
		'time',
		'u',
		'var',
		'wbr',
	);

	/**
	 * Conversion options.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Constructor.
	 *
	 * Registers default handlers based on options.
	 *
	 * @param array<string, mixed> $options Conversion options:
	 *   - prefer_dsgo: bool (default true) Use DesignSetGo blocks over core.
	 *   - wrap_in_section: bool (default false) Wrap top-level in section.
	 */
	public function __construct( array $options = array() ) {
		$this->options = wp_parse_args(
			$options,
			array(
				'prefer_dsgo'     => true,
				'wrap_in_section' => false,
			)
		);

		$this->attribute_mapper = new Attribute_Mapper();
		$this->handler_registry = new Element_Handler();

		$this->register_default_handlers();

		/**
		 * Allow plugins to register custom element handlers.
		 *
		 * @since 2.1.0
		 *
		 * @param Converter       $converter The converter instance.
		 * @param Element_Handler $registry  The handler registry.
		 */
		do_action( 'designsetgo_html_converter_init', $this, $this->handler_registry );
	}

	/**
	 * Convert an HTML string to WordPress block markup.
	 *
	 * @param string $html Raw semantic HTML.
	 * @return string Block markup (serialized blocks).
	 */
	public function convert( string $html ): string {
		$blocks = $this->convert_to_blocks( $html );

		return serialize_blocks( $blocks );
	}

	/**
	 * Convert HTML to a block array (unserialized).
	 *
	 * @param string $html Raw semantic HTML.
	 * @return array<array<string, mixed>> Array of WordPress block arrays.
	 */
	public function convert_to_blocks( string $html ): array {
		$html = trim( $html );
		if ( empty( $html ) ) {
			return array();
		}

		$doc = $this->parse_html( $html );
		if ( ! $doc ) {
			return array();
		}

		$body   = $doc->getElementsByTagName( 'body' )->item( 0 );
		$target = $body ? $body : $doc->documentElement;

		if ( ! $target ) {
			return array();
		}

		$blocks = $this->process_children_of_node( $target );

		// Optionally wrap in a section.
		if ( $this->options['wrap_in_section'] && ! empty( $blocks ) ) {
			$block_name = $this->options['prefer_dsgo'] ? 'designsetgo/section' : 'core/group';
			$blocks     = array(
				array(
					'blockName'    => $block_name,
					'attrs'        => array(),
					'innerBlocks'  => $blocks,
					'innerHTML'    => '',
					'innerContent' => $this->build_inner_content_for_blocks( $blocks ),
				),
			);
		}

		return $blocks;
	}

	/**
	 * Process a single DOM node into a block array.
	 *
	 * @param \DOMNode $node The DOM node to process.
	 * @return array<string, mixed>|null Block array or null if skipped.
	 */
	public function process_node( \DOMNode $node ): ?array {
		// Skip non-element nodes.
		if ( XML_TEXT_NODE === $node->nodeType ) {
			return $this->handle_text_node( $node );
		}

		if ( ! ( $node instanceof \DOMElement ) ) {
			return null;
		}

		$tag_name = strtolower( $node->tagName );

		// Skip script, style, and other non-content tags.
		if ( in_array( $tag_name, self::SKIP_TAGS, true ) ) {
			return null;
		}

		// Inline elements inside a block context should not become blocks.
		// They are handled by get_inner_html() in the parent's handler.
		if ( in_array( $tag_name, self::INLINE_TAGS, true ) ) {
			return $this->wrap_inline_as_paragraph( $node );
		}

		// Check for a registered handler.
		$handler = $this->handler_registry->get_handler( $node );
		if ( $handler ) {
			return call_user_func( $handler, $node, $this );
		}

		// Fallback: if element has children, wrap in group; otherwise wrap as HTML.
		if ( $node->hasChildNodes() ) {
			$inner_blocks = $this->process_children( $node );
			if ( ! empty( $inner_blocks ) ) {
				return array(
					'blockName'    => 'core/group',
					'attrs'        => array(),
					'innerBlocks'  => $inner_blocks,
					'innerHTML'    => "\n<div class=\"wp-block-group\">\n</div>\n",
					'innerContent' => $this->build_inner_content_for_blocks( $inner_blocks, "\n<div class=\"wp-block-group\">\n", "\n</div>\n" ),
				);
			}
		}

		// Leaf element with no handler: wrap as raw HTML block.
		$html = $this->get_outer_html( $node );
		if ( ! empty( trim( $html ) ) ) {
			return array(
				'blockName'    => 'core/html',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => "\n{$html}\n",
				'innerContent' => array( "\n{$html}\n" ),
			);
		}

		return null;
	}

	/**
	 * Process child nodes of a DOM element.
	 *
	 * Public interface for handlers that need to recursively process children.
	 *
	 * @param \DOMNode $parent Parent node.
	 * @return array<array<string, mixed>> Array of block arrays.
	 */
	public function process_children( \DOMNode $parent ): array {
		return $this->process_children_of_node( $parent );
	}

	/**
	 * Get the inner HTML of a DOM element (preserving inline markup).
	 *
	 * @param \DOMElement $element The element.
	 * @return string Inner HTML content.
	 */
	public function get_inner_html( \DOMElement $element ): string {
		$html = '';
		foreach ( $element->childNodes as $child ) {
			$html .= $element->ownerDocument->saveHTML( $child );
		}
		return $html;
	}

	/**
	 * Get the outer HTML of a DOM element.
	 *
	 * @param \DOMElement $element The element.
	 * @return string Outer HTML.
	 */
	public function get_outer_html( \DOMElement $element ): string {
		return $element->ownerDocument->saveHTML( $element );
	}

	/**
	 * Get the handler registry.
	 *
	 * @return Element_Handler
	 */
	public function get_handler_registry(): Element_Handler {
		return $this->handler_registry;
	}

	/**
	 * Register default element handlers.
	 */
	private function register_default_handlers(): void {
		// Always register core handlers first as baseline.
		$core_handlers = new Core_Handlers( $this, $this->attribute_mapper );
		$core_handlers->register( $this->handler_registry );

		// Optionally register DesignSetGo handlers (override core mappings).
		if ( $this->options['prefer_dsgo'] ) {
			$dsgo_handlers = new Dsgo_Handlers( $this, $this->attribute_mapper );
			$dsgo_handlers->register( $this->handler_registry );
		}
	}

	/**
	 * Parse HTML string into a DOMDocument.
	 *
	 * @param string $html Raw HTML.
	 * @return \DOMDocument|null Parsed document or null on failure.
	 */
	private function parse_html( string $html ): ?\DOMDocument {
		$doc = new \DOMDocument( '1.0', 'UTF-8' );

		// Suppress warnings from malformed HTML.
		$prev = libxml_use_internal_errors( true );

		// Wrap in a container to handle HTML fragments.
		$wrapped = '<html><body>' . $html . '</body></html>';
		$doc->loadHTML(
			mb_encode_numericentity( $wrapped, array( 0x80, 0x10FFFF, 0, ~0 ), 'UTF-8' ),
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		return $doc;
	}

	/**
	 * Process child nodes of a parent into block arrays.
	 *
	 * Handles consecutive text/inline nodes by grouping them into paragraphs.
	 *
	 * @param \DOMNode $parent Parent DOM node.
	 * @return array<array<string, mixed>> Array of block arrays.
	 */
	private function process_children_of_node( \DOMNode $parent ): array {
		$blocks       = array();
		$text_buffer  = '';

		foreach ( $parent->childNodes as $child ) {
			// Accumulate text and inline elements.
			if ( XML_TEXT_NODE === $child->nodeType ) {
				$text_buffer .= $child->textContent;
				continue;
			}

			if ( $child instanceof \DOMElement && in_array( strtolower( $child->tagName ), self::INLINE_TAGS, true ) ) {
				// Flush any accumulated text as part of the same paragraph.
				$text_buffer .= $child->ownerDocument->saveHTML( $child );
				continue;
			}

			// Before processing block element, flush text buffer.
			$this->flush_text_buffer( $blocks, $text_buffer );

			// Process block-level element.
			$block = $this->process_node( $child );
			if ( $block ) {
				$blocks[] = $block;
			}
		}

		// Flush remaining text buffer.
		$this->flush_text_buffer( $blocks, $text_buffer );

		return $blocks;
	}

	/**
	 * Flush accumulated text/inline content as a paragraph block.
	 *
	 * @param array<array<string, mixed>> $blocks      Block array (modified by reference).
	 * @param string                      $text_buffer Text buffer (reset to empty).
	 */
	private function flush_text_buffer( array &$blocks, string &$text_buffer ): void {
		$content = trim( $text_buffer );
		$text_buffer = '';

		if ( empty( $content ) ) {
			return;
		}

		// Sanitize inline HTML accumulated from saveHTML() to strip event handlers.
		$content = wp_kses( $content, wp_kses_allowed_html( 'post' ) );

		$blocks[] = array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<p>{$content}</p>\n",
			'innerContent' => array( "\n<p>{$content}</p>\n" ),
		);
	}

	/**
	 * Handle a standalone text node.
	 *
	 * @param \DOMNode $node Text node.
	 * @return array<string, mixed>|null Paragraph block or null.
	 */
	private function handle_text_node( \DOMNode $node ): ?array {
		$content = trim( $node->textContent );
		if ( empty( $content ) ) {
			return null;
		}

		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<p>{$content}</p>\n",
			'innerContent' => array( "\n<p>{$content}</p>\n" ),
		);
	}

	/**
	 * Wrap an inline element as a paragraph.
	 *
	 * Used when an inline element appears at block level without a parent.
	 *
	 * @param \DOMElement $element The inline element.
	 * @return array<string, mixed> Paragraph block array.
	 */
	private function wrap_inline_as_paragraph( \DOMElement $element ): array {
		$html = $this->get_outer_html( $element );

		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<p>{$html}</p>\n",
			'innerContent' => array( "\n<p>{$html}</p>\n" ),
		);
	}

	/**
	 * Build innerContent array with null placeholders for inner blocks.
	 *
	 * @param array<array> $blocks  Inner blocks.
	 * @param string       $opening Opening HTML (optional).
	 * @param string       $closing Closing HTML (optional).
	 * @return array<string|null> innerContent array.
	 */
	public static function build_inner_content_for_blocks( array $blocks, string $opening = '', string $closing = '' ): array {
		$content      = array( $opening );
		$blocks_count = count( $blocks );
		for ( $i = 0; $i < $blocks_count; $i++ ) {
			$content[] = null;
		}
		$content[] = $closing;

		return $content;
	}
}
