<?php
/**
 * Dynamic Tags — image resolver.
 *
 * Shared helper that, given a source slug and args, returns a full image
 * descriptor (id, url, alt, width, height). Used both by the dynamic-image
 * block's render.php and by the REST preview endpoint so the editor
 * preview and the frontend always agree.
 *
 * @package DesignSetGo
 * @since   2.2.0
 */

namespace DesignSetGo\Blocks\DynamicTags;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves image-typed Dynamic Tag sources to full descriptors.
 */
class ImageResolver {

	/**
	 * Resolves a source to an image descriptor.
	 *
	 * @param string $slug    Source slug.
	 * @param array  $args    Source args.
	 * @param int    $post_id Current post ID (for context resolution).
	 * @param string $size    Image size (default 'full').
	 * @return array{id:int,url:string,alt:string,width:int,height:int}|null
	 *         Descriptor or null when unresolvable.
	 */
	public static function resolve( $slug, array $args, $post_id = 0, $size = 'full' ) {
		$attachment_id = self::resolve_attachment_id( $slug, $args, (int) $post_id );
		if ( $attachment_id ) {
			return self::descriptor_from_id( $attachment_id, $size );
		}

		// Fallback: resolve URL via the core Bindings API when the source
		// has no attachment concept (e.g. an external URL stored in meta).
		$url = self::resolve_url_via_bindings( $slug, $args, (int) $post_id );
		if ( $url ) {
			return array(
				'id'     => 0,
				'url'    => $url,
				'alt'    => '',
				'width'  => 0,
				'height' => 0,
			);
		}

		return null;
	}

	/**
	 * Attempts to discover an attachment ID for image-typed sources.
	 *
	 * @param string $slug    Source slug.
	 * @param array  $args    Source args.
	 * @param int    $post_id Current post ID context.
	 * @return int Attachment ID or 0.
	 */
	private static function resolve_attachment_id( $slug, array $args, $post_id ) {
		switch ( $slug ) {
			case 'designsetgo/post-featured-image':
				return (int) get_post_thumbnail_id( $post_id );

			case 'designsetgo/site-logo':
				$logo = (int) get_option( 'site_logo' );
				if ( ! $logo ) {
					$logo = (int) get_theme_mod( 'custom_logo' );
				}
				return $logo;

			case 'designsetgo/post-meta':
				$key   = isset( $args['key'] ) ? (string) $args['key'] : '';
				$value = $key && $post_id ? get_post_meta( $post_id, $key, true ) : '';
				return is_numeric( $value ) ? (int) $value : 0;

			case 'designsetgo/acf':
				$key = isset( $args['key'] ) ? (string) $args['key'] : '';
				if ( ! $key || ! $post_id || ! function_exists( 'get_field' ) ) {
					return 0;
				}
				$value = \get_field( $key, $post_id );
				if ( is_array( $value ) && isset( $value['ID'] ) ) {
					return (int) $value['ID'];
				}
				if ( is_numeric( $value ) ) {
					return (int) $value;
				}
				return 0;
		}

		return 0;
	}

	/**
	 * Resolves a scalar sub-value (url/id/alt/width/height/title/caption)
	 * from an attachment ID. Used by image-typed Bindings sources that
	 * need to project to a single scalar per the Block Bindings contract.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $subkey        Sub-key: url|id|alt|width|height|title|caption.
	 * @param string $size          Image size for url/width/height (default 'full').
	 * @return string|null
	 */
	public static function resolve_subvalue( $attachment_id, $subkey, $size = 'full' ) {
		$attachment_id = (int) $attachment_id;
		if ( ! $attachment_id ) {
			return null;
		}
		if ( ! in_array( $subkey, array( 'url', 'id', 'alt', 'width', 'height', 'title', 'caption' ), true ) ) {
			$subkey = 'url';
		}

		switch ( $subkey ) {
			case 'id':
				return (string) $attachment_id;
			case 'alt':
				$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				return '' === $alt ? null : (string) $alt;
			case 'title':
				$title = get_the_title( $attachment_id );
				return '' === $title ? null : (string) $title;
			case 'caption':
				$caption = wp_get_attachment_caption( $attachment_id );
				return '' === $caption || false === $caption ? null : (string) $caption;
			case 'width':
			case 'height':
				$src = wp_get_attachment_image_src( $attachment_id, $size );
				if ( ! $src ) {
					return null;
				}
				$idx = 'width' === $subkey ? 1 : 2;
				return (string) $src[ $idx ];
			case 'url':
			default:
				$src = wp_get_attachment_image_src( $attachment_id, $size );
				return $src && ! empty( $src[0] ) ? (string) $src[0] : null;
		}
	}

	/**
	 * Builds an image descriptor from an attachment ID.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $size          Image size.
	 * @return array{id:int,url:string,alt:string,width:int,height:int}|null
	 */
	public static function descriptor_from_id( $attachment_id, $size = 'full' ) {
		$attachment_id = (int) $attachment_id;
		if ( ! $attachment_id ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $attachment_id, $size );
		if ( ! $src || empty( $src[0] ) ) {
			return null;
		}
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		return array(
			'id'     => $attachment_id,
			'url'    => (string) $src[0],
			'alt'    => is_string( $alt ) ? $alt : '',
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
		);
	}

	/**
	 * Falls back to the core Bindings API for a URL-only scalar.
	 *
	 * @param string $slug    Source slug.
	 * @param array  $args    Source args.
	 * @param int    $post_id Current post ID.
	 * @return string Empty string when unresolvable.
	 */
	private static function resolve_url_via_bindings( $slug, array $args, $post_id ) {
		if ( ! function_exists( 'get_block_bindings_source' ) ) {
			return '';
		}
		$binding = get_block_bindings_source( $slug );
		if ( ! $binding || ! isset( $binding->get_value_callback ) || ! is_callable( $binding->get_value_callback ) ) {
			return '';
		}
		$args['__dsgo_post_id'] = $post_id;
		$args['subkey']         = $args['subkey'] ?? 'url';
		$value                  = call_user_func( $binding->get_value_callback, $args, null, 'url' );
		if ( is_string( $value ) && '' !== $value && filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return $value;
		}
		return '';
	}
}
