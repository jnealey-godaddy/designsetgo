<?php
/**
 * Core Block Handlers for HTML-to-Block Converter.
 *
 * Maps standard HTML elements to WordPress core blocks.
 * These serve as fallbacks when DesignSetGo blocks are not preferred.
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
 * Core Handlers class.
 *
 * Each handler method receives a DOMElement and the Converter instance,
 * and returns a WordPress block array (blockName, attrs, innerBlocks).
 */
class Core_Handlers {

	/**
	 * Converter instance.
	 *
	 * @var Converter
	 */
	private Converter $converter;

	/**
	 * Attribute mapper instance.
	 *
	 * @var Attribute_Mapper
	 */
	private Attribute_Mapper $attribute_mapper;

	/**
	 * Constructor.
	 *
	 * @param Converter        $converter        Converter instance.
	 * @param Attribute_Mapper $attribute_mapper  Attribute mapper instance.
	 */
	public function __construct( Converter $converter, Attribute_Mapper $attribute_mapper ) {
		$this->converter        = $converter;
		$this->attribute_mapper = $attribute_mapper;
	}

	/**
	 * Register all core handlers with the element handler registry.
	 *
	 * @param Element_Handler $registry Handler registry.
	 */
	public function register( Element_Handler $registry ): void {
		$registry->register_tag_handler( 'p', array( $this, 'handle_paragraph' ) );
		$registry->register_tag_handler( 'h1', array( $this, 'handle_heading' ) );
		$registry->register_tag_handler( 'h2', array( $this, 'handle_heading' ) );
		$registry->register_tag_handler( 'h3', array( $this, 'handle_heading' ) );
		$registry->register_tag_handler( 'h4', array( $this, 'handle_heading' ) );
		$registry->register_tag_handler( 'h5', array( $this, 'handle_heading' ) );
		$registry->register_tag_handler( 'h6', array( $this, 'handle_heading' ) );
		$registry->register_tag_handler( 'ul', array( $this, 'handle_list' ) );
		$registry->register_tag_handler( 'ol', array( $this, 'handle_list' ) );
		$registry->register_tag_handler( 'blockquote', array( $this, 'handle_blockquote' ) );
		$registry->register_tag_handler( 'pre', array( $this, 'handle_code' ) );
		$registry->register_tag_handler( 'img', array( $this, 'handle_image' ) );
		$registry->register_tag_handler( 'figure', array( $this, 'handle_figure' ) );
		$registry->register_tag_handler( 'table', array( $this, 'handle_table' ) );
		$registry->register_tag_handler( 'hr', array( $this, 'handle_separator' ) );
		$registry->register_tag_handler( 'video', array( $this, 'handle_video' ) );
		$registry->register_tag_handler( 'audio', array( $this, 'handle_audio' ) );
		$registry->register_tag_handler( 'div', array( $this, 'handle_div' ) );
	}

