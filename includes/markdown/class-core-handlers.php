<?php
/**
 * Core Block Handlers
 *
 * Handles conversion of WordPress core blocks to markdown.
 *
 * @package DesignSetGo
 * @since 1.4.0
 */

namespace DesignSetGo\Markdown;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core_Handlers Class
 *
 * Converts core WordPress blocks to markdown.
 */
class Core_Handlers {
	/**
	 * Converter instance.
	 *
	 * @var Converter
	 */
	private $converter;

	/**
	 * Constructor.
	 *
	 * @param Converter $converter Converter instance.
	 */
	public function __construct( Converter $converter ) {
		$this->converter = $converter;
	}

	/**
	 * Handle core/paragraph block.
	 *
	 * @param array $block Block data.
	 * @return string Markdown.
	 */
	public function handle_paragraph( array $block ): string {
		$html = $this->converter->get_inner_html( $block );
		return $this->converter->html_to_text( $html );
	}

	/**
	 * Handle core/heading block.
	 *
	 * @param array $block Block data.
	 * @return string Markdown.
	 */
	public function handle_heading( array $block ): string {
		$level = $block['attrs']['level'] ?? 2;
		$html  = $this->converter->get_inner_html( $block );
		$text  = $this->converter->html_to_text( $html );

		return str_repeat( '#', $level ) . ' ' . $text;
	}

	/**
	 * Handle core/list block.
	 *
	 * @param array $block Block data.
	 * @return string Markdown.
	 */
	public function handle_list( array $block ): string {
		$ordered = ( $block['attrs']['ordered'] ?? false );
		$items   = array();
		$index   = 1;

		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $item ) {
				$text = $this->converter->render_block( $item );
				if ( ! empty( $text ) ) {
					$prefix  = $ordered ? "{$index}. " : '- ';
					$items[] = $prefix . $text;
					++$index;
				}
			}
		} else {
			// Fallback: parse list items directly from HTML (older block format
			// without inner block comments).
			$html = $this->converter->get_inner_html( $block );
			if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $html, $matches ) ) {
				foreach ( $matches[1] as $li_content ) {
					$text = $this->converter->html_to_text( $li_content );
					if ( ! empty( $text ) ) {
						$prefix  = $ordered ? "{$index}. " : '- ';
						$items[] = $prefix . $text;
						++$index;
					}
				}
			}
		}

		return implode( "\n", $items );
	}

	/**
	 * Handle core/list-item block.
	 *
	 * @param array $block Block data.
	 * @return string Markdown (without bullet prefix).
	 */
	public function handle_list_item( array $block ): string {
		$html = $this->converter->get_inner_html( $block );
		return $this->converter->html_to_text( $html );
	}

	/**
	 * Handle core/quote block.
	 *
	 * @param array $block Block data.
	 * @return string Markdown.
	 */
	public function handle_quote( array $block ): string {
		$content = '';

		if ( ! empty( $block['innerBlocks'] ) ) {
			$content = $this->converter->render_blocks( $block['innerBlocks'] );
		} else {
			$html    = $this->converter->get_inner_html( $block );
			$content = $this->converter->html_to_text( $html );
		}

		// Prefix each line with >.
		$lines  = explode( "\n", $content );
		$quoted = array_map( fn( $line ) => '> ' . $line, $lines );

		return implode( "\n", $quoted );
	}

	/**
	 * Handle core/code and core/preformatted blocks.
	 *
	 * @param array $block Block data.
	 * @return string Markdown.
	 */
	public function handle_code( array $block ): string {
		$html = $this->converter->get_inner_html( $block );
		$code = html_entity_decode( wp_strip_all_tags( $html ) );

		return "```\n" . trim( $code ) . "\n```";
	}

	/**
	 * Handle core/image block.
	 *
	 * @param array $block Block data.
	 * @return string Markdown.
	 */
	public function handle_image( array $block ): string {
		$url     = $block['attrs']['url'] ?? '';
		$alt     = $block['attrs']['alt'] ?? '';
		$caption = $block['attrs']['caption'] ?? '';

		if ( empty( $url ) ) {
			$html = $this->converter->get_inner_html( $block );
			if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches ) ) {
				$url = $matches[1];
			}
			if ( preg_match( '/<img[^>]+alt=["\']([^"\']*)["\']/', $html, $matches ) ) {
				$alt = $matches[1];
			}
		}

		if ( empty( $url ) ) {
			return '';
		}

		$markdown = '![' . $this->converter->escape_markdown( $alt ) . '](' . $url . ')';

		if ( ! empty( $caption ) ) {
			$markdown .= "\n*" . $this->converter->html_to_text( $caption ) . '*';
		}

		return $markdown;
	}

	/**
	 * Handle core/separator and designsetgo/divider blocks.
	 *
	 * @param array $_block Block data (unused).
	 * @return string Markdown.
	 */
	public function handle_separator( array $_block ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress hook callback signature
		return '---';
	}

	/**
	 * Handle core/spacer block.
	 *
	 * @param array $_block Block data (unused).
	 * @return string Markdown.
	 */
	public function handle_spacer( array $_block ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress hook callback signature
		return '';
	}

	/**
	 * Handle core/buttons container.
	 *
	 * @param array $block Block data.
	 * @return string Markdown.
	 */
	public function handle_buttons( array $block ): string {
		if ( empty( $block['innerBlocks'] ) ) {
			return '';
		}

		$buttons = array();
		foreach ( $block['innerBlocks'] as $button ) {
			$rendered = $this->converter->render_block( $button );
			if ( ! empty( $rendered ) ) {
				$buttons[] = $rendered;
			}
		}

		return implode( ' | ', $buttons );
	}

	/**
	 * Handle core/button block.
	 *
	 * @param array $block Block data.
	 * @return string Markdown.
	 */
	public function handle_button( array $block ): string {
		$url  = $block['attrs']['url'] ?? '';
		$html = $this->converter->get_inner_html( $block );
		$text = $this->converter->html_to_text( $html );

		if ( empty( $text ) ) {
			return '';
		}

		if ( ! empty( $url ) ) {
			return '[' . $text . '](' . $url . ')';
		}

		return '**' . $text . '**';
	}

	/**
	 * Handle container blocks (columns, groups, grid, row, section).
	 *
	 * @param array $block Block data.
	 * @return string Markdown.
	 */
	public function handle_container( array $block ): string {
		if ( empty( $block['innerBlocks'] ) ) {
			return '';
		}

		return $this->converter->render_blocks( $block['innerBlocks'] );
	}
}
