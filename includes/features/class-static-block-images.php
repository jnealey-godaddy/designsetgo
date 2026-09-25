<?php
/**
 * Static Block Images
 *
 * Hotspot and Card are static blocks, so their saved `<img>` carries only
 * `src`/`alt`: no dimensions (layout shift) and no `srcset` (the full file is
 * always downloaded). Changing `save()` would need a deprecation, so the
 * attributes are added at render time instead, using the same core helpers
 * `wp_filter_content_tags()` uses for `core/image`. Those helpers verify the
 * `src` belongs to the attachment, so a stale `imageId` adds nothing.
 *
 * With `width`/`height` present, core's content filter then adds
 * `loading`/`fetchpriority`/`decoding` itself (it skips images without
 * dimensions), so this class never sets them for attachment images.
 *
 * Card's "background" layout stores its image as an inline CSS
 * `background-image`, which the browser can't lazy-load. At render time it
 * becomes an absolutely positioned, `object-fit: cover` `<img>` instead.
 *
 * @package DesignSetGo
 * @since 2.8.3
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds dimensions, srcset and lazy loading to static blocks' images.
 */
class Static_Block_Images {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'render_block_designsetgo/hotspot', array( $this, 'render_hotspot' ), 10, 2 );
		add_filter( 'render_block_designsetgo/card', array( $this, 'render_card' ), 10, 2 );
	}

	/**
	 * Enhance the Hotspot's base image.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string Block HTML.
	 */
	public function render_hotspot( $block_content, $block ) {
		return $this->enhance_img( (string) $block_content, 'dsgo-hotspot__image', self::attachment_id( $block ) );
	}

	/**
	 * Enhance the Card's image, or turn its CSS background into an `<img>`.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string Block HTML.
	 */
	public function render_card( $block_content, $block ) {
		$attachment_id = self::attachment_id( $block );
		$block_content = $this->background_to_img( (string) $block_content, $attachment_id );

		return $this->enhance_img( $block_content, 'dsgo-card__image', $attachment_id );
	}

	/**
	 * Read the block's `imageId` attribute.
	 *
	 * @param array $block Parsed block.
	 * @return int Attachment ID, or 0.
	 */
	private static function attachment_id( $block ) {
		return isset( $block['attrs']['imageId'] ) ? absint( $block['attrs']['imageId'] ) : 0;
	}

	/**
	 * Add width/height and srcset/sizes to the first `<img>` with a class.
	 *
	 * @param string $html          Block HTML.
	 * @param string $class_name    Class the `<img>` must carry.
	 * @param int    $attachment_id Attachment the image came from.
	 * @return string Block HTML.
	 */
	private function enhance_img( $html, $class_name, $attachment_id ) {
		if ( ! $attachment_id || false === strpos( $html, $class_name ) ) {
			return $html;
		}

		$pattern = '/<img\b[^>]*\bclass="(?:[^"]*\s)?' . preg_quote( $class_name, '/' ) . '(?:\s[^"]*)?"[^>]*>/';
		if ( ! preg_match( $pattern, $html, $match, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}

		$img = self::add_attachment_attrs( $match[0][0], $attachment_id );

		return substr_replace( $html, $img, $match[0][1], strlen( $match[0][0] ) );
	}

	/**
	 * Add core's dimension and srcset attributes for an attachment.
	 *
	 * @param string $img           `<img>` tag.
	 * @param int    $attachment_id Attachment ID.
	 * @return string `<img>` tag.
	 */
	private static function add_attachment_attrs( $img, $attachment_id ) {
		$img = wp_img_tag_add_width_and_height_attr( $img, 'the_content', $attachment_id );

		return wp_img_tag_add_srcset_and_sizes_attr( $img, 'the_content', $attachment_id );
	}

	/**
	 * Replace the Card's inline `background-image` with a lazy `<img>`.
	 *
	 * Only the exact markup Card's current `save()` emits is rewritten;
	 * anything else (older deprecated markup, a filter's changes) is left
	 * alone, so the fallback is the unchanged CSS background.
	 *
	 * @param string $html          Block HTML.
	 * @param int    $attachment_id Attachment ID, or 0 for an external URL.
	 * @return string Block HTML.
	 */
	private function background_to_img( $html, $attachment_id ) {
		if (
			false === strpos( $html, 'dsgo-card__background' )
			|| ! preg_match( '/<div class="dsgo-card__background" style="([^"]*)">/', $html, $match, PREG_OFFSET_CAPTURE )
		) {
			return $html;
		}

		$style = html_entity_decode( $match[1][0], ENT_QUOTES );
		if ( ! preg_match( '/background-image:\s*url\(\s*([\'"]?)([^\'")]+)\1\s*\)\s*;?/i', $style, $url_match ) ) {
			return $html;
		}

		$url = esc_url( trim( $url_match[2] ) );
		if ( '' === $url ) {
			return $html;
		}

		$img = sprintf( '<img class="dsgo-card__background-image" src="%s" alt="" />', $url );
		if ( $attachment_id ) {
			$img = self::add_attachment_attrs( $img, $attachment_id );
		}
		// Without dimensions core won't choose a loading strategy, so the
		// external-URL case keeps a plain lazy default.
		if ( false === strpos( $img, ' width=' ) ) {
			$img = str_replace( '<img ', '<img loading="lazy" decoding="async" ', $img );
		}

		$rest_style = trim( str_replace( $url_match[0], '', $style ), " \t\n\r;" );
		$open_tag   = '<div class="dsgo-card__background"' . ( '' !== $rest_style ? ' style="' . esc_attr( $rest_style ) . '"' : '' ) . '>';

		return substr_replace( $html, $open_tag . $img, $match[0][1], strlen( $match[0][0] ) );
	}
}