	/**
	 * Handle paragraph element.
	 *
	 * @param \DOMElement $element   The paragraph element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_paragraph( \DOMElement $element, Converter $converter ): array {
		$attrs      = $this->attribute_mapper->map_attributes( $element, 'core/paragraph' );
		$inner_html = $converter->get_inner_html( $element );

		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<p>" . $inner_html . "</p>\n",
			'innerContent' => array( "\n<p>" . $inner_html . "</p>\n" ),
		);
	}

	/**
	 * Handle heading element (h1-h6).
	 *
	 * @param \DOMElement $element   The heading element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_heading( \DOMElement $element, Converter $converter ): array {
		$level      = (int) substr( $element->tagName, 1 );
		$attrs      = $this->attribute_mapper->map_attributes( $element, 'core/heading' );
		$attrs['level'] = $level;
		$inner_html = $converter->get_inner_html( $element );
		$tag        = 'h' . $level;

		return array(
			'blockName'    => 'core/heading',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<{$tag} class=\"wp-block-heading\">{$inner_html}</{$tag}>\n",
			'innerContent' => array( "\n<{$tag} class=\"wp-block-heading\">{$inner_html}</{$tag}>\n" ),
		);
	}

	/**
	 * Handle list element (ul/ol).
	 *
	 * @param \DOMElement $element   The list element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_list( \DOMElement $element, Converter $converter ): array {
		$ordered = 'ol' === strtolower( $element->tagName );
		$attrs   = $this->attribute_mapper->map_attributes( $element, 'core/list' );

		if ( $ordered ) {
			$attrs['ordered'] = true;
		}

		// Convert <li> children to core/list-item inner blocks.
		$inner_blocks = array();
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement && 'li' === strtolower( $child->tagName ) ) {
				$inner_blocks[] = $this->handle_list_item( $child, $converter );
			}
		}

		$tag = $ordered ? 'ol' : 'ul';

		return array(
			'blockName'    => 'core/list',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => "\n<{$tag}>\n</{$tag}>\n",
			'innerContent' => $this->build_inner_content_with_blocks(
				"\n<{$tag}>\n",
				count( $inner_blocks ),
				"\n</{$tag}>\n"
			),
		);
	}

	/**
	 * Handle list item element.
	 *
	 * @param \DOMElement $element   The li element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	private function handle_list_item( \DOMElement $element, Converter $converter ): array {
		$inner_html = $converter->get_inner_html( $element );

		return array(
			'blockName'    => 'core/list-item',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<li>{$inner_html}</li>\n",
			'innerContent' => array( "\n<li>{$inner_html}</li>\n" ),
		);
	}

	/**
	 * Handle blockquote element.
	 *
	 * @param \DOMElement $element   The blockquote element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_blockquote( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'core/quote' );
		$inner_blocks = $converter->process_children( $element );

		return array(
			'blockName'    => 'core/quote',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => "\n<blockquote class=\"wp-block-quote\">\n</blockquote>\n",
			'innerContent' => $this->build_inner_content_with_blocks(
				"\n<blockquote class=\"wp-block-quote\">\n",
				count( $inner_blocks ),
				"\n</blockquote>\n"
			),
		);
	}

	/**
	 * Handle pre/code element (code block).
	 *
	 * @param \DOMElement $element   The pre element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_code( \DOMElement $element, Converter $converter ): array {
		$attrs   = $this->attribute_mapper->map_attributes( $element, 'core/code' );
		$content = $converter->get_inner_html( $element );

		// If pre contains a code element, extract its content.
		$code_element = $element->getElementsByTagName( 'code' )->item( 0 );
		if ( $code_element ) {
			$content = $converter->get_inner_html( $code_element );

			// Extract language from class attribute (e.g., language-php).
			$class = $code_element->getAttribute( 'class' );
			if ( preg_match( '/language-([a-z0-9+#-]+)/i', $class, $matches ) ) {
				$attrs['language'] = strtolower( $matches[1] );
			}
		}

		return array(
			'blockName'    => 'core/code',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<pre class=\"wp-block-code\"><code>{$content}</code></pre>\n",
			'innerContent' => array( "\n<pre class=\"wp-block-code\"><code>{$content}</code></pre>\n" ),
		);
	}

	/**
	 * Handle image element.
	 *
	 * @param \DOMElement $element   The img element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed>|null Block array or null.
	 */
	public function handle_image( \DOMElement $element, Converter $converter ): ?array {
		$src = $element->getAttribute( 'src' );
		if ( empty( $src ) ) {
			return null;
		}

		$attrs        = $this->attribute_mapper->map_attributes( $element, 'core/image' );
		$attrs['url'] = $src;

		$alt = $element->getAttribute( 'alt' );
		if ( ! empty( $alt ) ) {
			$attrs['alt'] = $alt;
		}

		$width = $element->getAttribute( 'width' );
		if ( ! empty( $width ) ) {
			$attrs['width'] = $width;
		}

		$height = $element->getAttribute( 'height' );
		if ( ! empty( $height ) ) {
			$attrs['height'] = $height;
		}

		$src_escaped = esc_url( $src );
		$alt_escaped = esc_attr( $alt );

		return array(
			'blockName'    => 'core/image',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<figure class=\"wp-block-image\"><img src=\"{$src_escaped}\" alt=\"{$alt_escaped}\"/></figure>\n",
			'innerContent' => array( "\n<figure class=\"wp-block-image\"><img src=\"{$src_escaped}\" alt=\"{$alt_escaped}\"/></figure>\n" ),
		);
	}

	/**
	 * Handle figure element.
	 *
	 * Detects whether figure contains an image, video, or embed.
	 *
	 * @param \DOMElement $element   The figure element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed>|null Block array or null.
	 */
	public function handle_figure( \DOMElement $element, Converter $converter ): ?array {
		// Check for img child.
		$img = $element->getElementsByTagName( 'img' )->item( 0 );
		if ( $img instanceof \DOMElement ) {
			return $this->handle_image( $img, $converter );
		}

		// Check for video child.
		$video = $element->getElementsByTagName( 'video' )->item( 0 );
		if ( $video instanceof \DOMElement ) {
			return $this->handle_video( $video, $converter );
		}

		// Fallback: wrap as generic HTML block.
		return $this->handle_raw_html( $element, $converter );
	}

	/**
	 * Handle table element.
	 *
	 * Preserves the full table HTML inside a core/table block.
	 *
	 * @param \DOMElement $element   The table element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_table( \DOMElement $element, Converter $converter ): array {
		$attrs    = $this->attribute_mapper->map_attributes( $element, 'core/table' );
		$table_html = $converter->get_outer_html( $element );

		return array(
			'blockName'    => 'core/table',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<figure class=\"wp-block-table\">{$table_html}</figure>\n",
			'innerContent' => array( "\n<figure class=\"wp-block-table\">{$table_html}</figure>\n" ),
		);
	}

	/**
	 * Handle hr element (separator).
	 *
	 * @param \DOMElement $element   The hr element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_separator( \DOMElement $element, Converter $converter ): array {
		return array(
			'blockName'    => 'core/separator',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n",
			'innerContent' => array( "\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n" ),
		);
	}

	/**
	 * Handle video element.
	 *
	 * @param \DOMElement $element   The video element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed>|null Block array or null.
	 */
	public function handle_video( \DOMElement $element, Converter $converter ): ?array {
		$src = $element->getAttribute( 'src' );

		// Check source child element if no src attribute.
		if ( empty( $src ) ) {
			$source = $element->getElementsByTagName( 'source' )->item( 0 );
			if ( $source instanceof \DOMElement ) {
				$src = $source->getAttribute( 'src' );
			}
		}

		if ( empty( $src ) ) {
			return null;
		}

		$attrs        = $this->attribute_mapper->map_attributes( $element, 'core/video' );
		$attrs['src'] = $src;
		$src_escaped  = esc_url( $src );

		return array(
			'blockName'    => 'core/video',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<figure class=\"wp-block-video\"><video controls src=\"{$src_escaped}\"></video></figure>\n",
			'innerContent' => array( "\n<figure class=\"wp-block-video\"><video controls src=\"{$src_escaped}\"></video></figure>\n" ),
		);
	}

	/**
	 * Handle audio element.
	 *
	 * @param \DOMElement $element   The audio element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed>|null Block array or null.
	 */
	public function handle_audio( \DOMElement $element, Converter $converter ): ?array {
		$src = $element->getAttribute( 'src' );

		if ( empty( $src ) ) {
			$source = $element->getElementsByTagName( 'source' )->item( 0 );
			if ( $source instanceof \DOMElement ) {
				$src = $source->getAttribute( 'src' );
			}
		}

		if ( empty( $src ) ) {
			return null;
		}

		$attrs        = $this->attribute_mapper->map_attributes( $element, 'core/audio' );
		$attrs['src'] = $src;
		$src_escaped  = esc_url( $src );

		return array(
			'blockName'    => 'core/audio',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => "\n<figure class=\"wp-block-audio\"><audio controls src=\"{$src_escaped}\"></audio></figure>\n",
			'innerContent' => array( "\n<figure class=\"wp-block-audio\"><audio controls src=\"{$src_escaped}\"></audio></figure>\n" ),
		);
	}

	/**
	 * Handle generic div element (core/group fallback).
	 *
	 * @param \DOMElement $element   The div element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_div( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'core/group' );
		$inner_blocks = $converter->process_children( $element );

		return array(
			'blockName'    => 'core/group',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => "\n<div class=\"wp-block-group\">\n</div>\n",
			'innerContent' => $this->build_inner_content_with_blocks(
				"\n<div class=\"wp-block-group\">\n",
				count( $inner_blocks ),
				"\n</div>\n"
			),
		);
	}

	/**
	 * Handle raw HTML as a core/html block.
	 *
	 * Used as a fallback for unrecognized elements with no children.
	 *
	 * @param \DOMElement $element   The element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_raw_html( \DOMElement $element, Converter $converter ): array {
		$html = $converter->get_outer_html( $element );

		return array(
			'blockName'    => 'core/html',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => "\n{$html}\n",
			'innerContent' => array( "\n{$html}\n" ),
		);
	}

	/**
	 * Build innerContent array with null placeholders for inner blocks.
	 *
	 * WordPress uses null entries in innerContent to mark where inner blocks go.
	 *
	 * @param string $opening    Opening HTML.
	 * @param int    $block_count Number of inner blocks.
	 * @param string $closing    Closing HTML.
	 * @return array<string|null> innerContent array.
	 */
	private function build_inner_content_with_blocks( string $opening, int $block_count, string $closing ): array {
		$content   = array( $opening );
		for ( $i = 0; $i < $block_count; $i++ ) {
			$content[] = null;
		}
		$content[] = $closing;

		return $content;
	}
}
